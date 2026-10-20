<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // "Notificação de Reunião em Despacho" vira pendência pro Marlon lançar o planejamento
    // e as campanhas (decisão do Evandro, 2026-10-20). Antes: notificava "um papel" sem papel
    // escolhido — rodava e não avisava ninguém. Roda no deploy porque a ação
    // create_pendency só existe no código novo. Só mexe se ainda estiver como antes.
    private const AUTOMATION_ID = '01a0f221-3e75-7002-be82-02a652092228';

    public function up(): void
    {
        $db = DB::connection('pgsql');
        $automation = $db->table('automations')->where('id', self::AUTOMATION_ID)->first();
        $marlonId   = $db->table('users')->where('name', 'ilike', 'Marlon%')->value('id');

        if (!$automation || $automation->action_type !== 'send_notification' || !$marlonId) {
            return;
        }

        $db->table('automations')->where('id', self::AUTOMATION_ID)->update([
            'name'          => 'Reunião em Despacho → pendência: lançar planejamento e campanhas',
            'action_type'   => 'create_pendency',
            'action_config' => json_encode([
                'pendency_title'         => 'Lançar planejamento e campanhas — {meeting_title}',
                'pendency_description'   => 'Reunião finalizada: planejamentos e atas atualizados, prontos pra lançar.',
                'pendency_kind'          => 'outro',
                'owed_by'                => 'agencia',
                'owed_user_source'       => 'fixed',
                'owed_user_id'           => (string) $marlonId,
                'owner_source'           => 'organizer',
                'requester_source'       => 'organizer',
                'pendency_due_in_days'   => '2',
                'pendency_skip_weekends' => '1',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('automations')->where('id', self::AUTOMATION_ID)->update([
            'name'          => 'Notificação de Reunião em Despacho',
            'action_type'   => 'send_notification',
            'action_config' => json_encode(['to' => 'role', 'role' => null, 'message' => 'Reunião finalizada, planejamentos e atas atualizadas, prontas para liberação']),
        ]);
    }
};
