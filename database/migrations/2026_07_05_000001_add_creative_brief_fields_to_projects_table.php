<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->string('brief_status', 20)->default('basico')->after('type');

            // Brief criativo de campanha - texto livre
            $table->string('big_idea_titulo')->nullable()->after('traffic_phases');
            $table->text('big_idea_manifesto')->nullable()->after('big_idea_titulo');
            $table->text('territorio_alternativo')->nullable()->after('big_idea_manifesto');
            $table->text('racional_estrategico')->nullable()->after('territorio_alternativo');
            $table->string('frase_voz')->nullable()->after('racional_estrategico');
            $table->string('assinatura')->nullable()->after('frase_voz');
            $table->text('ponto_atencao')->nullable()->after('assinatura');

            // Brief criativo de campanha - listas (jsonb)
            $table->jsonb('tom_comunicacao')->nullable()->after('ponto_atencao');
            $table->jsonb('angulos')->nullable()->after('tom_comunicacao');
            $table->jsonb('mecanica')->nullable()->after('angulos');
            $table->jsonb('referencias_visuais')->nullable()->after('mecanica');
            $table->jsonb('pecas')->nullable()->after('referencias_visuais');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'brief_status',
                'big_idea_titulo', 'big_idea_manifesto', 'territorio_alternativo',
                'racional_estrategico', 'frase_voz', 'assinatura', 'ponto_atencao',
                'tom_comunicacao', 'angulos', 'mecanica', 'referencias_visuais', 'pecas',
            ]);
        });
    }
};
