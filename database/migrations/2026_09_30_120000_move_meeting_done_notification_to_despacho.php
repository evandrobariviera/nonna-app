<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reunião ganhou o status "Despacho" entre Revisão Interna e Realizada (agora rotulada
 * "Finalizada"). O aviso "Reunião finalizada, planejamentos e atas atualizadas, prontas
 * para liberação" é justamente a passagem pro Gestor de Projetos — então passa a
 * disparar ao entrar em Despacho (decisão do Evandro, 2026-09-30).
 *
 * Só mexe na automação de reunião com esse gatilho; a de gerar Macro por IA
 * ("Cria Macroplanejamento ao Realizar Reunião") fica como está, por decisão dele.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->mover('realizada', 'despacho', 'Notificação de Reunião Realizada', 'Notificação de Reunião em Despacho');
    }

    public function down(): void
    {
        $this->mover('despacho', 'realizada', 'Notificação de Reunião em Despacho', 'Notificação de Reunião Realizada');
    }

    private function mover(string $de, string $para, string $nomeAntigo, string $nomeNovo): void
    {
        $automacoes = DB::connection('pgsql')->table('automations')
            ->where('entity_type', 'meeting')
            ->where('trigger_type', 'status_changed')
            ->where('name', $nomeAntigo)
            ->get(['id', 'trigger_config']);

        foreach ($automacoes as $a) {
            $config = json_decode($a->trigger_config, true) ?: [];
            if (($config['to'] ?? null) !== $de) {
                continue;
            }
            $config['to'] = $para;

            DB::connection('pgsql')->table('automations')->where('id', $a->id)->update([
                'trigger_config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                'name' => $nomeNovo,
                'updated_at' => now(),
            ]);
        }
    }
};
