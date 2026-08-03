<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Project::$disciplines só tinha as 7 disciplinas "operacionais" —
    // Estratégia e Relacionamento/CS já existem em MacroPlan::$disciplineOptions
    // (chips da Capa) mas nunca tiveram um campo de briefing próprio no Projeto,
    // então blocos de briefing com esses rótulos eram descartados silenciosamente
    // pelo MacroPlanHtmlImporter.
    public function up(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->text('briefing_estrategia')->nullable()->after('briefing_email');
            $table->text('briefing_relacionamento')->nullable()->after('briefing_estrategia');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('projects', function (Blueprint $table) {
            $table->dropColumn(['briefing_estrategia', 'briefing_relacionamento']);
        });
    }
};
