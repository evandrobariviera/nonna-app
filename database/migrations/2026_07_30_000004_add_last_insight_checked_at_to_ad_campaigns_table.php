<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            // Marca quando a campanha foi avaliada pela última vez pelo motor de
            // insights de IA no ciclo de otimização atual (desde o último
            // last_optimized_at) — evita reprocessar a mesma campanha todo dia
            // enquanto ela permanece "em atraso" sem ter sido otimizada de novo.
            $table->timestamp('last_insight_checked_at')->nullable()->after('last_optimized_at');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn('last_insight_checked_at');
        });
    }
};
