<?php

namespace App\Http\Controllers;

use App\Jobs\Relevance\RelevanceThoughAnalysisQueue;
use App\ProjectRelevanceHistory;
use App\ProjectRelevanceThough;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelevanceThoughController extends Controller
{
    public int $slice = 10;

    public function show(ProjectRelevanceThough $though)
    {
        $project = ProjectRelevanceHistory::query()
            ->select(['id', 'name', 'user_id'])
            ->find($though->project_relevance_history_id);
        $admin = User::isUserAdmin();

        if ($though->result === null || $though->result === '') {
            return response()->view('relevance-analysis.though.pending', [
                'though' => $though,
                'project' => $project,
                'admin' => $admin,
            ], 202);
        }

        $decoded = $this->decodeResult($though->result);
        if ($decoded === null) {
            return response()->view('relevance-analysis.though.pending', [
                'though' => $though,
                'project' => $project,
                'admin' => $admin,
                'error' => true,
            ], 500);
        }

        $allCount = count($decoded);
        $sliceSize = max(1, (int) ceil($allCount / $this->slice));
        $firstSlice = array_slice($decoded, 0, $sliceSize, true);

        $countScanned = 0;
        if ($firstSlice !== []) {
            $firstKey = array_key_first($firstSlice);
            $firstGroup = $firstSlice[$firstKey];
            if (is_array($firstGroup) && isset($firstGroup[$firstKey]['total'])) {
                $countScanned = (int) $firstGroup[$firstKey]['total'];
            }
        }

        // Не тащим весь массив в view/JS — иначе OOM на больших сквозных.
        unset($decoded);

        $though->result = $firstSlice;

        return view('relevance-analysis.though.show', [
            'though' => $though,
            'project' => $project,
            'admin' => $admin,
            'allCount' => $allCount,
            'count' => count($firstSlice),
            'countUniqueScanned' => $countScanned,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getSliceResult(Request $request): JsonResponse
    {
        $record = ProjectRelevanceThough::where('id', '=', $request->id)->first();
        if (!$record || $record->result === null || $record->result === '') {
            return response()->json(['elems' => [], 'message' => 'Too Early'], 409);
        }

        $array = $this->decodeResult($record->result);
        if ($array === null) {
            return response()->json(['elems' => [], 'message' => 'Decode failed'], 500);
        }

        $allCount = count($array);
        $sliceSize = max(1, (int) ceil($allCount / $this->slice));
        $offset = max(0, (int) $request->input('count', 0));
        $sliceArray = array_slice($array, $offset, $sliceSize, true);

        return response()->json([
            'elems' => $sliceArray,
        ]);
    }

    /**
     * Словоформы для раскрытия строки (вместо json_encode всего результата в HTML).
     */
    public function getWordGroup(Request $request): JsonResponse
    {
        $record = ProjectRelevanceThough::where('id', '=', (int) $request->input('id'))->first();
        if (!$record || $record->result === null || $record->result === '') {
            return response()->json(['ok' => false, 'group' => new \stdClass()], 409);
        }

        $word = trim((string) $request->input('word', ''));
        if ($word === '') {
            return response()->json(['ok' => false, 'group' => new \stdClass()], 422);
        }

        $array = $this->decodeResult($record->result);
        if ($array === null || !isset($array[$word]) || !is_array($array[$word])) {
            return response()->json(['ok' => true, 'group' => new \stdClass()]);
        }

        return response()->json([
            'ok' => true,
            'group' => $array[$word],
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function startThroughAnalyse(Request $request): JsonResponse
    {
        HistoryRelevanceController::checkAccess($request);
        $items = HistoryRelevanceController::getUniqueScanned($request->id);
        if (count($items) == 0) {
            return response()->json([
                'code' => 415,
                'message' => 'Не удалось получить требуемые данные, возможно вам стоит перезапустить анализ проекта'
            ]);
        }

        $though = ProjectRelevanceThough::firstOrNew([
            'project_relevance_history_id' => $request->id,
        ]);

        if (isset($though->id) && $though->state == 0) {
            return response()->json([
                'success' => false,
                'code' => 415,
                'message' => 'Сквозной анализ уже запущен',
            ]);
        }

        $though->state = 0;
        $though->stage = 1;

        $though->save();

        dispatch(new RelevanceThoughAnalysisQueue([
            'items' => $items->toArray(),
            'mainId' => $request->id,
            'thoughId' => $though->id,
            'countRecords' => count($items),
            'stage' => 1,
        ]));

        return response()->json([
            'success' => false,
            'code' => 200,
            'message' => 'Сквозной анализ успешно добавлен в очередь',
        ]);

    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeResult(string $payload): ?array
    {
        $previous = ini_get('memory_limit');
        // Распаковка крупных сквозных (десятки МБ) на дефолтных 128M падает.
        @ini_set('memory_limit', '512M');

        try {
            $binary = base64_decode($payload, true);
            if ($binary === false) {
                return null;
            }
            $json = @gzuncompress($binary);
            if ($json === false) {
                return null;
            }
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : null;
        } finally {
            if (is_string($previous) && $previous !== '') {
                @ini_set('memory_limit', $previous);
            }
        }
    }
}
