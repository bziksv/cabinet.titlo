<?php

namespace App\Http\Controllers;

use App\Services\TextUniquenessService;
use App\Support\TextUniquenessLimits;
use App\TextUniquenessHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TextUniquenessController extends Controller
{
    /** Уникальность встроена в /text-analyzer — отдельная страница больше не нужна. */
    public function index(): RedirectResponse
    {
        return redirect()->route('text.analyzer.view');
    }

    public function analyze(Request $request, TextUniquenessService $service): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'auth', 'message' => __('Unauthorized')], 401);
        }

        $mode = $request->input('mode') === 'urls' ? 'urls' : 'internet';
        $params = [
            'mode' => $mode,
            'text' => (string) $request->input('text', ''),
            'urls' => (string) $request->input('urls', ''),
            'engine' => $request->input('engine') === 'google' ? 'google' : 'yandex',
            'yandex_lr' => (string) $request->input('yandex_lr', config('cabinet-text-uniqueness.default_yandex_lr', '213')),
        ];

        try {
            $cost = TextUniquenessService::estimateCost($params);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'validation', 'message' => $e->getMessage()], 422);
        }

        if (! TextUniquenessLimits::canSpend($cost, $user)) {
            return response()->json([
                'error' => 'limit',
                'message' => TextUniquenessLimits::limitMessage($user) ?: __('Text uniqueness limit exhausted'),
                'remaining' => TextUniquenessLimits::remainingForUser($user),
                'limit' => TextUniquenessLimits::limitForUser($user),
            ], 403);
        }

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        try {
            $result = $service->analyze($params);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'fetch_failed',
                'message' => __('Text uniqueness fetch failed'),
            ], 502);
        }

        TextUniquenessLimits::spend((int) $result['cost'], $user);

        $historyId = null;
        $historyWarning = null;
        $save = $request->boolean('save', false);
        if ($save && TextUniquenessLimits::canSaveHistory($user)) {
            $plain = TextUniquenessService::normalizePlain($params['text']);
            $title = mb_substr($plain, 0, 60);
            if (mb_strlen($plain) > 60) {
                $title .= '…';
            }

            $history = TextUniquenessHistory::query()->create([
                'user_id' => $user->id,
                'title' => $title !== '' ? $title : __('Text uniqueness'),
                'mode' => $result['mode'],
                'params' => [
                    'mode' => $mode,
                    'engine' => $params['engine'],
                    'yandex_lr' => $params['yandex_lr'],
                    'urls' => TextUniquenessService::normalizeUrlList($params['urls']),
                    'chars' => $result['chars'] ?? mb_strlen($plain),
                ],
                'results' => $result,
                'uniqueness_pct' => $result['uniqueness_pct'] ?? 0,
                'cost' => $result['cost'],
            ]);
            $historyId = $history->id;
            TextUniquenessLimits::pruneHistory($user);
        }

        return response()->json([
            'ok' => true,
            'result' => $result,
            'cost' => $result['cost'],
            'remaining' => TextUniquenessLimits::remainingForUser($user),
            'limit' => TextUniquenessLimits::limitForUser($user),
            'history_id' => $historyId,
            'history_warning' => $historyWarning,
            'saved_count' => TextUniquenessLimits::savedCount($user),
            'history_limit' => TextUniquenessLimits::historyLimitForUser($user),
            'can_save_history' => TextUniquenessLimits::canSaveHistory($user),
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
            'history_limit' => TextUniquenessLimits::historyLimitForUser($user),
        ]);
    }

    public function estimate(Request $request): JsonResponse
    {
        $params = [
            'mode' => $request->input('mode') === 'urls' ? 'urls' : 'internet',
            'text' => (string) $request->input('text', ''),
            'urls' => (string) $request->input('urls', ''),
        ];

        return response()->json([
            'ok' => true,
            'cost' => TextUniquenessService::estimateCost($params),
        ]);
    }
}
