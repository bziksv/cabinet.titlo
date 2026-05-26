<?php

namespace App\Http\Controllers;

use App\DomainInformationPublicShare;
use Illuminate\View\View;

class DomainInformationPublicShareController extends Controller
{
    public function show(string $token): View
    {
        if (!DomainInformationPublicShare::tableAvailable()) {
            abort(503, __('Public sharing is temporarily unavailable.'));
        }

        $share = DomainInformationPublicShare::where('token', $token)->first();

        if ($share === null || !$share->isActive()) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        $payload = $share->decodedPayload();
        $report = $payload['report'] ?? [];
        if ($report === []) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        return view('domain-information.public-share', [
            'share' => $share,
            'report' => $report,
            'shareMeta' => $payload['meta'] ?? [],
            'isPublicView' => true,
        ]);
    }
}
