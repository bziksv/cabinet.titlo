<?php

namespace App\Classes\Cron;

use App\HtmlEditorPublicShare;
use Carbon\Carbon;

class HtmlEditorPublicSharesDelete
{
    public function __invoke()
    {
        if (!HtmlEditorPublicShare::tableAvailable()) {
            return;
        }

        HtmlEditorPublicShare::where('expires_at', '<', Carbon::now())->delete();

        HtmlEditorPublicShare::whereNotNull('revoked_at')
            ->where('revoked_at', '<', Carbon::now()->subDays(7))
            ->delete();
    }
}
