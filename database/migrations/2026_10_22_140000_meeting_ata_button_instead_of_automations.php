<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ATA por botão (decisão do Evandro, 2026-10-22): a IA não roda mais na troca de status
    // pra Revisão Interna — é o botão "Gerar ATA com IA" da reunião, que também avisa os
    // participantes ("✓ Li a ATA"). A tarefa de atualização da ATA vira a pendência
    // "Registrar a ATA" do organizador (meetings:ata-pendencies). As três automações ficam
    // desligadas, não apagadas. O agente da ATA ganha o slug que o botão procura.
    private const AUTOMATIONS = [
        '01a02551-e403-73de-ae62-296e2b890f0c', // Estrutura ATA da Reunião via IA
        '01a04079-4bfb-7277-9995-0742d8b9f58d', // Notifica ATA pronta
        '01a040ea-0d7e-73b1-8d98-b94d4ebf67f6', // Cria Tarefa de Atualização da ATA
    ];

    private const ATA_AGENT_ID = '01a0409a-24ae-73f2-9424-02b06e2f0018'; // ATA Estruturada — Reunião com Cliente

    public function up(): void
    {
        $db = DB::connection('pgsql');

        $db->table('automations')->whereIn('id', self::AUTOMATIONS)->update(['is_active' => false, 'updated_at' => now()]);

        if (!$db->table('ai_agents')->where('slug', 'ata-estruturada')->exists()) {
            $db->table('ai_agents')->where('id', self::ATA_AGENT_ID)->update(['slug' => 'ata-estruturada', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('pgsql');
        $db->table('automations')->whereIn('id', self::AUTOMATIONS)->update(['is_active' => true]);
        $db->table('ai_agents')->where('id', self::ATA_AGENT_ID)->where('slug', 'ata-estruturada')->update(['slug' => null]);
    }
};
