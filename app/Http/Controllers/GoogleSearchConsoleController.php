<?php

namespace App\Http\Controllers;

use App\GoogleSearchConsoleDomainProperty;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleService;
use App\Support\HomeUserSites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleSearchConsoleController extends Controller
{
    /** @var GoogleSearchConsoleService */
    private $gsc;

    public function __construct(GoogleSearchConsoleService $gsc)
    {
        $this->gsc = $gsc;
    }

    public function connect(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return redirect('/login');
        }

        if (!$this->gsc->isConfigured()) {
            return redirect()
                ->to($this->safeReturn($request->input('return')))
                ->with('error', __('Google Search Console is not configured'));
        }

        $domain = HomeUserSites::normalizeDomain((string) $request->input('domain', ''));
        $url = $this->gsc->buildAuthorizeUrl($userId, [
            'domain' => $domain,
            'return' => $this->safeReturn($request->input('return')),
        ]);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $this->gsc->decodeState((string) $request->input('state', ''));
        $userId = (int) Auth::id();
        if ($userId < 1) {
            return redirect('/login');
        }

        $return = $this->safeReturn($state['return'] ?? null);
        if (!$state || (int) $state['uid'] !== $userId) {
            return redirect($return)->with('error', __('Google Search Console authorization failed'));
        }

        if ($request->filled('error')) {
            return redirect($return)->with('error', __('Google Search Console authorization cancelled'));
        }

        $code = (string) $request->input('code', '');
        if ($code === '') {
            return redirect($return)->with('error', __('Google Search Console authorization failed'));
        }

        $result = $this->gsc->handleCallback($userId, $code);
        if (empty($result['ok'])) {
            return redirect($return)->with('error', $result['message'] ?? __('Google Search Console authorization failed'));
        }

        $domain = HomeUserSites::normalizeDomain((string) ($state['domain'] ?? ''));
        $sep = strpos($return, '?') === false ? '?' : '&';
        $target = $return . $sep . http_build_query([
            'gsc_picker' => 1,
            'gsc_domain' => $domain,
        ]);

        return redirect()->to($target)->with('success', __('Google Search Console connected'));
    }

    public function status(): JsonResponse
    {
        $userId = (int) Auth::id();

        return response()->json([
            'ok' => true,
            'configured' => $this->gsc->isConfigured(),
            'connected' => $this->gsc->isConnected($userId),
            'google_email' => optional(\App\GoogleSearchConsoleUserToken::query()->find($userId))->google_email,
        ]);
    }

    public function properties(): JsonResponse
    {
        $userId = (int) Auth::id();
        if (!$this->gsc->isConnected($userId)) {
            return response()->json([
                'ok' => false,
                'need_auth' => true,
                'message' => __('Connect Google Search Console first'),
            ], 401);
        }

        try {
            $properties = $this->gsc->listProperties($userId);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => __('Could not load GSC properties'),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'properties' => $properties,
            // Совместимость с UI Вебмастера (hosts)
            'hosts' => $properties,
        ]);
    }

    public function bind(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $domain = (string) $request->input('domain', '');
        $propertyId = (string) ($request->input('property_id') ?: $request->input('host_id', ''));

        $result = $this->gsc->bindProperty($userId, $domain, $propertyId);
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
        $ok = $this->gsc->unbindProperty($userId, $domain);

        HomeUserSites::forgetSitesCache($userId);

        return response()->json([
            'ok' => $ok,
            'domain' => HomeUserSites::normalizeDomain($domain),
        ]);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();
        $this->gsc->disconnect($userId);

        return redirect()
            ->to($this->safeReturn($request->input('return')))
            ->with('success', __('Google Search Console disconnected'));
    }

    public function binding(Request $request): JsonResponse
    {
        $userId = (int) Auth::id();
        $domain = HomeUserSites::normalizeDomain((string) $request->input('domain', ''));
        if ($domain === '') {
            return response()->json(['ok' => false, 'message' => __('Invalid domain')], 422);
        }

        $row = GoogleSearchConsoleDomainProperty::tableReady()
            ? GoogleSearchConsoleDomainProperty::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->first()
            : null;

        return response()->json([
            'ok' => true,
            'connected' => $this->gsc->isConnected($userId),
            'configured' => $this->gsc->isConfigured(),
            'binding' => $row ? [
                'domain' => $row->domain,
                'property_id' => (string) $row->property_id,
                'property_url' => (string) $row->property_url,
                'verified' => (bool) $row->verified,
                // aliases for shared UI with Webmaster picker
                'host_id' => (string) $row->property_id,
                'host_url' => (string) $row->property_url,
            ] : null,
        ]);
    }

    private function safeReturn($url): string
    {
        $fallback = route('home');
        if (!is_string($url) || $url === '') {
            return $fallback;
        }
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return url($url);
        }
        $app = rtrim((string) config('app.url'), '/');
        if ($app !== '' && strpos($url, $app) === 0) {
            return $url;
        }

        return $fallback;
    }
}
