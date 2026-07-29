<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Padrão da conta (herdado por toda campanha dela, salvo override).
        Schema::connection('pgsql')->table('client_ad_accounts', function (Blueprint $table) {
            $table->decimal('target_cost_per_result', 12, 2)->nullable()->after('budget_status');
            $table->decimal('target_roas', 6, 2)->nullable()->after('target_cost_per_result');
        });

        // Override específico da campanha — vazio = usa o padrão da conta.
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->decimal('target_cost_per_result', 12, 2)->nullable()->after('optimization_tier');
            $table->decimal('target_roas', 6, 2)->nullable()->after('target_cost_per_result');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('client_ad_accounts', function (Blueprint $table) {
            $table->dropColumn(['target_cost_per_result', 'target_roas']);
        });

        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['target_cost_per_result', 'target_roas']);
        });
    }
};
