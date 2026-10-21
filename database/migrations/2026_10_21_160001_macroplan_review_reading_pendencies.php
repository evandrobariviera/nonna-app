<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Planejamento em Revisão Interna → pendência de leitura pro responsável do cliente e pro
    // Marlon (decisão do Evandro, 2026-10-21). Substitui a tarefa "Revisar Macroplanejamento"
    // (desligada, não apagada). Depois da leitura, monta-se a equipe de cada campanha/projeto
    // e agenda-se a reunião de discussão pela aba Campanhas e Projetos do planejamento.
    private const OLD_AUTOMATION_ID = '01a040ea-10a6-72d5-ad5b-62e708364768';

    private const NAMES = [
        'Revisão Interna → pendência: responsável do cliente ler planejamento e campanhas',
        'Revisão Interna → pendência: Marlon ler planejamento e campanhas',
    ];

    public function up(): void
    {
        $db = DB::connection('pgsql');
        $marlonId = $db->table('users')->where('name', 'ilike', 'Marlon%')->value('id');

        if ($db->table('automations')->whereIn('name', self::NAMES)->exists()) {
            return;
        }

        $description = 'Planejamento em Revisão Interna: ler o documento do planejamento e o de cada campanha/projeto. '
            . 'Depois, montar a equipe de cada item e agendar a reunião de discussão (aba Campanhas e Projetos).';

        $base = [
            'pendency_description'   => $description,
            'pendency_kind'          => 'outro',
            'owed_by'                => 'agencia',
            'owner_source'           => 'plan_responsible',
            'requester_source'       => 'plan_responsible',
            'pendency_due_in_days'   => '2',
            'pendency_skip_weekends' => '1',
        ];

        $rows = [[
            'name'   => self::NAMES[0],
            'config' => $base + [
                // títulos diferentes: a ação não cria duas pendências abertas com o mesmo título
                'pendency_title'   => 'Ler planejamento e campanhas (responsável do cliente) — {client_name}',
                'owed_user_source' => 'client_creative_lead',
            ],
        ]];
        if ($marlonId) {
            $rows[] = [
                'name'   => self::NAMES[1],
                'config' => $base + [
                    'pendency_title'   => 'Ler planejamento e campanhas (gestão de projetos) — {client_name}',
                    'owed_user_source' => 'fixed',
                    'owed_user_id'     => (string) $marlonId,
                ],
            ];
        }

        foreach ($rows as $row) {
            $db->table('automations')->insert([
                'id'             => (string) Str::uuid7(),
                'name'           => $row['name'],
                'description'    => 'Fluxo de discussão de campanhas/projetos: planejamento entra em Revisão Interna → pendência de leitura.',
                'entity_type'    => 'macro_plan',
                'trigger_type'   => 'status_changed',
                'trigger_config' => json_encode(['from' => '*', 'to' => 'revisao_interna']),
                'action_type'    => 'create_pendency',
                'action_config'  => json_encode($row['config'], JSON_UNESCAPED_UNICODE),
                'is_active'      => true,
                'created_by'     => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $db->table('automations')->where('id', self::OLD_AUTOMATION_ID)->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');
        $db->table('automations')->whereIn('name', self::NAMES)->delete();
        $db->table('automations')->where('id', self::OLD_AUTOMATION_ID)->update(['is_active' => true]);
    }
};
