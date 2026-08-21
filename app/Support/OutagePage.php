<?php

namespace App\Support;

use Illuminate\Http\Response;
use Throwable;

/**
 * Статическая страница техработ (public/outage.html) при недоступной БД.
 * На prod дополнительно отдаёт nginx по флагу storage/app/outage/ENABLED.
 */
class OutagePage
{
    public static function flagPath(): string
    {
        return storage_path('app/outage/ENABLED');
    }

    public static function htmlPath(): string
    {
        return public_path('outage.html');
    }

    public static function isEnabled(): bool
    {
        return is_file(self::flagPath());
    }

    public static function enable(string $reason = ''): void
    {
        // Локально один «gone away» от тяжёлого запроса не должен блокировать весь кабинет.
        if (function_exists('app') && app()->environment('local')) {
            return;
        }

        $dir = dirname(self::flagPath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            self::flagPath(),
            gmdate('c') . "\n" . trim($reason) . "\n"
        );
    }

    public static function html(): string
    {
        $path = self::htmlPath();
        if (is_readable($path)) {
            return (string) file_get_contents($path);
        }

        return '<!DOCTYPE html><html lang="ru"><meta charset="utf-8">'
            . '<title>Технические работы — Titlo</title>'
            . '<body style="font-family:sans-serif;padding:2rem">'
            . '<h1>Кабинет временно недоступен</h1>'
            . '<p>Проблемы с сервером базы данных. Попробуйте позже.</p>'
            . '<p><a href="mailto:info@titlo.ru">info@titlo.ru</a></p>'
            . '</body></html>';
    }

    public static function response(): Response
    {
        return response(self::html(), 503, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Retry-After' => '300',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function isDatabaseUnavailable(Throwable $e): bool
    {
        $cur = $e;
        for ($i = 0; $i < 8 && $cur; $i++) {
            $msg = strtolower($cur->getMessage());
            $code = method_exists($cur, 'getCode') ? (string) $cur->getCode() : '';
            if (
                strpos($msg, 'mysql server has gone away') !== false
                || strpos($msg, 'connection timed out') !== false
                || strpos($msg, 'connection refused') !== false
                || strpos($msg, "can't connect to mysql") !== false
                || strpos($msg, 'could not find driver') !== false
                || strpos($msg, 'server has gone away') !== false
                || strpos($msg, 'no connection could be made') !== false
                || strpos($msg, 'sqlstate[hy000] [2002]') !== false
                || strpos($msg, 'sqlstate[hy000] [2006]') !== false
                || $code === '2002'
                || $code === '2006'
            ) {
                return true;
            }
            $cur = $cur->getPrevious();
        }

        return false;
    }
}
