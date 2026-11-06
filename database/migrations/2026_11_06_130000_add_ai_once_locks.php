<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Trava de custo das ações de IA (2026-11-06, pedido do Evandro): cada uma roda UMA vez.
//  - meetings.ata_ai_generated_at: a ATA já foi gerada pela IA → botão some. Conceito: uma
//    reunião, uma ATA; precisou atualizar = nova reunião com a ATA anterior como pauta.
// (A conferência do documento usa document_update_runs: uma por documento × reunião.)
// Só marca quando dá certo — se a IA falhar, dá pra tentar de novo.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('pgsql')->hasColumn('meetings', 'ata_ai_generated_at')) {
            Schema::connection('pgsql')->table('meetings', function (Blueprint $table) {
                $table->timestamp('ata_ai_generated_at')->nullable();
            });
        }

        // ATAs que a IA já gerou antes da trava: o uso da IA não guarda a reunião, mas guarda
        // cliente + horário; a ATA é gravada logo depois da resposta (ata_recorded_at).
        DB::connection('pgsql')->statement(<<<'SQL'
            UPDATE meetings m
               SET ata_ai_generated_at = m.ata_recorded_at
             WHERE m.ata_ai_generated_at IS NULL
               AND m.ata_recorded_at IS NOT NULL
               AND EXISTS (
                   SELECT 1 FROM ai_token_usage u
                    WHERE u.trigger IN ('meeting:gerar_ata', 'automation:structure_ata')
                      AND u.client_id = m.client_id
                      AND u.created_at BETWEEN m.ata_recorded_at - interval '3 minutes' AND m.ata_recorded_at + interval '1 minute'
               )
        SQL);
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('meetings', fn (Blueprint $t) => $t->dropColumn('ata_ai_generated_at'));
    }
};
