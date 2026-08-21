<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Local/dev: heavy tables live on prod (DB_HEAVY_CONNECTION), everything else on default.
 * Prod: DB_HEAVY_CONNECTION empty → default connection.
 */
class HeavyDb
{
    public static function connectionName(): string
    {
        $name = config('database.heavy_connection');
        if (is_string($name) && $name !== '' && config('database.connections.' . $name)) {
            return $name;
        }

        return (string) config('database.default');
    }

    public static function connection(): Connection
    {
        return DB::connection(static::connectionName());
    }

    /**
     * @param  string|\Illuminate\Database\Query\Expression  $table
     */
    public static function table($table): Builder
    {
        return static::connection()->table($table);
    }
}
