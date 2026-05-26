<?php

namespace App\Http\Controllers\Api\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\DomainInformationDemoService;
use App\Support\DemoGuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class DomainInformationDemoController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $cfg = DomainInformationDemoService::config();
        $module = DomainInformationDemoService::MODULE;
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 5);

        $guest = DemoGuestSession::read($request);
        $remainingBefore = DemoGuestSession::remaining($guest['state'], $module, $maxRuns);

        if ($remainingBefore <= 0) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит проверок в демо на сегодня исчерпан. Зарегистрируйтесь — отслеживание доменов по расписанию и оповещения.',
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

        $validated = DomainInformationDemoService::validate([
            'domain' => $body['domain'] ?? '',
        ]);

        if (!($validated['ok'] ?? false)) {
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

        $result = DomainInformationDemoService::check($validated);

        $bump = DemoGuestSession::bump($guest['state'], $module, $maxRuns);
        if (!$bump['allowed']) {
            return $this->jsonError(
                429,
                [
                    'error' => 'rate_limit',
                    'message' => 'Лимит проверок в демо на сегодня исчерпан.',
                    'remaining' => 0,
                ],
                $guest['guestId'],
                $bump['nextState'],
                $guest['isNewGuest']
            );
        }

        $payload = DomainInformationDemoService::buildResponse($result, $bump['remaining'], $guest['guestId']);

        return $this->attachCookies(
            response()->json($payload),
            DemoGuestSession::cookies($guest['guestId'], $bump['nextState'], $guest['isNewGuest'])
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
