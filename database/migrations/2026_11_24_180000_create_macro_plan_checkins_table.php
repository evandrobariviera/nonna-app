<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conferências das METAS do planejamento (24/11): a cada 30 dias, na revisão do meio e no
 * fechamento, pelo responsável do planejamento. metas = avaliação por meta do ciclo (com o
 * resultado final no fechamento); sistema = foto das campanhas/projetos que ajudam em cada meta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('pgsql')->hasTable('macro_plan_checkins')) {
            return;
        }
        Schema::connection('pgsql')->create('macro_plan_checkins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('macro_plan_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('checked_on');
            $table->string('moment', 20)->default('mensal');  // mensal | meio | fechamento
            $table->string('situacao', 20);                    // no_rumo | atencao | fora
            $table->jsonb('metas')->nullable();                // [{key, nome, partida, meta, atual, semaforo, avaliacao, comentario, resultado}]
            $table->text('travou')->nullable();
            $table->string('depende', 20)->nullable();
            $table->text('proximo_passo');
            $table->jsonb('sistema')->nullable();
            $table->boolean('is_closing')->default(false);
            $table->timestamps();

            $table->foreign('macro_plan_id')->references('id')->on('macro_plans')->cascadeOnDelete();
            $table->index(['macro_plan_id', 'checked_on']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('macro_plan_checkins');
    }
};
