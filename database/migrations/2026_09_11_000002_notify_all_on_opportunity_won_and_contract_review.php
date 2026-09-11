<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplia o alcance de duas notificações que estavam restritas a um papel
 * funcional específico — o Evandro decidiu que agora devem chegar pra todo
 * mundo do time:
 *
 *  - "Oportunidade Ganha → Notificar time" (automations.action_config):
 *    trocado de to=role/atendimento pra to=all — AutomationJob::resolveRecipients
 *    passou a tratar to=all como "todo mundo" pra entidades sem
 *    executor/responsável (Oportunidade, Projeto, Campanha).
 *
 *  - "Contrato em análise" (ClientOnboardingService::notifyFinance) não é uma
 *    automação em banco — o código já foi trocado direto pra User::all(),
 *    sem dado pra migrar aqui.
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
        $config['to'] = 'all';
        unset($config['role']);

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
        $config['to']   = 'role';
        $config['role'] = 'atendimento';

        $db->table('automations')
            ->where('id', $automation->id)
            ->update([
                'action_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at'    => now(),
            ]);
    }
};
