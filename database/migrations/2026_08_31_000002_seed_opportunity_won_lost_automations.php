<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria as automações da esteira comercial de Oportunidades — nada existia pra
 * essa entidade antes. Todas no gatilho "Status mudou" (o mesmo que já dispara
 * ao arrastar o card no kanban, ao "Fechar Negócio" e ao "Marcar Perdido"):
 *
 *  1. Oportunidade Ganha → Avisar grupo (n8n)   [send_webhook]  — INATIVA:
 *     depende de um workflow no n8n que receba o payload (entity_type=opportunity)
 *     e poste no grupo da agência. Ativar em Automações depois de montar o fluxo.
 *  2. Oportunidade Ganha → Notificar time        [send_notification] — ativa
 *  3. Oportunidade Perdida → Notificar time      [send_notification] — ativa
 *
 * Idempotente (guarda por nome). created_by = 1 (mesmo padrão das demais).
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');

        // Placeholder — o webhook node do n8n pra essa esteira ainda não existe.
        // O usuário confirma/ajusta a URL na tela de Automações antes de ativar.
        $n8nUrl = 'https://webhn8n-01.nonnaagenciadigital.com.br/webhook/oportunidade-ganha';

        $rows = [
            [
                'name'        => 'Oportunidade Ganha → Avisar grupo (n8n)',
                'description' => 'Quando uma oportunidade é marcada como Ganha, envia um webhook pro n8n com os dados (incluindo o tipo: novo cliente / projeto / follow-up) pra postar a mensagem no grupo da agência. INATIVA até o workflow no n8n existir.',
                'trigger_type'   => 'status_changed',
                'trigger_config' => ['to' => 'ganho', 'from' => '*', 'conditions_logic' => 'and'],
                'action_type'    => 'send_webhook',
                'action_config'  => ['url' => $n8nUrl, 'method' => 'POST'],
                'is_active'      => false,
            ],
            [
                'name'        => 'Oportunidade Ganha → Notificar time',
                'description' => 'Notificação interna (sino) quando uma oportunidade é marcada como Ganha.',
                'trigger_type'   => 'status_changed',
                'trigger_config' => ['to' => 'ganho', 'from' => '*', 'conditions_logic' => 'and'],
                'action_type'    => 'send_notification',
                'action_config'  => [
                    'to'      => 'role',
                    'role'    => 'atendimento',
                    'kind'    => 'oportunidade_ganha',
                    'message' => 'Oportunidade "{opportunity_title}" ({client_name}) fechada como GANHA — tipo: {opportunity_type}. Fee proposto: R$ {proposed_fee}.',
                ],
                'is_active'      => true,
            ],
            [
                'name'        => 'Oportunidade Perdida → Notificar time',
                'description' => 'Notificação interna (sino) quando uma oportunidade é marcada como Perdida.',
                'trigger_type'   => 'status_changed',
                'trigger_config' => ['to' => 'perdido', 'from' => '*', 'conditions_logic' => 'and'],
                'action_type'    => 'send_notification',
                'action_config'  => [
                    'to'      => 'role',
                    'role'    => 'atendimento',
                    'kind'    => 'oportunidade_perdida',
                    'message' => 'Oportunidade "{opportunity_title}" ({client_name}) marcada como PERDIDA — tipo: {opportunity_type}.',
                ],
                'is_active'      => true,
            ],
        ];

        foreach ($rows as $row) {
            if ($db->table('automations')->where('name', $row['name'])->exists()) {
                continue;
            }

            $db->table('automations')->insert([
                'id'             => (string) Str::uuid(),
                'name'           => $row['name'],
                'description'    => $row['description'],
                'entity_type'    => 'opportunity',
                'trigger_type'   => $row['trigger_type'],
                'trigger_config' => json_encode($row['trigger_config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'action_type'    => $row['action_type'],
                'action_config'  => json_encode($row['action_config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_active'      => $row['is_active'],
                'created_by'     => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('automations')->whereIn('name', [
            'Oportunidade Ganha → Avisar grupo (n8n)',
            'Oportunidade Ganha → Notificar time',
            'Oportunidade Perdida → Notificar time',
        ])->delete();
    }
};
