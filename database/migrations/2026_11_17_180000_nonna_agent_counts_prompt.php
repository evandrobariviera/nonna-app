<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agente Nonna: listar_tarefas/listar_pendencias passaram a devolver "contagens"
 * prontas (feitas no banco). Regra pra usar sempre esses números em vez de contar a
 * lista (no teste 17/11 o modelo disse 16 de 20 atrasadas quando eram 18), e pra não
 * perguntar o responsável da tarefa sem necessidade. Idempotente.
 */
return new class extends Migration
{
    private const MARKER = 'NÚMEROS E CONTAGENS';

    private const BLOCK = <<<'TXT'
NÚMEROS E CONTAGENS
N1. Para "quantas", "quem tem mais", "qual cliente/projeto concentra", use SEMPRE o campo "contagens" que a ferramenta devolve (feito no banco, sobre todos os itens encontrados). Nunca conte itens da lista à mão: a lista pode estar cortada (veja "mostrando" x "total_encontrado").
N2. Se a pergunta precisar de um número que não está em "contagens", diga que não tem esse número pronto em vez de estimar.
N3. Em tarefa nova, NÃO pergunte quem é o responsável: deixe em branco se a pessoa não disser. Só pergunte o que for essencial (cliente, assunto da tarefa, ou data/hora de reunião).

TXT;

    public function up(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'nonna-agent')->first();
        if (!$agent || str_contains($agent->system_prompt, self::MARKER)) {
            return;
        }

        $prompt = str_contains($agent->system_prompt, "\nLIMITES")
            ? str_replace("\nLIMITES", "\n" . self::BLOCK . "\nLIMITES", $agent->system_prompt)
            : $agent->system_prompt . "\n\n" . self::BLOCK;

        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update([
            'system_prompt' => $prompt,
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'nonna-agent')->first();
        if ($agent) {
            DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)
                ->update(['system_prompt' => str_replace("\n" . self::BLOCK, '', $agent->system_prompt)]);
        }
    }
};
