<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Histórico de AÇÕES por tarefa (não de conteúdo) — criação, mudança de
    // status/situação/prioridade/destino/tipo/cliente/projeto/sprint, atribuição
    // de responsável/executor. Guarda só rótulos (de/para), nunca texto livre
    // (descrição/legenda ficam de fora de propósito).
    public function up(): void
    {
        Schema::connection('pgsql')->create('task_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('from_label')->nullable();
            $table->string('to_label')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('task_activities');
    }
};
