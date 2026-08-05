<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql';

    // Antes, uma pessoa só podia ocupar 1 papel por tarefa (executor OU responsável OU
    // observador) — a constraint impedia a mesma pessoa acumular 2 papéis na mesma tarefa,
    // o que é um caso de uso real (ex: time pequeno, a mesma pessoa é executora e
    // responsável). Troca a constraint pra (task_id, user_id, role) — só impede duplicar o
    // mesmo papel duas vezes, não mais papéis diferentes pra mesma pessoa.
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_executors', function (Blueprint $table) {
            $table->dropUnique('task_executors_task_id_user_id_unique');
            $table->unique(['task_id', 'user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_executors', function (Blueprint $table) {
            $table->dropUnique(['task_id', 'user_id', 'role']);
            $table->unique(['task_id', 'user_id']);
        });
    }
};
