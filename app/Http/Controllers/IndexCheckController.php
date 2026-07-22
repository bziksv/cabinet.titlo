<?php

namespace App\Http\Controllers;

use App\IndexCheckHistory;
use App\Services\IndexCheckService;
use App\Support\DemoCabinet;
use App\Support\IndexCheckLimits;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IndexCheckController extends Controller
{
    /**
     * @return array|Factory|JsonResponse|View
     */
    public function index(Request $request)
    {
        if ($request->boolean('ajax')) {
            return $this->ajaxCheck($request);
        }

        $user = Auth::user();
        $googleDomains = config('cabinet-index-check.google_domains', []);
        $limit = IndexCheckLimits::limitForUser();
        $remaining = IndexCheckLimits::remainingForUser();
        $costPerEngine = IndexCheckService::costPerEngine();
        $histories = [];

        if ($user && ! DemoCabinet::isCurrentUser()) {
            $histories = IndexCheckHistory::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(30)
                ->get(['id', 'url', 'check_yandex', 'check_google', 'result', 'created_at']);
        }

        return view('pages.index-check', compact(
            'googleDomains',
            'limit',
            'remaining',
            'costPerEngine',
            'histories'
        ));
    }

    private function ajaxCheck(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'auth', 'message' => __('Unauthorized')], 401);
        }

        $url = trim((string) $request->input('url', ''));
        $yandex = $request->boolean('yandex', true);
        $google = $request->boolean('google', true);
        $unifyWww = $request->boolean('unify_www', false);

        if ($url === '') {
            return response()->json(['error' => 'validation', 'message' => 'Укажите URL'], 422);
        }

        if (! $yandex && ! $google) {
            return response()->json(['error' => 'validation', 'message' => 'Выберите хотя бы одну поисковую систему'], 422);
        }

        if (DemoCabinet::isCurrentUser()) {
            return response()->json([
                'ok' => true,
                'cost' => 0,
                'remaining' => IndexCheckLimits::remainingForUser(),
                'limit' => IndexCheckLimits::limitForUser(),
                'result' => $this->demoAjaxResult($url, $yandex, $google),
                'history' => null,
            ]);
        }

        $cost = IndexCheckService::checkCost($yandex, $google);
        if (! IndexCheckLimits::canSpend($cost)) {
            $message = IndexCheckLimits::limitMessage() ?: __('Index check limit exhausted');

            return response()->json([
                'error' => 'limit',
                'message' => $message,
                'remaining' => IndexCheckLimits::remainingForUser(),
                'limit' => IndexCheckLimits::limitForUser(),
            ], 403);
        }

        $googleDomain = (string) $request->input('google_domain', 'google.ru');
        $googleDomains = config('cabinet-index-check.google_domains', []);
        $googleLr = $googleDomains[$googleDomain] ?? config('cabinet-index-check.default_google_lr', '213');

        try {
            $result = IndexCheckService::check($url, [
                'yandex' => $yandex,
                'google' => $google,
                'unify_www' => $unifyWww,
                'google_lr' => $googleLr,
                'yandex_lr' => config('cabinet-index-check.default_yandex_lr', '213'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'fetch_failed',
                'message' => 'Не удалось выполнить проверку. Попробуйте позже.',
            ], 502);
        }

        IndexCheckLimits::spend($cost);

        $history = IndexCheckHistory::query()->create([
            'user_id' => Auth::id(),
            'url' => (string) ($result['url'] ?? $url),
            'check_yandex' => $yandex,
            'check_google' => $google,
            'result' => $result,
        ]);
        IndexCheckLimits::pruneHistory();

        return response()->json([
            'ok' => true,
            'cost' => $cost,
            'remaining' => IndexCheckLimits::remainingForUser(),
            'limit' => IndexCheckLimits::limitForUser(),
            'result' => $result,
            'history' => [
                'id' => $history->id,
                'url' => $history->url,
                'created_at' => optional($history->created_at)->toDateTimeString(),
            ],
        ]);
    }

    /**
     * @return array{url: string, yandex: ?array, google: ?array}
     */
    private function demoAjaxResult(string $url, bool $yandex, bool $google): array
    {
        $showcase = DemoCabinet::indexCheckShowcase();
        $items = is_array($showcase['items'] ?? null) ? $showcase['items'] : [];
        $needle = mb_strtolower(rtrim($url, '/'));

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $candidate = mb_strtolower(rtrim((string) ($row['url'] ?? ''), '/'));
            if ($candidate !== '' && ($candidate === $needle || strpos($needle, $candidate) !== false || strpos($candidate, $needle) !== false)) {
                return [
                    'url' => (string) ($row['url'] ?? $url),
                    'yandex' => $yandex ? ($row['yandex'] ?? null) : null,
                    'google' => $google ? ($row['google'] ?? null) : null,
                ];
            }
        }

        return [
            'url' => $url,
            'yandex' => $yandex ? [
                'indexed' => true,
                'results_count' => 1,
                'matched_url' => $url,
                'title' => 'Демо: страница в индексе',
                'snippet' => 'Пример сниппета из выдачи для анализа в демо-кабинете.',
                'error' => null,
            ] : null,
            'google' => $google ? [
                'indexed' => false,
                'results_count' => 0,
                'matched_url' => null,
                'title' => null,
                'snippet' => null,
                'error' => null,
            ] : null,
        ];
    }
}
