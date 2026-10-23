<?php

namespace App\Bootstrap;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\HandleExceptions as BaseHandleExceptions;

/**
 * Laravel 6 ставит error_reporting(-1); на PHP 8.1+ deprecations из vendor
 * превращаются в ErrorException. Для локалки на 8.5 глушим E_DEPRECATED.
 */
class HandleExceptions extends BaseHandleExceptions
{
    public function bootstrap(Application $app)
    {
        parent::bootstrap($app);

        if (PHP_VERSION_ID >= 80100) {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        }
    }

    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        if (PHP_VERSION_ID >= 80100 && ($level === E_DEPRECATED || $level === E_USER_DEPRECATED)) {
            return;
        }

        parent::handleError($level, $message, $file, $line, $context);
    }
}
