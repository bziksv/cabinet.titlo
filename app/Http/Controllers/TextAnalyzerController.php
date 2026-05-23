<?php

namespace App\Http\Controllers;

use App\Exports\TextAnalyzer\TextAnalyzerWorkbookExport;
use App\Services\TextAnalyzerPdfService;
use App\TariffSetting;
use App\TextAnalyzer;
use App\TextAnalyzerPublicShare;
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

        return view('text-analyse.index', [
            'response' => $response,
            'request' => $request,
            'url' => $url,
            'scrollToResults' => $scrollToResults,
            'publicShare' => $publicShare,
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

        session()->flash('text_analyzer.response', $response);
        session()->flash('text_analyzer.request', $request);
        session()->flash('text_analyzer.scroll_to_results', true);

        return redirect()->route('text.analyzer.view');
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
                'textarea' => 'required|min:200',
            ], [
                'textarea.required' => __("You didn't fill in the text field"),
                'textarea.min' => __('The text length is at least 200 characters'),
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
