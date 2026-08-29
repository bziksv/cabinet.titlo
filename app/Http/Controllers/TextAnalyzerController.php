<?php

namespace App\Http\Controllers;

use App\Exports\TextAnalyzer\TextAnalyzerWorkbookExport;
use App\Services\TextAnalyzerPdfService;
use App\Services\TextUniquenessService;
use App\Support\EseninTextCheckLimits;
use App\Support\DemoCabinet;
use App\Support\TextAnalyzerEsenin;
use App\Support\TextAnalyzerHistorySave;
use App\Support\TextAnalyzerUniqueness;
use App\Support\TextUniquenessLimits;
use App\TariffSetting;
use App\TextAnalyzer;
use App\TextAnalyzerPublicShare;
use App\TextUniquenessHistory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TextAnalyzerController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:Text analyzer']);
    }

    /**
     * @return array|false|Application|Factory|View|mixed
     */
    public function index()
    {
        $response = session('text_analyzer.response');
        $request = session('text_analyzer.request', []);
        $url = session('text_analyzer.url');
        $scrollToResults = session('text_analyzer.scroll_to_results', false);

        if ($response !== null) {
            session(['text_analyzer.export_snapshot' => [
                'response' => $response,
                'request' => $request,
                'url' => $url,
            ]]);
        }

        // Не держим результат в session (v1.5): после отображения — сброс; F5 / новая вкладка без «залипшего» анализа
        session()->forget([
            'text_analyzer.response',
            'text_analyzer.request',
            'text_analyzer.url',
            'text_analyzer.scroll_to_results',
        ]);

        $exportSnapshot = session('text_analyzer.export_snapshot');
        $publicShare = null;
        if (is_array($exportSnapshot) && !empty($exportSnapshot['response'])) {
            $activeShare = TextAnalyzerPublicShare::activeForUser((int) Auth::id());
            if ($activeShare !== null && $activeShare->matchesSnapshot($exportSnapshot)) {
                $publicShare = $activeShare;
            }
        }

        $user = Auth::user();
        $uniquenessLimit = TextUniquenessLimits::limitForUser($user);
        $uniquenessRemaining = TextUniquenessLimits::remainingForUser($user);
        $canSaveUniquenessHistory = TextUniquenessLimits::canSaveHistory($user);
        $uniquenessHistories = [];
        $uniquenessHistoryLimit = TextUniquenessLimits::historyLimitForUser($user);
        $uniquenessHistoryCount = TextUniquenessLimits::savedCount($user);
        if ($canSaveUniquenessHistory) {
            $uniquenessHistories = TextUniquenessHistory::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit((int) ($uniquenessHistoryLimit ?: 50))
                ->get(['id', 'title', 'mode', 'uniqueness_pct', 'cost', 'params', 'created_at']);
        }

        $canCheckEsenin = $user && $user->can('Esenin text check');
        $eseninRemaining = $canCheckEsenin ? EseninTextCheckLimits::remainingForUser($user) : null;
        $eseninLimit = $canCheckEsenin ? EseninTextCheckLimits::limitForUser($user) : null;

        // Демо: сразу полный снимок (слова / Zipf / уникальность / Есенин), не пустая форма.
        if (DemoCabinet::isCurrentUser()) {
            $showcase = DemoCabinet::textAnalyzerShowcase();
            if ($showcase && empty($response)) {
                $response = $showcase['response'];
                $request = $showcase['request'];
                $scrollToResults = true;
            }
        }

        return view('text-analyse.index', [
            'response' => $response,
            'request' => $request,
            'url' => $url,
            'scrollToResults' => $scrollToResults,
            'publicShare' => $publicShare,
            'uniquenessLimit' => $uniquenessLimit,
            'uniquenessRemaining' => $uniquenessRemaining,
            'canSaveUniquenessHistory' => $canSaveUniquenessHistory,
            'uniquenessHistories' => $uniquenessHistories,
            'uniquenessHistoryLimit' => $uniquenessHistoryLimit,
            'uniquenessHistoryCount' => $uniquenessHistoryCount,
            'canCheckEsenin' => $canCheckEsenin,
            'eseninRemaining' => $eseninRemaining,
            'eseninLimit' => $eseninLimit,
            'batchMax' => (int) config('cabinet-text-analyzer.batch_max', 20),
        ]);
    }

    /**
     * @param Request $request
     * @return array|false|Application|Factory|RedirectResponse|View|mixed
     * @throws ValidationException
     */
    public function analyze(Request $request)
    {
        $this->validator($request);

        if (TariffSetting::checkTextAnalyserLimits()) {
            flash()->overlay(__('Your limits are exhausted this month'), ' ')->error();
            return Redirect::back();
        }

        session()->forget('text_analyzer.export_snapshot');

        $request = $request->all();

        if ($request['type'] === 'url') {
            $html = TextAnalyzer::curlInit($request['url']);
            if (!$html) {
                flash()->overlay($request['url'], __('connection attempt failed'))->error();

                return view('text-analyse.index', ['request' => $request]);
            }
            $html = TextAnalyzer::removeStylesAndScripts($html);
            $response = TextAnalyzer::analyze($html, $request);
        } else {
            $response = TextAnalyzer::analyze($request['textarea'], $request);
        }

        if (TextAnalyzer::shouldCompareCompetitor($request)) {
            $competitorUrl = trim((string) ($request['competitorUrl'] ?? ''));
            $competitorHtml = TextAnalyzer::curlInit($competitorUrl);
            if (!$competitorHtml) {
                flash()->overlay($competitorUrl, __('Competitor page connection failed'))->warning();
            } else {
                $competitorHtml = TextAnalyzer::removeStylesAndScripts($competitorHtml);
                $competitorResponse = TextAnalyzer::analyze($competitorHtml, $request);
                TextAnalyzer::attachCompetitorComparison($response, $competitorResponse, $competitorUrl);
            }
        }

        $pack = TextAnalyzerUniqueness::attach($request, $response, Auth::user());
        $response = $pack['response'];
        if ($pack['uniqueness_error']) {
            flash()->overlay($pack['uniqueness_error'], __('Text uniqueness'))->warning();
        }

        $eseninInput = (string) ($pack['plain'] ?? '');
        if ($eseninInput === '' && ($request['type'] ?? '') === 'text') {
            $eseninInput = TextAnalyzer::normalizePlainForUniqueness((string) ($request['textarea'] ?? ''));
        }
        $eseninPack = TextAnalyzerEsenin::attach($request, $response, $eseninInput, Auth::user());
        $response = $eseninPack['response'];
        if ($eseninPack['esenin_error']) {
            flash()->overlay($eseninPack['esenin_error'], __('Esenin text check'))->warning();
        }

        $plainForHistory = (string) ($pack['plain'] ?? $eseninInput);
        $historyPack = TextAnalyzerHistorySave::maybeSave($request, $response, $plainForHistory, Auth::user());
        if (! empty($historyPack['warning'])) {
            flash()->overlay($historyPack['warning'], __('Text uniqueness history title'))->warning();
        }

        session()->flash('text_analyzer.response', $response);
        session()->flash('text_analyzer.request', $request);
        session()->flash('text_analyzer.scroll_to_results', true);

        return redirect()->route('text.analyzer.view');
    }

    /**
     * Один элемент пакетной проверки (URL или текст).
     */
    public function batchItem(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'auth', 'message' => __('Unauthorized')], 401);
        }

        if (TariffSetting::checkTextAnalyserLimits()) {
            return response()->json([
                'error' => 'limit',
                'message' => __('Your limits are exhausted this month'),
            ], 403);
        }

        $type = $request->input('type') === 'text' ? 'text' : 'url';
        $payload = [
            'type' => $type,
            'url' => (string) $request->input('url', ''),
            'textarea' => (string) $request->input('textarea', ''),
            'noIndex' => $request->input('noIndex'),
            'hiddenText' => $request->input('hiddenText'),
            'conjunctionsPrepositionsPronouns' => $request->input('conjunctionsPrepositionsPronouns', 1),
            'removeWords' => $request->input('removeWords'),
            'listWords' => $request->input('listWords'),
            'checkUniqueness' => $request->input('checkUniqueness', 1),
            'saveUniqueness' => $request->input('saveUniqueness', 0),
            'checkEsenin' => $request->input('checkEsenin', 0),
            'excludeOwnDomain' => (string) $request->input('excludeOwnDomain', ''),
            'batchLabel' => (string) $request->input('label', ''),
            'taskName' => trim((string) $request->input('taskName', '')),
        ];

        if ($type === 'url') {
            if (trim($payload['url']) === '') {
                return response()->json(['error' => 'validation', 'message' => __("You didn't fill in the URL field")], 422);
            }
            $html = TextAnalyzer::curlInit($payload['url']);
            if (! $html) {
                return response()->json([
                    'ok' => false,
                    'label' => $payload['url'],
                    'error' => __('connection attempt failed'),
                ]);
            }
            $html = TextAnalyzer::removeStylesAndScripts($html);
            $response = TextAnalyzer::analyze($html, $payload);
            if ($payload['excludeOwnDomain'] === '') {
                $payload['excludeOwnDomain'] = TextAnalyzer::urlHost($payload['url']);
            }
        } else {
            if (mb_strlen(trim($payload['textarea'])) < 200) {
                return response()->json([
                    'error' => 'validation',
                    'message' => __('The text length is at least 200 characters'),
                ], 422);
            }
            $response = TextAnalyzer::analyze($payload['textarea'], $payload);
        }

        $pack = TextAnalyzerUniqueness::attach($payload, $response, $user);
        $response = $pack['response'];
        $eseninInput = (string) ($pack['plain'] ?? '');
        if ($eseninInput === '' && $type === 'text') {
            $eseninInput = TextAnalyzer::normalizePlainForUniqueness($payload['textarea']);
        }
        $eseninPack = TextAnalyzerEsenin::attach($payload, $response, $eseninInput, $user);
        $response = $eseninPack['response'];
        $historyPack = TextAnalyzerHistorySave::maybeSave(
            $payload,
            $response,
            (string) ($pack['plain'] ?? $eseninInput),
            $user
        );
        $uniq = $response['uniqueness'] ?? null;
        $esenin = $response['esenin'] ?? null;

        return response()->json([
            'ok' => empty($uniq['error'] ?? null) && empty($esenin['error'] ?? null),
            'label' => $payload['batchLabel'] !== ''
                ? $payload['batchLabel']
                : ($type === 'url' ? $payload['url'] : mb_substr(trim($payload['textarea']), 0, 80)),
            'type' => $type,
            'general' => [
                'countWordsAll' => $response['general']['countWordsAll'] ?? 0,
                'countStopWords' => $response['general']['countStopWords'] ?? 0,
                'countWordsWithoutStopWords' => $response['general']['countWordsWithoutStopWords'] ?? 0,
                'textLength' => $response['general']['textLength'] ?? 0,
                'lengthWithOutSpaces' => $response['general']['lengthWithOutSpaces'] ?? 0,
            ],
            'uniqueness_pct' => $uniq['uniqueness_pct'] ?? null,
            'uniqueness_cost' => $uniq['cost'] ?? 0,
            'uniqueness' => $uniq,
            'esenin_risk' => $esenin['risk'] ?? null,
            'esenin_level' => $esenin['level'] ?? null,
            'esenin' => $esenin,
            'history_id' => $historyPack['history_id'],
            'history_warning' => $historyPack['warning'],
            'message' => $pack['uniqueness_error'] ?: $eseninPack['esenin_error'],
            'uniqueness_remaining' => TextUniquenessLimits::remainingForUser($user),
            'esenin_remaining' => EseninTextCheckLimits::remainingForUser($user),
        ]);
    }

    public function uniquenessHistoryShow(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! TextUniquenessLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden', 'message' => __('Text uniqueness history paid only')], 403);
        }

        $row = TextUniquenessHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $row) {
            return response()->json(['error' => 'not_found', 'message' => __('Not found')], 404);
        }

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $row->id,
                'title' => $row->title,
                'mode' => $row->mode,
                'params' => $row->params,
                'results' => $row->results,
                'uniqueness_pct' => $row->uniqueness_pct,
                'cost' => $row->cost,
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Открыть сохранённую проверку: полный снимок анализатора (слова / Zipf / уникальность / Есенин).
     */
    public function uniquenessHistoryOpen(int $id)
    {
        $user = Auth::user();
        if (! $user || ! TextUniquenessLimits::canSaveHistory($user)) {
            flash()->overlay(__('Text uniqueness history paid only'), __('Text uniqueness history title'))->warning();

            return redirect()->route('text.analyzer.view');
        }

        $row = TextUniquenessHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $row) {
            flash()->overlay(__('Not found'), __('Text uniqueness history title'))->warning();

            return redirect()->route('text.analyzer.view');
        }

        $params = is_array($row->params) ? $row->params : [];
        $source = (string) ($params['source'] ?? '');

        // Записи только из модуля Есенина — туда же.
        if ($source === 'esenin-text-check' && empty($params['had_analysis'])) {
            return redirect('/esenin-text-check?history=' . (int) $row->id);
        }

        $response = TextAnalyzerHistorySave::responseFromHistory($row);
        if ($response === null) {
            flash()->overlay(__('Not found'), __('Text uniqueness history title'))->warning();

            return redirect()->route('text.analyzer.view');
        }

        // Нет полного анализа, но есть Есенин — открываем в модуле Есенина.
        if (empty($params['had_analysis']) && empty($response['totalWords']) && ! empty($response['esenin']) && empty($response['uniqueness'])) {
            return redirect('/esenin-text-check?history=' . (int) $row->id);
        }

        $request = TextAnalyzerHistorySave::requestFromHistory($row);

        session()->flash('text_analyzer.response', $response);
        session()->flash('text_analyzer.request', $request);
        session()->flash('text_analyzer.scroll_to_results', true);
        if (($request['type'] ?? '') === 'url' && ! empty($request['url'])) {
            session()->flash('text_analyzer.url', $request['url']);
        }

        return redirect()->route('text.analyzer.view');
    }

    public function uniquenessHistoryDestroy(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! TextUniquenessLimits::canSaveHistory($user)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $deleted = TextUniquenessHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        return response()->json([
            'ok' => true,
            'deleted' => (bool) $deleted,
            'saved_count' => TextUniquenessLimits::savedCount($user),
        ]);
    }

    public function uniquenessEstimate(Request $request): JsonResponse
    {
        $type = $request->input('type') === 'url' ? 'url' : 'text';
        $text = (string) $request->input('text', '');
        $checkUniqueness = ! in_array($request->input('checkUniqueness'), [false, 0, '0', 'false', 'off', '', null], true);
        $checkEsenin = ! in_array($request->input('checkEsenin'), [false, 0, '0', 'false', 'off', '', null], true);
        $compare = TextAnalyzer::shouldCompareCompetitor([
            'compareCompetitor' => $request->input('compareCompetitor'),
        ]);
        $batchCount = max(0, min(50, (int) $request->input('batch_count', 0)));

        $uniquenessApprox = false;
        if ($checkUniqueness && $text === '' && ($type === 'url' || $batchCount > 0)) {
            // До загрузки страницы точную длину не знаем — минимум зондов
            $text = str_repeat('слово ', 80);
            $uniquenessApprox = true;
        }

        $uniquenessPerItem = $checkUniqueness
            ? TextUniquenessService::estimateCost(['mode' => 'internet', 'text' => $text])
            : 0;
        $eseninPerItem = $checkEsenin ? EseninTextCheckLimits::checkCost() : 0;
        $analyzerPerItem = 1 + ($compare && $batchCount === 0 ? 1 : 0);

        $items = $batchCount > 0 ? $batchCount : 1;

        return response()->json([
            'ok' => true,
            'items' => $items,
            'analyzer' => $analyzerPerItem * $items,
            'uniqueness' => $uniquenessPerItem * $items,
            'uniqueness_per_item' => $uniquenessPerItem,
            'uniqueness_approx' => $uniquenessApprox,
            'esenin' => $eseninPerItem * $items,
            'esenin_per_item' => $eseninPerItem,
            // обратная совместимость
            'cost' => $uniquenessPerItem * $items,
        ]);
    }

    /**
     * @return BinaryFileResponse|RedirectResponse
     */
    public function exportExcel()
    {
        $snapshot = $this->exportSnapshot();
        if ($snapshot === null) {
            flash()->overlay(__('Run the analysis again before exporting.'), __('Export'))->warning();
            return redirect()->route('text.analyzer.view');
        }

        $meta = $this->buildExportMeta($snapshot);
        $fileName = 'text-analyzer-' . date('Y-m-d-His') . '.xlsx';

        return Excel::download(
            new TextAnalyzerWorkbookExport($snapshot['response'], $snapshot['request'], $meta),
            $fileName,
            ExcelFormat::XLSX
        );
    }

    /**
     * @return BinaryFileResponse|RedirectResponse
     */
    public function createPublicShare(): JsonResponse
    {
        $snapshot = $this->exportSnapshot();
        if ($snapshot === null) {
            return response()->json([
                'success' => false,
                'message' => __('Run the analysis again before exporting.'),
                'code' => 415,
            ]);
        }

        if (!TextAnalyzerPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable. Run database migration text_analyzer_public_shares.'),
                'code' => 503,
            ]);
        }

        $share = TextAnalyzerPublicShare::issueForUser(
            (int) Auth::id(),
            $snapshot,
            $this->buildExportMeta($snapshot)
        );

        if ($share === null) {
            return response()->json([
                'success' => false,
                'message' => __('Public link could not be created.'),
                'code' => 500,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Public link created'),
            'code' => 201,
            'url' => $share->publicUrl(),
            'expires_at' => $share->expires_at->format('d.m.Y H:i'),
        ]);
    }

    public function revokePublicShare(): JsonResponse
    {
        $snapshot = $this->exportSnapshot();
        if ($snapshot !== null) {
            TextAnalyzerPublicShare::revokeForUserSnapshot((int) Auth::id(), $snapshot);
        } else {
            TextAnalyzerPublicShare::revokeActiveForUser((int) Auth::id());
        }

        return response()->json([
            'success' => true,
            'message' => __('Public link revoked'),
            'code' => 201,
        ]);
    }

    public function exportPdf()
    {
        $snapshot = $this->exportSnapshot();
        if ($snapshot === null) {
            flash()->overlay(__('Run the analysis again before exporting.'), __('Export'))->warning();
            return redirect()->route('text.analyzer.view');
        }

        $meta = $this->buildExportMeta($snapshot);
        $fileName = 'text-analyzer-report-' . date('Y-m-d-His') . '.pdf';

        return app(TextAnalyzerPdfService::class)->downloadResponse(
            $snapshot['response'],
            $snapshot['request'] ?? [],
            $meta,
            $fileName
        );
    }

    /**
     * @param $url
     * @return Application|array|Factory|false|View
     */
    public function redirectToAnalyse($url)
    {
        $url = str_replace('abc', '/', $url);

        return view('text-analyse.index', compact('url'));
    }

    /**
     * @param Request $request
     * @return void
     * @throws ValidationException
     */
    protected function validator(Request $request)
    {
        if ($request['type'] === 'text') {
            $this->validate($request, [
                'textarea' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $plain = TextAnalyzer::normalizePlainForUniqueness((string) $value);
                        if (mb_strlen($plain) < 200) {
                            $fail(__('The text length is at least 200 characters'));
                        }
                    },
                ],
            ], [
                'textarea.required' => __("You didn't fill in the text field"),
            ]);
        } else {
            $this->validate($request, [
                'url' => 'required|website',
            ], [
                'url.required' => __("You didn't fill in the URL field"),
                'url.website' => __('The URL must be valid')
            ]);
        }

        if (TextAnalyzer::shouldCompareCompetitor($request->all())) {
            $rules = [
                'competitorUrl' => 'required|website',
            ];
            $messages = [
                'competitorUrl.required' => __('Enter the competitor page URL'),
                'competitorUrl.website' => __('The competitor URL must be valid'),
            ];
            if ($request['type'] === 'url' && !empty($request['url'])) {
                $rules['competitorUrl'] .= '|different:url';
                $messages['competitorUrl.different'] = __('Competitor URL must differ from your page URL');
            }
            $this->validate($request, $rules, $messages);
        }
    }

    protected function exportSnapshot(): ?array
    {
        $snapshot = session('text_analyzer.export_snapshot');
        if (!is_array($snapshot) || empty($snapshot['response'])) {
            return null;
        }

        return $snapshot;
    }

    protected function buildExportMeta(array $snapshot): array
    {
        $request = $snapshot['request'] ?? [];
        $url = $snapshot['url'] ?? null;
        $sourceLabel = ($request['type'] ?? '') === 'url'
            ? (string) ($request['url'] ?? $url ?? '')
            : __('Text Analysis');

        if (($request['type'] ?? '') !== 'url' && !empty($request['textarea'])) {
            $preview = mb_substr(trim((string) $request['textarea']), 0, 180);
            if (mb_strlen(trim((string) $request['textarea'])) > 180) {
                $preview .= '…';
            }
            $sourceLabel = $preview;
        }

        return [
            'generated_at' => now()->format('d.m.Y H:i'),
            'source_label' => $sourceLabel,
            'version' => config('cabinet-text-analyzer.version', '1.0'),
            'brand_name' => \App\Support\TextAnalyzerPdfBranding::BRAND_NAME,
            'brand_site' => \App\Support\TextAnalyzerPdfBranding::BRAND_SITE,
        ];
    }

}
