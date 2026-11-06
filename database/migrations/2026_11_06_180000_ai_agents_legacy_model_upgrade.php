<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Levantamento do uso de IA (2026-11-06):
 * - "Analista de Campanhas" e "Pauta — Reunião Interna" ainda rodavam no gpt-4o-mini (legado).
 *   Passam pro gpt-5.6-luna (mesmo custo na prática, centavos/mês). Luna é modelo de raciocínio:
 *   o limite de tokens inclui o "pensar", por isso os limites sobem (500 cortaria a resposta).
 * - "Analista de Atendimento" (Diagnóstico de Atendimento) bateu no teto de 16 mil tokens de
 *   resposta em 24/10 (conversa de 91 mil tokens) e cortou o diagnóstico no meio → 32 mil.
 * Só mexe se o agente ainda estiver como estava (não desfaz ajuste feito à mão na tela).
 * Agentes sem slug: localizados pelo id de produção.
 */
return new class extends Migration
{
    private const CHANGES = [
        // id => [modelo/limite esperados hoje, novos valores]
        '019fae71-8de5-71cb-a296-d0a0207637d1' => [['model' => 'gpt-4o-mini', 'max_tokens' => 500], ['model' => 'gpt-5.6-luna', 'max_tokens' => 4000]],   // Analista de Campanhas
        '01a0253e-74be-7399-97ca-71c4b7b5cc25' => [['model' => 'gpt-4o-mini', 'max_tokens' => 3000], ['model' => 'gpt-5.6-luna', 'max_tokens' => 8000]],  // Pauta — Reunião Interna
        '019f8ab0-f410-73db-b77c-6b50506bee97' => [['model' => 'gpt-5.6-luna', 'max_tokens' => 16000], ['model' => 'gpt-5.6-luna', 'max_tokens' => 32000]], // Analista de Atendimento
    ];

    public function up(): void
    {
        foreach (self::CHANGES as $id => [$from, $to]) {
            DB::connection('pgsql')->table('ai_agents')
                ->where('id', $id)
                ->where('model', $from['model'])
                ->where('max_tokens', $from['max_tokens'])
                ->update($to + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::CHANGES as $id => [$from, $to]) {
            DB::connection('pgsql')->table('ai_agents')
                ->where('id', $id)
                ->where('model', $to['model'])
                ->where('max_tokens', $to['max_tokens'])
                ->update($from + ['updated_at' => now()]);
        }
    }
};
