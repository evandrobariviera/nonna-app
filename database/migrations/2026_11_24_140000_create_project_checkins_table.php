<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conferências da campanha/projeto (etapa E.2, 24/11). Cada linha é uma conferência do
 * coordenador: metas (avaliação por número), pontos de cuidado (sinais de alerta), andamento
 * (situação + próximo passo) e a foto do que o Sistema mostrava no dia (sistema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('pgsql')->hasTable('project_checkins')) {
            return;
        }
        Schema::connection('pgsql')->create('project_checkins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('project_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('checked_on');
            $table->string('situacao', 20);                 // no_rumo | atencao | fora
            $table->jsonb('metas')->nullable();             // [{key, nome, partida, meta, atual, semaforo, avaliacao, comentario}]
            $table->jsonb('cuidados')->nullable();          // [{id, se, aconteceu, combinado}]
            $table->text('travou')->nullable();
            $table->string('depende', 20)->nullable();      // equipe | cliente
            $table->text('proximo_passo');
            $table->jsonb('sistema')->nullable();           // foto do pilar Sistema no dia
            $table->boolean('is_closing')->default(false);  // a última conferência = fechamento
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'checked_on']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('project_checkins');
    }
};
