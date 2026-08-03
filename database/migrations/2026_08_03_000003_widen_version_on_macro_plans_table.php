<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // macro_plans.version era varchar(20) — bom o bastante pra "v1.0", mas os
    // templates mais recentes da skill de geração de HTML colocam um rótulo bem
    // mais descritivo no campo "Versão" da Capa (ex: "v1.2 — Distribuição dos 36
    // materiais + C3 Orgânico"), o que estourava o limite e derrubava o import
    // inteiro com "value too long for type character varying(20)".
    public function up(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->string('version', 150)->default('1.0')->change();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('macro_plans', function (Blueprint $table) {
            $table->string('version', 20)->default('1.0')->change();
        });
    }
};
