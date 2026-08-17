<?php

namespace App\Http\Controllers;

use App\EseninTextCheckPublicShare;
use App\Services\EseninTextCheckService;
use App\Services\EseninTextCheckSessionService;
use App\Support\DemoCabinet;
use App\Support\Esenin\EseninAnalyzer;
use App\Support\EseninTextCheckLimits;
use App\Support\EseninTextCheckPublicShareTtl;
use App\Support\EseninTextCheckSettingsRegistry;
use App\Support\TextAnalyzerHistorySave;
use App\Support\TextUniquenessLimits;
use App\TextUniquenessHistory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EseninTextCheckController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:Esenin text check']);
    }

    /**
     * @return array|Factory|JsonResponse|RedirectResponse|View
     */
    public function index(Request $request)
    {
        if ($request->boolean('ajax')) {
            return $this->ajaxCheck($request);
        }

        // Демо: сразу открываем готовую сессию с результатом
        if (DemoCabinet::isCurrentUser() && ! $request->filled('session')) {
            $showcase = DemoCabinet::eseninShowcasePath();
            if ($showcase) {
                return redirect($showcase);
            }
        }

        $limit = EseninTextCheckLimits::limitForUser();
        $remaining = EseninTextCheckLimits::remainingForUser();
        $costPerCheck = EseninTextCheckLimits::checkCost();
        $maxChars = EseninTextCheckSettingsRegistry::moduleInt('max_chars', 20000);
        $modes = EseninTextCheckService::MODES;
        $maxVersions = EseninTextCheckSessionService::maxVersionsPerSession();
        $autosaveDebounceMs = EseninTextCheckSettingsRegistry::moduleInt('autosave_debounce_ms', 2500);
        $sessionsAvailable = EseninTextCheckSessionService::tablesReady();
        $publicShareAvailable = EseninTextCheckPublicShare::tableAvailable();
        $shareTtlOptions = EseninTextCheckPublicShareTtl::labelsForUi();

        $user = Auth::user();
        $canSaveUniquenessHistory = TextUniquenessLimits::canSaveHistory($user);
        $uniquenessHistories = collect();
        $uniquenessHistoryLimit = TextUniquenessLimits::historyLimitForUser($user);
        $uniquenessHistoryCount = TextUniquenessLimits::savedCount($user);
        if ($canSaveUniquenessHistory) {
            $uniquenessHistories = TextUniquenessHistory::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit((int) ($uniquenessHistoryLimit ?: 50))
                ->get(['id', 'title', 'mode', 'uniqueness_pct', 'cost', 'params', 'created_at']);
        }

        return view('pages.esenin-text-check', compact(
            'limit',
            'remaining',
            'costPerCheck',
            'maxChars',
            'modes',
            'maxVersions',
            'autosaveDebounceMs',
            'sessionsAvailable',
            'publicShareAvailable',
            'shareTtlOptions',
            'canSaveUniquenessHistory',
            'uniquenessHistories',
            'uniquenessHistoryLimit',
            'uniquenessHistoryCount'
        ));
    }

    public function save(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['error' => 'auth', 'message' => 'Требуется авторизация'], 401);
        }

        try {
            $payload = EseninTextCheckSessionService::saveDraft($userId, [
                'session_id' => $request->input('session_id'),
                'name' => $request->input('name'),
                'text' => (string) $request->input('text', ''),
                'source' => (string) $request->input('source', 'text'),
                'source_url' => $request->input('url'),
                'tbclass' => $request->input('tbclass'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'unavailable', 'message' => $e->getMessage()], 503);
        }

        return response()->json(array_merge(['ok' => true], $payload));
    }

    public function showSession(int $session): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['error' => 'auth', 'message' => 'Требуется авторизация'], 401);
        }

        $model = EseninTextCheckSessionService::findSessionForUser($session, $userId);
        if ($model === null) {
            return response()->json(['error' => 'not_found', 'message' => 'Задание не найдено'], 404);
        }

        return response()->json(array_merge(['ok' => true], EseninTextCheckSessionService::sessionPayload($model)));
    }

    public function listSessions(): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['error' => 'auth', 'message' => 'Требуется авторизация'], 401);
        }

        if (! EseninTextCheckSessionService::tablesReady()) {
            return response()->json(['error' => 'unavailable', 'message' => 'Хранение заданий временно недоступно'], 503);
        }

        return response()->json([
            'ok' => true,
            'sessions' => EseninTextCheckSessionService::listSessionsForUser($userId),
        ]);
    }

    public function loadVersion(int $session, int $version): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['error' => 'auth', 'message' => 'Требуется авторизация'], 401);
        }

        $model = EseninTextCheckSessionService::findSessionForUser($session, $userId);
        if ($model === null) {
            return response()->json(['error' => 'not_found', 'message' => 'Задание не найдено'], 404);
        }

        $versionModel = $model->versions()->where('id', $version)->first();
        if ($versionModel === null) {
            return response()->json(['error' => 'not_found', 'message' => 'Версия не найдена'], 404);
        }

        return response()->json([
            'ok' => true,
            'session_id' => (int) $model->id,
            'name' => $model->name,
            'source' => $model->source,
            'source_url' => $model->source_url,
            'tbclass' => $model->tbclass,
            'version' => EseninTextCheckSessionService::versionPayload($versionModel),
        ]);
    }

    private function ajaxCheck(Request $request): JsonResponse
    {
        $source = (string) $request->input('source', 'text');
        $mode = (string) $request->input('mode', EseninTextCheckSettingsRegistry::moduleString('default_mode', 'risk'));
        $cost = EseninTextCheckLimits::checkCost();

        if (! EseninTextCheckLimits::canSpend($cost)) {
            $message = EseninTextCheckLimits::limitMessage() ?: __('Esenin text check limit exhausted');

            return response()->json([
                'error' => 'limit',
                'message' => $message,
                'remaining' => EseninTextCheckLimits::remainingForUser(),
                'limit' => EseninTextCheckLimits::limitForUser(),
            ], 403);
        }

        try {
            EseninTextCheckService::normalizeMode($mode);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation', 'message' => $e->getMessage()], 422);
        }

        $checkedText = '';

        try {
            if ($source === 'url') {
                $url = (string) $request->input('url', '');
                $tbclass = (string) $request->input('tbclass', '');
                if (! preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
                $checkedText = EseninAnalyzer::extractTextFromUrl($url, trim($tbclass));
                $result = EseninTextCheckService::checkText($checkedText, [
                    'mode' => $mode,
                    'url' => $url,
                    'tbclass' => trim($tbclass),
                ]);
            } else {
                $checkedText = (string) $request->input('text', '');
                $result = EseninTextCheckService::checkText($checkedText, [
                    'mode' => $mode,
                ]);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'fetch_failed',
                'message' => $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            \Log::warning('Esenin text check failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'fetch_failed',
                'message' => 'Не удалось выполнить проверку. Попробуйте позже.',
            ], 502);
        }

        EseninTextCheckLimits::spend($cost);

        $sessionPayload = null;
        $userId = (int) Auth::id();
        if ($userId > 0 && EseninTextCheckSessionService::tablesReady()) {
            try {
                $sessionPayload = EseninTextCheckSessionService::saveDraft($userId, [
                    'session_id' => $request->input('session_id'),
                    'name' => $request->input('name'),
                    'text' => $checkedText !== '' ? $checkedText : (string) $request->input('text', ''),
                    'source' => $source,
                    'source_url' => $request->input('url'),
                    'tbclass' => $request->input('tbclass'),
                    'result' => $result,
                    'is_check' => true,
                ]);
            } catch (\Throwable $e) {
                $sessionPayload = null;
            }
        }

        $plainForHistory = $checkedText !== '' ? $checkedText : (string) $request->input('text', '');
        if (\App\Support\Esenin\EseninHtmlHighlighter::isHtml($plainForHistory)) {
            $plainForHistory = trim(EseninAnalyzer::extractPlainText($plainForHistory));
        }
        $wantSaveHistory = ! in_array($request->input('save_history'), [false, 0, '0', 'false', 'off', '', null], true);
        $historyPack = TextAnalyzerHistorySave::maybeSaveFromEsenin(
            $plainForHistory,
            is_array($result) ? $result : [],
            [
                'name' => $request->input('name'),
                'source' => $source,
                'url' => $request->input('url'),
                'tbclass' => $request->input('tbclass'),
            ],
            Auth::user(),
            $wantSaveHistory
        );

        $shareState = null;
        if ($userId > 0 && $sessionPayload !== null) {
            $shareState = $this->shareStateForSession(
                (int) ($sessionPayload['session_id'] ?? 0),
                $result,
                $checkedText !== '' ? $checkedText : (string) $request->input('text', ''),
                (string) $request->input('name', '')
            );
        }

        return response()->json([
            'ok' => true,
            'cost' => $cost,
            'remaining' => EseninTextCheckLimits::remainingForUser(),
            'limit' => EseninTextCheckLimits::limitForUser(),
            'result' => $result,
            'session' => $sessionPayload,
            'share' => $shareState,
            'history_id' => $historyPack['history_id'],
            'history_warning' => $historyPack['warning'],
            'history_saved_count' => TextUniquenessLimits::savedCount(Auth::user()),
            'history_limit' => TextUniquenessLimits::historyLimitForUser(Auth::user()),
        ]);
    }

    public function historyShow(int $id): JsonResponse
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

    public function historyDestroy(int $id): JsonResponse
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

    public function createPublicShare(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Требуется авторизация'], 401);
        }

        if (!EseninTextCheckPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable. Run database migration esenin_text_check_public_shares.'),
            ], 503);
        }

        $result = $request->input('result');
        if (!is_array($result) || $result === []) {
            $decoded = json_decode((string) $request->input('result_json', ''), true);
            if (is_array($decoded) && $decoded !== []) {
                $result = $decoded;
            }
        }
        if (!is_array($result) || $result === []) {
            return response()->json([
                'success' => false,
                'message' => __('Run the analysis again before exporting.'),
            ], 415);
        }

        $text = trim((string) $request->input('text', ''));
        if ($text === '') {
            return response()->json([
                'success' => false,
                'message' => 'Текст проверки пустой',
            ], 422);
        }

        $sessionId = (int) $request->input('session_id', 0);
        if ($sessionId > 0 && EseninTextCheckSessionService::findSessionForUser($sessionId, $userId) === null) {
            return response()->json([
                'success' => false,
                'message' => 'Задание не найдено',
            ], 404);
        }

        $ttlDays = EseninTextCheckPublicShareTtl::normalize($request->input('ttl_days', 30));
        $name = trim((string) $request->input('name', ''));
        $snapshot = [
            'result' => $result,
            'text' => $text,
            'name' => $name,
        ];
        $meta = [
            'generated_at' => now()->format('d.m.Y H:i'),
            'source_label' => $name !== '' ? $name : EseninTextCheckSessionService::defaultName($text),
            'version' => config('cabinet-esenin-text-check.version'),
        ];

        $share = EseninTextCheckPublicShare::issueForSession(
            $userId,
            $sessionId > 0 ? $sessionId : null,
            $snapshot,
            $meta,
            $ttlDays
        );

        if ($share === null) {
            return response()->json([
                'success' => false,
                'message' => __('Public link could not be created.'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Public link created'),
            'url' => $share->publicUrl(),
            'ttl_days' => $ttlDays,
            'expires_at' => $share->expires_at ? $share->expires_at->format('d.m.Y H:i') : null,
            'expires_label' => $share->expiresLabel(),
        ]);
    }

    public function revokePublicShare(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Требуется авторизация'], 401);
        }

        if (!EseninTextCheckPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable.'),
            ], 503);
        }

        $sessionId = (int) $request->input('session_id', 0);
        if ($sessionId > 0 && EseninTextCheckSessionService::findSessionForUser($sessionId, $userId) === null) {
            return response()->json([
                'success' => false,
                'message' => 'Задание не найдено',
            ], 404);
        }

        EseninTextCheckPublicShare::revokeForSession($userId, $sessionId > 0 ? $sessionId : null);

        return response()->json([
            'success' => true,
            'message' => __('Public link revoked'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function shareStateForSession(int $sessionId, array $result, string $text, string $name): array
    {
        if (!EseninTextCheckPublicShare::tableAvailable()) {
            return [
                'available' => false,
                'url' => null,
                'expires_at' => null,
                'expires_label' => null,
                'ttl_days' => 30,
            ];
        }

        $share = EseninTextCheckPublicShare::activeForSession($sessionId > 0 ? $sessionId : null, (int) Auth::id());
        $currentHash = EseninTextCheckPublicShare::snapshotHash([
            'result' => $result,
            'text' => $text,
        ]);

        if ($share !== null && $share->snapshot_hash !== $currentHash) {
            return [
                'available' => true,
                'url' => null,
                'expires_at' => null,
                'expires_label' => null,
                'ttl_days' => 30,
                'stale' => true,
            ];
        }

        return [
            'available' => true,
            'url' => $share ? $share->publicUrl() : null,
            'expires_at' => $share && $share->expires_at ? $share->expires_at->format('d.m.Y H:i') : null,
            'expires_label' => $share ? $share->expiresLabel() : null,
            'ttl_days' => $share ? $share->ttlDaysFromPayload() : 30,
            'stale' => false,
        ];
    }
}
