<?php

namespace App\Http\Controllers;

use App\ProjectRelevanceHistory;
use App\Relevance;
use App\RelevanceAnalysisConfig;
use App\RelevanceHistory;
use App\RelevanceHistoryResult;
use App\RelevancePublicShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RelevancePublicShareController extends Controller
{
    public function showProject(string $token): View
    {
        $share = $this->resolveShare($token);
        $project = $share->project()->firstOrFail();

        $stories = $project->stories()->get([
            'id',
            'phrase',
            'main_link',
            'region',
            'last_check',
            'points',
            'position',
            'coverage',
            'coverage_tf',
            'density',
            'width',
            'comment',
            'state',
        ]);

        return view('relevance-analysis.sharing.public-project', [
            'share' => $share,
            'project' => $project,
            'stories' => $stories,
            'publicShareToken' => $token,
        ]);
    }

    public function showHistory(string $token, int $id)
    {
        $share = $this->resolveShare($token);
        $object = RelevanceHistory::with('projectRelevanceHistory:id,user_id,name')
            ->where('id', $id)
            ->firstOrFail();

        if ((int) $object->project_relevance_history_id !== (int) $share->project_id) {
            abort(403, __("You don't have access to this object"));
        }

        $object->request = json_decode($object->request, true);
        $viewOnlyAccess = (object) ['access' => 1];

        return view('relevance-analysis.show-history', [
            'admin' => false,
            'id' => $id,
            'object' => $object,
            'access' => $viewOnlyAccess,
            'publicShareToken' => $token,
            'publicShareExpires' => $share->expires_at->format('d.m.Y H:i'),
        ]);
    }

    public function getDetails(string $token, Request $request): JsonResponse
    {
        $share = $this->resolveShare($token);
        $historyRow = RelevanceHistory::findOrFail((int) $request->input('id'));

        if ((int) $historyRow->project_relevance_history_id !== (int) $share->project_id) {
            return response()->json([
                'code' => 415,
                'message' => __("You don't have access to this object"),
            ]);
        }

        $part = (string) $request->input('part', 'full');

        try {
            $history = RelevanceHistoryResult::where('project_id', '=', $historyRow->id)
                ->latest('updated_at')
                ->first();

            if ($history === null) {
                return response()->json([
                    'code' => 415,
                    'message' => __('The data was lost'),
                ]);
            }

            if (!$history->compressed) {
                foreach ($history->getOriginal() as $key => $item) {
                    if ($key != 'id' && $key != 'project_id' && $key != 'created_at' && $key != 'updated_at' && $key != 'compressed' && $key != 'cleaning' && $key != 'hash') {
                        if (is_string($item) && $item !== '' && preg_match('/^[A-Za-z0-9+\/=]{32,}$/', $item) && @gzuncompress(base64_decode($item, true) ?: '') !== false) {
                            continue;
                        }
                        if ($item === null || $item === '') {
                            continue;
                        }
                        $history[$key] = base64_encode(gzcompress($item, 9));
                    }
                }

                $history->compressed = true;
                $history->save();

                $history = RelevanceHistoryResult::where('project_id', '=', $historyRow->id)
                    ->latest('updated_at')
                    ->first();
            }

            $history = Relevance::uncompress($history);
            $history = Relevance::historyDetailsPart($history, $part);
        } catch (Throwable $exception) {
            return response()->json([
                'code' => 415,
                'message' => __('The data was lost'),
            ]);
        }

        $response = [
            'code' => 200,
            'history' => $history,
        ];

        if ($part === 'full' || $part === 'meta') {
            $response['config'] = RelevanceAnalysisConfig::first();
        }

        return response()->json($response);
    }

    protected function resolveShare(string $token): RelevancePublicShare
    {
        $share = RelevancePublicShare::where('token', $token)->first();

        if ($share === null || !$share->isActive()) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        return $share;
    }
}
