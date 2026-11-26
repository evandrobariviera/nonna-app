<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cidade localizada no Google (GooglePlaces::locateCity): os limites no mapa (viewport)
 * prendem a varredura dentro da cidade. Sem isso, "dentista em Tangará SC" trazia
 * Tangará da Serra (MT) — o Google trata a UF no texto só como dica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_cities', function (Blueprint $table) {
            $table->string('google_place_id')->nullable();
            $table->string('google_label')->nullable(); // como o Google chama: "Tangará - SC"
            $table->jsonb('viewport')->nullable();      // {low:{latitude,longitude}, high:{...}}
        });
    }

    public function down(): void
    {
        Schema::table('commerce_cities', fn (Blueprint $table) => $table->dropColumn(['google_place_id', 'google_label', 'viewport']));
    }
};
