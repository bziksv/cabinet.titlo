<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Дни съёма позиций по региону — лёгкая таблица для колонок дат в /table.
 * Без полного DISTINCT DATE по monitoring_positions (~1 с cold).
 */
class CreateMonitoringPositionDatesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('monitoring_position_dates')) {
            Schema::create('monitoring_position_dates', function (Blueprint $table) {
                $table->unsignedBigInteger('monitoring_searchengine_id');
                $table->date('check_date');
                $table->primary(['monitoring_searchengine_id', 'check_date']);
            });
        }

        $dbName = Schema::getConnection()->getDatabaseName();
        $hasEngineCreated = (int) DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', 'monitoring_positions')
            ->where('index_name', 'mon_pos_engine_created_idx')
            ->count() > 0;

        if (!$hasEngineCreated && Schema::hasTable('monitoring_positions')) {
            Schema::table('monitoring_positions', function (Blueprint $table) {
                $table->index(
                    ['monitoring_searchengine_id', 'created_at'],
                    'mon_pos_engine_created_idx'
                );
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('monitoring_position_dates');

        $dbName = Schema::getConnection()->getDatabaseName();
        $hasEngineCreated = (int) DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', 'monitoring_positions')
            ->where('index_name', 'mon_pos_engine_created_idx')
            ->count() > 0;

        if ($hasEngineCreated) {
            Schema::table('monitoring_positions', function (Blueprint $table) {
                $table->dropIndex('mon_pos_engine_created_idx');
            });
        }
    }
}
