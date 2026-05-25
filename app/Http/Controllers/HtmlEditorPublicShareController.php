<?php

namespace App\Http\Controllers;

use App\HtmlEditorPublicShare;
use Illuminate\View\View;

class HtmlEditorPublicShareController extends Controller
{
    public function show(string $token): View
    {
        if (!HtmlEditorPublicShare::tableAvailable()) {
            abort(503, __('Public sharing is temporarily unavailable.'));
        }

        $share = HtmlEditorPublicShare::where('token', $token)->first();

        if ($share === null || !$share->isActive()) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        $payload = $share->decodedPayload();
        $html = (string) ($payload['html'] ?? '');
        if (strip_tags($html) === '') {
            abort(404, __('This public link has expired or does not exist.'));
        }

        return view('html-editor.public-share', [
            'share' => $share,
            'html' => $html,
            'shareMeta' => $payload['meta'] ?? [],
        ]);
    }
}
