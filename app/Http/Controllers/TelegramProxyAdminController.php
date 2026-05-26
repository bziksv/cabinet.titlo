<?php

namespace App\Http\Controllers;

use App\Services\TelegramBacklinkTestService;
use App\Services\TelegramBotService;
use App\Services\TelegramConnectivityService;
use App\Support\TelegramProxyDebugLog;
use App\Support\TelegramProxyRegistry;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class TelegramProxyAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(TelegramConnectivityService $connectivity): View
    {
        $user = Auth::user();
        $brokenLinksCount = 0;

        if ($user && User::isUserAdmin()) {
            $projectIds = \App\ProjectTracking::where('user_id', $user->id)->pluck('id');
            if ($projectIds->isNotEmpty()) {
                $brokenLinksCount = \App\LinkTracking::whereIn('project_tracking_id', $projectIds)
                    ->where('broken', true)
                    ->count();
            }
        }

        $modules = collect(config('cabinet-telegram.modules', []))->map(static function (array $module) {
            if (!empty($module['route']) && \Illuminate\Support\Facades\Route::has($module['route'])) {
                $module['url'] = route($module['route']);
                if (!empty($module['route_fragment'])) {
                    $module['url'] .= '#' . $module['route_fragment'];
                }
            } else {
                $module['url'] = null;
            }

            $module['title'] = __($module['title']);

            return $module;
        });

        TelegramProxyRegistry::seedFromEnvIfEmpty();

        return view('admin.telegram-proxy.index', [
            'status' => $connectivity->status(),
            'proxies' => TelegramProxyRegistry::all(),
            'proxyRegistry' => collect(TelegramProxyRegistry::all())->keyBy('id'),
            'modules' => $modules,
            'telegramConnected' => $user ? $user->isTelegramConnected() : false,
            'brokenLinksCount' => $brokenLinksCount,
            'debugLogText' => $this->debugLogForView(),
        ]);
    }

    public function storeProxy(Request $request): RedirectResponse
    {
        $data = $this->validateProxyRequest($request);

        if (!TelegramProxyRegistry::isValidProxyUrl($data['url'])) {
            flash()->overlay(__('Telegram proxy invalid url'), __('Error'))->error();

            return $this->redirectToIndex();
        }

        TelegramProxyRegistry::add(
            $data['label'],
            $data['url'],
            $data['priority'],
            $data['enabled']
        );
        TelegramConnectivityService::forgetSendAttemptOrderCache();
        flash()->overlay(__('Telegram proxy saved'), ' ')->success();

        return $this->redirectToIndex();
    }

    public function updateProxy(Request $request, string $id): RedirectResponse
    {
        if (TelegramProxyRegistry::find($id) === null) {
            flash()->overlay(__('Telegram proxy not found'), __('Error'))->error();

            return $this->redirectToIndex();
        }

        $data = $this->validateProxyRequest($request);

        if (!TelegramProxyRegistry::isValidProxyUrl($data['url'])) {
            flash()->overlay(__('Telegram proxy invalid url'), __('Error'))->error();

            return $this->redirectToIndex();
        }

        TelegramProxyRegistry::update(
            $id,
            $data['label'],
            $data['url'],
            $data['priority'],
            $data['enabled']
        );
        TelegramConnectivityService::forgetSendAttemptOrderCache();
        flash()->overlay(__('Telegram proxy updated'), ' ')->success();

        return $this->redirectToIndex();
    }

    public function destroyProxy(string $id): RedirectResponse
    {
        TelegramProxyRegistry::remove($id);
        TelegramConnectivityService::forgetSendAttemptOrderCache();
        flash()->overlay(__('Telegram proxy removed'), ' ')->success();

        return $this->redirectToIndex();
    }

    public function importFromEnv(): RedirectResponse
    {
        if (!TelegramProxyRegistry::importFromEnv()) {
            flash()->overlay(__('Telegram proxy env empty'), __('Error'))->error();

            return $this->redirectToIndex();
        }

        TelegramConnectivityService::forgetSendAttemptOrderCache();
        flash()->overlay(__('Telegram proxy imported env'), ' ')->success();

        return $this->redirectToIndex();
    }

    public function refreshStatus(TelegramConnectivityService $connectivity): RedirectResponse
    {
        TelegramConnectivityService::forgetSendAttemptOrderCache();
        TelegramProxyDebugLog::begin('refresh-status');
        TelegramProxyDebugLog::logUserContext(Auth::user());
        TelegramProxyDebugLog::logTelegramConfig();
        TelegramProxyDebugLog::logConnectivity($connectivity);

        return $this->redirectToIndex()
            ->with('telegram_proxy_status', $connectivity->status());
    }

    public function clearDebugLog(): RedirectResponse
    {
        TelegramProxyDebugLog::clear();

        return Redirect::route('admin.telegram-proxy.index');
    }

    public function testNotify(Request $request, TelegramConnectivityService $connectivity): RedirectResponse
    {
        TelegramConnectivityService::forgetSendAttemptOrderCache();
        TelegramProxyDebugLog::begin('test-notify');
        /** @var User $user */
        $user = Auth::user();
        TelegramProxyDebugLog::logUserContext($user);
        TelegramProxyDebugLog::logTelegramConfig();
        TelegramProxyDebugLog::logConnectivity($connectivity);

        if (!$user->isTelegramConnected()) {
            TelegramProxyDebugLog::error('Прерывание: Telegram не подключён в профиле');
            flash()->overlay(__('Telegram proxy admin no bot'), __('Error'))->error();

            return $this->redirectToIndex();
        }

        if (empty(config('app.telegram_bot_token'))) {
            TelegramProxyDebugLog::error('Прерывание: TELEGRAM_BOT_TOKEN пуст');
            flash()->overlay(__('Telegram proxy admin no token'), __('Error'))->error();

            return $this->redirectToIndex();
        }

        try {
            $sent = (new TelegramBotService((int) $user->chat_id))->sendMsg(
                __('Проверка получения уведомлений пройдена!')
            );
        } catch (\Throwable $e) {
            TelegramProxyDebugLog::logSendResult(false, $e->getMessage(), TelegramBotService::$lastSendDiagnostics);
            TelegramProxyDebugLog::error('exception', ['message' => $e->getMessage()]);
            flash()->overlay(
                TelegramBotService::$lastError ?: $e->getMessage(),
                __('Error')
            )->error();

            return $this->redirectToIndex();
        }

        TelegramProxyDebugLog::logSendResult(
            (bool) $sent,
            TelegramBotService::$lastError,
            TelegramBotService::$lastSendDiagnostics
        );

        if (empty($sent)) {
            flash()->overlay(TelegramBotService::$lastError ?: __('Unknown error'), __('Error'))->error();
        } else {
            flash()->overlay(__('Telegram proxy admin test sent'), ' ')->success();
        }

        return $this->redirectToIndex();
    }

    public function testBacklink(TelegramBacklinkTestService $tester, TelegramConnectivityService $connectivity): RedirectResponse
    {
        if (!User::isUserAdmin()) {
            abort(403);
        }

        TelegramProxyDebugLog::begin('test-backlink');
        /** @var User $user */
        $user = Auth::user();
        TelegramProxyDebugLog::logUserContext($user);
        TelegramProxyDebugLog::logTelegramConfig();
        TelegramProxyDebugLog::logConnectivity($connectivity);

        $result = $tester->runForUser($user);
        TelegramProxyDebugLog::info('backlink.test.result', $result);

        if ($result['error'] === 'no_telegram') {
            flash()->overlay(__('Backlink test telegram no bot'), __('Error'))->error();

            return Redirect::to(route('profile.index') . '#telegram');
        }

        if ($result['error'] === 'no_projects') {
            flash()->overlay(__('Backlink test telegram no projects'), ' ')->warning();

            return $this->redirectToIndex();
        }

        if ($result['error'] === 'no_broken') {
            flash()->overlay(__('Backlink test telegram no broken'), ' ')->info();

            return $this->redirectToIndex();
        }

        if ($result['error'] === 'no_token') {
            flash()->overlay(__('Backlink test telegram no token'), __('Error'))->error();

            return $this->redirectToIndex();
        }

        if ($result['ok'] > 0 && $result['fail'] === 0) {
            flash()->overlay(
                __('Backlink test telegram sent', ['count' => $result['ok'], 'skipped' => $result['skipped']]),
                ' '
            )->success();
        } elseif ($result['ok'] > 0) {
            flash()->overlay(
                __('Backlink test telegram partial', [
                    'ok' => $result['ok'],
                    'fail' => $result['fail'],
                    'error' => $result['error'],
                ]),
                ' '
            )->warning();
        } else {
            flash()->overlay(
                __('Backlink test telegram failed', ['error' => $result['error'] ?: __('Unknown error')]),
                __('Error')
            )->error();
        }

        return $this->redirectToIndex();
    }

    private function debugLogForView(): string
    {
        $flashed = session('telegram_proxy_debug_log');
        if (is_string($flashed) && $flashed !== '') {
            return $flashed;
        }

        return TelegramProxyDebugLog::formatForCopy();
    }

    private function redirectToIndex(): RedirectResponse
    {
        return Redirect::route('admin.telegram-proxy.index')
            ->with('telegram_proxy_debug_log', TelegramProxyDebugLog::formatForCopy());
    }

    /**
     * @return array{label: string, url: string, priority: int, enabled: bool}
     */
    private function validateProxyRequest(Request $request): array
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'url' => 'required|string|max:500',
            'priority' => 'nullable|integer|min:0|max:999',
            'enabled' => 'nullable|boolean',
        ]);

        return [
            'label' => (string) $data['label'],
            'url' => trim((string) $data['url']),
            'priority' => (int) ($data['priority'] ?? 50),
            'enabled' => $request->boolean('enabled', false),
        ];
    }
}
