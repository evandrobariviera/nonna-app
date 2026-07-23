<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('client_ad_accounts', function (Blueprint $table) {
            // adicao_necessaria | aguardando_pagto | creditos_ativos | standby
            // mesmos 4 status da lista "Orçamentos" no ClickUp — o gestor
            // controla manualmente, não é derivado do saldo.
            $table->string('budget_status', 30)->default('adicao_necessaria')->after('last_billing_sent_at');
            $table->foreignId('responsible_user_id')->nullable()->after('budget_status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_ad_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn('budget_status');
        });
    }
};
