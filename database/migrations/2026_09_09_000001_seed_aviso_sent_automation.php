<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Automação: quando um Aviso é enviado ao cliente pela Central de Aprovações
 * (TaskApprovalService::sendAviso() — rodada sem entregável, sem decisão do
 * cliente), a tarefa relacionada passa pra "Concluído".
 *
 * Fica em /automacoes como qualquer outra — pode ser pausada/editada por lá.
 * Idempotente (guarda por nome). created_by = 1, mesmo padrão das demais.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    private string $name = 'Aviso enviado → concluir tarefa';

    public function up(): void
    {
        $db = DB::connection('pgsql');

        if ($db->table('automations')->where('name', $this->name)->exists()) {
            return;
        }

        $db->table('automations')->insert([
            'id'             => (string) Str::uuid(),
            'name'           => $this->name,
            'description'    => 'Quando um Aviso é enviado ao cliente pela Central de Aprovações, marca a tarefa relacionada como Concluída. (Aviso não tem decisão do cliente — a rodada já nasce aprovada.)',
            'entity_type'    => 'task',
            'trigger_type'   => 'aviso_sent',
            'trigger_config' => json_encode(['conditions_logic' => 'and'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'action_type'    => 'update_field',
            'action_config'  => json_encode(['field' => 'status', 'value' => 'concluido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active'      => true,
            'created_by'     => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('automations')->where('name', $this->name)->delete();
    }
};
