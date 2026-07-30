<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Tratada" — separado do status da rodada de propósito (approved/changes_requested/
// pending são o resultado da decisão do cliente, não mexem por ação da agência).
// Marcado quando alguém usa o quick-select de status/situação na Central de
// Aprovações pra rotear a tarefa de volta (Ajuste/Alteração, Revisão Interna, etc)
// depois de um "Ajustes Solicitados" — a partir daí a rodada some da Central
// (menos poluição visual), mas continua intacta no histórico da tarefa/portal.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            $table->timestamp('handled_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('task_approval_rounds', function (Blueprint $table) {
            $table->dropColumn('handled_at');
        });
    }
};
