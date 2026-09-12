<?php

namespace App\Http\Controllers;

use App\Services\YandexWebmaster\YandexWebmasterService;
use App\Support\HomeUserSites;
use App\Support\OauthReturnUrl;
use App\YandexWebmasterDomainHost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class YandexWebmasterController extends Controller
{
    /** @var YandexWebmasterService */
    private $webmaster;

    public function __construct(YandexWebmasterService $webmaster)
    {
        $this->webmaster = $webmaster;
    }

    public function connect(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return redirect('/login');
        }

        if (!$this->webmaster->isConfigured()) {
            return redirect()
                ->to($this->safeReturn($request->input('return')))
                ->with('error', __('Yandex Webmaster is not configured'));
        }

        $domain = HomeUserSites::normalizeDomain((string) $request->input('domain', ''));
        $url = $this->webmaster->buildAuthorizeUrl($userId, [
            'domain' => $domain,
            'return' => $this->safeReturn($request->input('return')),
        ]);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $this->webmaster->decodeState((string) $request->input('state', ''));
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return redirect('/login');
        }

        $return = $this->safeReturn($state['return'] ?? null);
        if (!$state || (int) $state['uid'] !== $userId) {
            return redirect($return)->with('error', __('Yandex Webmaster authorization failed'));
        }

        if ($request->filled('error')) {
            return redirect($return)->with('error', __('Yandex Webmaster authorization cancelled'));
        }

        $code = (string) $request->input('code', '');
        if ($code === '') {
            return redirect($return)->with('error', __('Yandex Webmaster authorization failed'));
        }

        $result = $this->webmaster->handleCallback($userId, $code);
        if (empty($result['ok'])) {
            return redirect($return)->with('error', $result['message'] ?? __('Yandex Webmaster authorization failed'));
        }

        $domain = HomeUserSites::normalizeDomain((string) ($state['domain'] ?? ''));
        $sep = strpos($return, '?') === false ? '?' : '&';
        $target = $return . $sep . http_build_query([
            'webmaster_picker' => 1,
            'webmaster_domain' => $domain,
        ]);

        return redirect()->to($target)->with('success', __('Yandex Webmaster connected'));
    }

    public function status(): JsonResponse
    {
        $userId = (int) Auth::id();

        return response()->json([
            'ok' => true,
            'configured' => $this->webmaster->isConfigured(),
            'connected' => $this->webmaster->isConnected($userId),
            'yandex_login' => optional(\App\YandexWebmasterUserToken::query()->find($userId))->yandex_login,
        ]);
    }

    public function hosts(): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$this->webmaster->isConnected($userId)) {
            return response()->json([
                'ok' => false,
                'need_auth' => true,
                'message' => __('Connect Yandex Webmaster first'),
            ], 401);
        }

        try {
            $hosts = $this->webmaster->listHosts($userId);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => __('Could not load Webmaster hosts'),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'hosts' => $hosts,
        ]);
    }

    public function bind(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $domain = (string) $request->input('domain', '');
        $hostId = (string) $request->input('host_id', '');

        $result = $this->webmaster->bindHost($userId, $domain, $hostId);
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
        $ok = $this->webmaster->unbindHost($userId, $domain);

        HomeUserSites::forgetSitesCache($userId);

        return response()->json([
            'ok' => $ok,
            'domain' => HomeUserSites::normalizeDomain($domain),
        ]);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $this->webmaster->disconnect($userId);

        return redirect()
            ->to($this->safeReturn($request->input('return')))
            ->with('success', __('Yandex Webmaster disconnected'));
    }

    public function binding(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $domain = HomeUserSites::normalizeDomain((string) $request->input('domain', ''));
        if ($domain === '') {
            return response()->json(['ok' => false, 'message' => __('Invalid domain')], 422);
        }

        $row = YandexWebmasterDomainHost::tableReady()
            ? YandexWebmasterDomainHost::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->first()
            : null;

        return response()->json([
            'ok' => true,
            'connected' => $this->webmaster->isConnected($userId),
            'configured' => $this->webmaster->isConfigured(),
            'binding' => $row ? [
                'domain' => $row->domain,
                'host_id' => (string) $row->host_id,
                'host_url' => (string) $row->host_url,
                'verified' => (bool) $row->verified,
            ] : null,
        ]);
    }

    private function safeReturn($url): string
    {
        return OauthReturnUrl::sanitize($url);
    }
}
