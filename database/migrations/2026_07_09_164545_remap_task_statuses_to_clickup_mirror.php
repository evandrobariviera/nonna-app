<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Status antigo => novo status (espelho 1:1 do ClickUp).
     * Status antigos sem linha nenhuma ficam de fora (nada a fazer).
     */
    private const MAP = [
        'aguardando_aprovacao' => 'aprovacao',
        'em_copy'              => 'em_producao',
        'pronto_producao'      => 'backlog',
        'revisao'              => 'revisao_interna',
        'aguardando_envio'     => 'aprovacao',
        'aguardando_resposta'  => 'aprovacao',
        'ajuste'               => 'ajuste_alteracao',
        'aprovado'             => 'despacho_agendamento',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            $count = DB::table('tasks')->where('status', $old)->count();
            if ($count > 0) {
                DB::table('tasks')->where('status', $old)->update(['status' => $new]);
                Log::info("[remap_task_statuses] {$old} -> {$new}: {$count} tarefas atualizadas.");
            }
        }
    }

    public function down(): void
    {
        // Vários status antigos convergem pro mesmo novo status (ex: aguardando_envio,
        // aguardando_resposta e aguardando_aprovacao viram todos "aprovacao") — reversão
        // perfeita é impossível nesses casos por perda de informação. Só revertemos
        // quando o mapeamento é 1:1 (sem ambiguidade).
        $countsByNew = array_count_values(self::MAP);
        foreach (self::MAP as $old => $new) {
            if ($countsByNew[$new] === 1) {
                DB::table('tasks')->where('status', $new)->update(['status' => $old]);
            }
        }
    }
};
