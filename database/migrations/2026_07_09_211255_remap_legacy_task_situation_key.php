<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * A chave antiga do enum de situação ('aguardando_referencias') foi substituída
     * pelo texto real que vem do ClickUp ('Pendente de Informações'), pra situação
     * pegar a cor certa em vez de cair no cinza padrão por não bater com nada.
     */
    public function up(): void
    {
        $count = DB::table('tasks')->where('situation', 'aguardando_referencias')->count();
        if ($count > 0) {
            DB::table('tasks')->where('situation', 'aguardando_referencias')
                ->update(['situation' => 'Pendente de Informações']);
            Log::info("[remap_legacy_task_situation_key] aguardando_referencias -> Pendente de Informações: {$count} tarefas atualizadas.");
        }
    }

    public function down(): void
    {
        DB::table('tasks')->where('situation', 'Pendente de Informações')
            ->update(['situation' => 'aguardando_referencias']);
    }
};
