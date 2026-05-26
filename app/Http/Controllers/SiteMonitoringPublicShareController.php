<?php

namespace App\Http\Controllers;

use App\SiteMonitoringPublicShare;
use Illuminate\View\View;

class SiteMonitoringPublicShareController extends Controller
{
    public function show(string $token): View
    {
        if (!SiteMonitoringPublicShare::tableAvailable()) {
            abort(503, __('Public sharing is temporarily unavailable.'));
        }

        $share = SiteMonitoringPublicShare::where('token', $token)->first();

        if ($share === null || !$share->isActive()) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        $payload = $share->decodedPayload();
        $report = $payload['report'] ?? [];
        if ($report === []) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        return view('site-monitoring.public-share', [
            'share' => $share,
            'report' => $report,
            'shareMeta' => $payload['meta'] ?? [],
            'isPublicView' => true,
        ]);
    }
}
