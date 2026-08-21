<?php

namespace App\Http\Middleware;

use App\Support\OutagePage;
use Closure;

/**
 * Если выставлен флаг outage — сразу отдать статическую заглушку (без запросов к БД).
 */
class ServeOutagePage
{
    public function handle($request, Closure $next)
    {
        // Local: не блокируем кабинет флагом outage (его мог выставить gone away / healthcheck).
        if (!app()->environment('local') && OutagePage::isEnabled()) {
            return OutagePage::response();
        }

        return $next($request);
    }
}
