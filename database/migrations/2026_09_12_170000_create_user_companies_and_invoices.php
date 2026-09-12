<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserCompaniesAndInvoices extends Migration
{
    public function up()
    {
        Schema::create('user_companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('inn', 12);
            $table->string('kpp', 9)->nullable();
            $table->string('legal_address', 500);
            $table->string('postal_address', 500)->nullable();
            $table->string('email');
            $table->string('phone', 64)->nullable();
            $table->unsignedInteger('balance')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'inn']);
            $table->index(['user_id', 'name']);
        });

        Schema::create('company_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_company_id');
            $table->string('number', 64);
            $table->unsignedInteger('amount');
            $table->string('status', 16)->default('pending');
            $table->string('service_title', 255)->default('Пополнение баланса в сервисе Титло');
            $table->json('payer_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('credited_by_admin_id')->nullable();
            $table->string('act_number', 64)->nullable();
            $table->timestamp('act_issued_at')->nullable();
            $table->string('invoice_pdf_path', 500)->nullable();
            $table->string('act_pdf_path', 500)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_company_id')->references('id')->on('user_companies')->onDelete('cascade');
            $table->foreign('credited_by_admin_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['user_id', 'number']);
            $table->index(['user_company_id', 'status']);
            $table->index(['user_id', 'issued_at']);
        });

        Schema::table('balances', function (Blueprint $table) {
            $table->unsignedBigInteger('user_company_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('company_invoice_id')->nullable()->after('user_company_id');

            $table->foreign('user_company_id')->references('id')->on('user_companies')->onDelete('set null');
            $table->foreign('company_invoice_id')->references('id')->on('company_invoices')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('balances', function (Blueprint $table) {
            $table->dropForeign(['user_company_id']);
            $table->dropForeign(['company_invoice_id']);
            $table->dropColumn(['user_company_id', 'company_invoice_id']);
        });

        Schema::dropIfExists('company_invoices');
        Schema::dropIfExists('user_companies');
    }
}
