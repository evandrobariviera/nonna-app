<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Chamado nasce só na tela de Tickets (triagem — protege a Fila de coisa que ainda
    // não foi avaliada, já que chamado cai primeiro no Atendimento). queued_at marca o
    // momento em que alguém decide "mandar pra Fila"; enquanto nulo, a tarefa só aparece
    // em Tickets. Tarefa de projeto (is_ticket=false) nunca passa por essa checagem.
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('queued_at')->nullable()->after('is_ticket');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('queued_at');
        });
    }
};
