<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Static DB outage page (no Laravel / no MySQL)
|--------------------------------------------------------------------------
|
| Prod: nginx also checks this flag. Local / PHP-FPM: catch here before boot.
|
*/
$outageFlag = __DIR__ . '/../storage/app/outage/ENABLED';
$outageSkipLocal = false;
$envFile = __DIR__ . '/../.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || $envLine[0] === '#') {
            continue;
        }
        if (strpos($envLine, 'APP_ENV=') === 0) {
            $outageSkipLocal = (trim(substr($envLine, 8), " \t\"'") === 'local');
            break;
        }
    }
}
if (!$outageSkipLocal && is_file($outageFlag)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 300');
    header('Cache-Control: no-store');
    $outageHtml = __DIR__ . '/outage.html';
    if (is_readable($outageHtml)) {
        readfile($outageHtml);
    } else {
        echo '<!DOCTYPE html><html lang="ru"><meta charset="utf-8"><title>Технические работы</title>'
            . '<body style="font-family:sans-serif;padding:2rem"><h1>Кабинет временно недоступен</h1>'
            . '<p><a href="mailto:info@titlo.ru">info@titlo.ru</a></p></body></html>';
    }
    exit;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so that we don't have to worry about manual
| loading any of our classes later on. It feels great to relax.
|
*/

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
|
| We need to illuminate PHP development, so let us turn on the lights.
| This bootstraps the framework and gets it ready for use, then it
| will load up this application so that we can run it and send
| the responses back to the browser and delight our users.
|
*/

$app = require_once __DIR__ . '/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request
| through the kernel, and send the associated response back to
| the client's browser allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

try {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $response = $kernel->handle(
        $request = Illuminate\Http\Request::capture()
    );

    $response->send();

    $kernel->terminate($request, $response);

} catch (Throwable $e) {
    \Illuminate\Support\Facades\Log::debug('hard catch', [
        'message' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTrace(),
    ]);
}
