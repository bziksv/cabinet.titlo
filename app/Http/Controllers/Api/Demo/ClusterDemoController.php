<?php

namespace App\Http\Controllers\Api\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\ClusterDemoService;
use App\Support\DemoGuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class ClusterDemoController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $cfg = ClusterDemoService::config();
        $module = ClusterDemoService::MODULE;
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 2);

        $guest = DemoGuestSession::read($request);
        $remainingBefore = DemoGuestSession::remaining($guest['state'], $module, $maxRuns);

        if ($remainingBefore <= 0) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит демо на сегодня исчерпан. Зарегистрируйтесь для полного кластеризатора.',
                    'remaining' => 0,
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        $body = $request->json()->all();
        if (!is_array($body)) {
            $body = $request->all();
        }

        $validated = ClusterDemoService::validateRun($body);
        if (!$validated['ok']) {
            return $this->jsonError(
                $validated['status'],
                [
                    'error' => $validated['error'],
                    'message' => $validated['message'],
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        $started = ClusterDemoService::start($validated['payload'], $guest['guestId']);
        if (!$started['ok']) {
            return $this->jsonError(
                $started['status'],
                [
                    'error' => $started['error'],
                    'message' => $started['message'],
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        $bump = DemoGuestSession::bump($guest['state'], $module, $maxRuns);
        if (!$bump['allowed']) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит демо на сегодня исчерпан.',
                    'remaining' => 0,
                ],
                $guest['guestId'],
                $bump['nextState'],
                $guest['isNewGuest']
            );
        }

        $payload = ClusterDemoService::buildRunResponse($started, $bump['remaining'], $guest['guestId']);

        return $this->attachCookies(
            response()->json($payload),
            DemoGuestSession::cookies($guest['guestId'], $bump['nextState'], $guest['isNewGuest'])
        );
    }

    public function poll(Request $request): JsonResponse
    {
        $cfg = ClusterDemoService::config();
        $module = ClusterDemoService::MODULE;
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 2);

        $guest = DemoGuestSession::read($request);
        $remaining = DemoGuestSession::remaining($guest['state'], $module, $maxRuns);

        $body = $request->json()->all();
        if (!is_array($body)) {
            $body = $request->all();
        }

        $progressId = (string) ($body['progress_id'] ?? '');
        $polled = ClusterDemoService::poll($progressId, $guest['guestId']);

        if (!$polled['ok']) {
            return $this->jsonError(
                $polled['status'],
                [
                    'error' => $polled['error'],
                    'message' => $polled['message'],
                ],
                $guest['guestId'],
                $guest['state'],
                $guest['isNewGuest']
            );
        }

        if ($polled['status'] === 'complete') {
            $payload = ClusterDemoService::buildPollResponse(
                'complete',
                $remaining,
                $guest['guestId'],
                $polled['result'] ?? null
            );
        } else {
            $payload = ClusterDemoService::buildPollResponse(
                'pending',
                $remaining,
                $guest['guestId'],
                null,
                $polled['progress'] ?? null
            );
        }

        return $this->attachCookies(
            response()->json($payload),
            DemoGuestSession::cookies($guest['guestId'], $guest['state'], $guest['isNewGuest'])
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, array{count: int, day: string}> $runState
     */
    private function jsonError(int $status, array $body, string $guestId, array $runState, bool $setGuest): JsonResponse
    {
        return $this->attachCookies(
            response()->json($body, $status),
            DemoGuestSession::cookies($guestId, $runState, $setGuest)
        );
    }

    /**
     * @param Cookie[] $cookies
     */
    private function attachCookies(JsonResponse $response, array $cookies): JsonResponse
    {
        foreach ($cookies as $cookie) {
            $response = $response->withCookie($cookie);
        }

        return $response;
    }
}
