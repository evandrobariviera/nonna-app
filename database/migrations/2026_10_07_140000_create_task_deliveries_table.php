<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retorno de entrega — toda ida de uma tarefa pra Revisão Interna feita por uma pessoa
 * exige que ela conte o que fez (ver TaskDeliveryService). Uma linha por entrega: volta
 * de Ajuste e entrega de novo = linha nova. O texto é escrito pensando no cliente (vai
 * servir de justificativa na aprovação), por isso fica separado dos comentários internos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('task_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            // Quem sai da empresa: a entrega fica no histórico, sem autor.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('fully_done');
            $table->text('missing')->nullable(); // só quando fully_done = false: o que faltou e por quê
            $table->text('body');
            $table->string('from_status', 30)->nullable(); // de onde a tarefa saiu (em_producao, ajuste_alteracao...)
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('task_deliveries');
    }
};
