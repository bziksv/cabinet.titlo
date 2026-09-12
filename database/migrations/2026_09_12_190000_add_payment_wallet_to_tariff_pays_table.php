<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentWalletToTariffPaysTable extends Migration
{
    public function up()
    {
        Schema::table('tariff_pays', function (Blueprint $table) {
            $table->unsignedBigInteger('user_company_id')->nullable()->after('sum');
            $table->boolean('auto_renewed')->default(false)->after('user_company_id');

            $table->foreign('user_company_id')
                ->references('id')
                ->on('user_companies')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('tariff_pays', function (Blueprint $table) {
            $table->dropForeign(['user_company_id']);
            $table->dropColumn(['user_company_id', 'auto_renewed']);
        });
    }
}
