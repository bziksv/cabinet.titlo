<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Отложенный старт ручного съёма позиций / частотности.
 */
class MonitoringDeferredStart
{
    /**
     * @return array{ok: bool, start_at: ?Carbon, error: ?string}
     */
    public static function resolveFromRequest(Request $request): array
    {
        $when = strtolower(trim((string) $request->input('start_when', 'now')));
        if ($when === '' || $when === 'now') {
            return ['ok' => true, 'start_at' => null, 'error' => null];
        }

        if (!in_array($when, ['today', 'tomorrow'], true)) {
            return [
                'ok' => false,
                'start_at' => null,
                'error' => __('Monitoring deferred start invalid when'),
            ];
        }

        $time = trim((string) $request->input('start_time', ''));
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            return [
                'ok' => false,
                'start_at' => null,
                'error' => __('Monitoring deferred start invalid time'),
            ];
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $now = Carbon::now(config('app.timezone'));
        $startAt = $now->copy()->startOfDay()->setTime($hour, $minute, 0);
        if ($when === 'tomorrow') {
            $startAt->addDay();
        }

        if ($startAt->lte($now)) {
            return [
                'ok' => false,
                'start_at' => null,
                'error' => __('Monitoring deferred start time passed'),
            ];
        }

        // Не дальше конца завтрашнего дня.
        if ($startAt->gt($now->copy()->addDay()->endOfDay())) {
            return [
                'ok' => false,
                'start_at' => null,
                'error' => __('Monitoring deferred start too far'),
            ];
        }

        return ['ok' => true, 'start_at' => $startAt, 'error' => null];
    }
}
