<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajusta o texto da notificação interna (sino) de "Oportunidade Ganha →
 * Notificar time" (seedada em 2026_08_31_000002) — tom mais motivacional e
 * traz a Observação da oportunidade ({opportunity_notes}, novo em
 * ContextResolver::forOpportunity) pra equipe já ter contexto de quem entrou.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $db = DB::connection('pgsql');

        $automation = $db->table('automations')
            ->where('name', 'Oportunidade Ganha → Notificar time')
            ->first();

        if (!$automation) {
            return;
        }

        $config = json_decode($automation->action_config, true) ?? [];
        $config['message'] = 'Mais um fechado! {client_name} entra como cliente pela oportunidade "{opportunity_title}" (fee R$ {proposed_fee}).'
            . "\n\n" . 'Pra equipe já saber quem chegou: {opportunity_notes}';

        $db->table('automations')
            ->where('id', $automation->id)
            ->update([
                'action_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at'    => now(),
            ]);
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');

        $automation = $db->table('automations')
            ->where('name', 'Oportunidade Ganha → Notificar time')
            ->first();

        if (!$automation) {
            return;
        }

        $config = json_decode($automation->action_config, true) ?? [];
        $config['message'] = 'Oportunidade "{opportunity_title}" ({client_name}) fechada como GANHA — tipo: {opportunity_type}. Fee proposto: R$ {proposed_fee}.';

        $db->table('automations')
            ->where('id', $automation->id)
            ->update([
                'action_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at'    => now(),
            ]);
    }
};
