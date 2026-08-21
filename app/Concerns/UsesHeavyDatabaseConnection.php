<?php

namespace App\Concerns;

trait UsesHeavyDatabaseConnection
{
    public function getConnectionName()
    {
        $heavy = config('database.heavy_connection');
        if (is_string($heavy) && $heavy !== '' && config('database.connections.' . $heavy)) {
            return $heavy;
        }

        return $this->connection;
    }
}
