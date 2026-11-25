<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medidor de uso do Google Places (Prospecção) — trava de custo (App\Services\Commerce\GoogleUsage).
 * Uma linha por organização × mês × tipo de chamada (busca / lista / coleta), só com a
 * contagem. Nada do Google é guardado aqui (termos do Maps Platform).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_places_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7);        // 2026-11
            $table->string('sku', 20);       // busca | lista | coleta
            $table->unsignedInteger('calls')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'month', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_places_usage');
    }
};
