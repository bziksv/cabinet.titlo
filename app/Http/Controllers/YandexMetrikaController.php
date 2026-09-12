<?php

namespace App\Http\Controllers;

use App\Services\YandexMetrika\YandexMetrikaService;
use App\Support\HomeUserSites;
use App\Support\OauthReturnUrl;
use App\YandexMetrikaDomainCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class YandexMetrikaController extends Controller
{
    /** @var YandexMetrikaService */
    private $metrika;

    public function __construct(YandexMetrikaService $metrika)
    {
        $this->metrika = $metrika;
    }

    public function connect(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return redirect('/login');
        }

        if (!$this->metrika->isConfigured()) {
            return redirect()
                ->to($this->safeReturn($request->input('return')))
                ->with('error', __('Yandex Metrika is not configured'));
        }

        $domain = HomeUserSites::normalizeDomain((string) $request->input('domain', ''));
        $url = $this->metrika->buildAuthorizeUrl($userId, [
            'domain' => $domain,
            'return' => $this->safeReturn($request->input('return')),
        ]);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $this->metrika->decodeState((string) $request->input('state', ''));
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return redirect('/login');
        }

        $return = $this->safeReturn($state['return'] ?? null);
        if (!$state || (int) $state['uid'] !== $userId) {
            return redirect($return)->with('error', __('Yandex Metrika authorization failed'));
        }

        if ($request->filled('error')) {
            return redirect($return)->with('error', __('Yandex Metrika authorization cancelled'));
        }

        $code = (string) $request->input('code', '');
        if ($code === '') {
            return redirect($return)->with('error', __('Yandex Metrika authorization failed'));
        }

        $result = $this->metrika->handleCallback($userId, $code);
        if (empty($result['ok'])) {
            return redirect($return)->with('error', $result['message'] ?? __('Yandex Metrika authorization failed'));
        }

        $domain = HomeUserSites::normalizeDomain((string) ($state['domain'] ?? ''));
        $sep = strpos($return, '?') === false ? '?' : '&';
        $target = $return . $sep . http_build_query([
            'metrika_picker' => 1,
            'metrika_domain' => $domain,
        ]);

        return redirect()->to($target)->with('success', __('Yandex Metrika connected'));
    }

    public function status(): JsonResponse
    {
        $userId = (int) Auth::id();

        return response()->json([
            'ok' => true,
            'configured' => $this->metrika->isConfigured(),
            'connected' => $this->metrika->isConnected($userId),
            'yandex_login' => optional(\App\YandexMetrikaUserToken::query()->find($userId))->yandex_login,
        ]);
    }

    public function counters(): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$this->metrika->isConnected($userId)) {
            return response()->json([
                'ok' => false,
                'need_auth' => true,
                'message' => __('Connect Yandex Metrika first'),
            ], 401);
        }

        try {
            $counters = $this->metrika->listCounters($userId);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => __('Could not load Metrika counters'),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'counters' => $counters,
        ]);
    }

    public function bind(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $domain = (string) $request->input('domain', '');
        $counterId = (int) $request->input('counter_id', 0);

        $result = $this->metrika->bindCounter($userId, $domain, $counterId);
        if (empty($result['ok'])) {
            return response()->json($result, 422);
        }

        HomeUserSites::forgetSitesCache($userId);

        return response()->json($result);
    }

    public function unbind(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $domain = (string) $request->input('domain', '');
        $ok = $this->metrika->unbindCounter($userId, $domain);

        HomeUserSites::forgetSitesCache($userId);

        return response()->json([
            'ok' => $ok,
            'domain' => HomeUserSites::normalizeDomain($domain),
        ]);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $this->metrika->disconnect($userId);

        return redirect()
            ->to($this->safeReturn($request->input('return')))
            ->with('success', __('Yandex Metrika disconnected'));
    }

    public function binding(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $domain = HomeUserSites::normalizeDomain((string) $request->input('domain', ''));
        if ($domain === '') {
            return response()->json(['ok' => false, 'message' => __('Invalid domain')], 422);
        }

        $row = YandexMetrikaDomainCounter::tableReady()
            ? YandexMetrikaDomainCounter::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->first()
            : null;

        return response()->json([
            'ok' => true,
            'connected' => $this->metrika->isConnected($userId),
            'configured' => $this->metrika->isConfigured(),
            'binding' => $row ? [
                'domain' => $row->domain,
                'counter_id' => (int) $row->counter_id,
                'counter_name' => (string) $row->counter_name,
                'counter_site' => (string) $row->counter_site,
            ] : null,
        ]);
    }

    private function safeReturn($url): string
    {
        return OauthReturnUrl::sanitize($url);
    }
}
