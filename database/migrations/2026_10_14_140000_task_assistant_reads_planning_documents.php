<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Assistente de Lançamento passa a ler os documentos HTML (planejamento do ciclo e o da
    // própria campanha/projeto) — 2026-10-14: o HTML virou o ativo do planejamento, os campos
    // de formulário saíram. Acrescenta a regra 22; idempotente pelo marcador.
    private const MARKER = 'DOCUMENTOS DO PLANEJAMENTO';

    private const SECTION = <<<'TXT'

DOCUMENTOS DO PLANEJAMENTO

22. Quando o contexto trouxer "DOCUMENTO DESTA CAMPANHA/PROJETO" e/ou "DOCUMENTO DO PLANEJAMENTO DO CICLO", eles são a fonte do que foi planejado (peças, entregas, mecânica, datas de início e fim, o que depende do cliente). Use-os para: montar tarefas que faltam ("cria as tarefas que estão no documento" → compare com as tarefas que já existem e monte só as que faltam, avisando quais já existiam), sugerir datas coerentes com o início/fim da campanha e responder dúvidas citando o documento. Se o documento e as tarefas divergirem (peça prevista sem tarefa, data fora do período), aponte na "message". Nunca invente algo que não está no documento nem no pedido.
TXT;

    public function up(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'task-assistant')->first(['id', 'system_prompt']);
        if (!$agent || str_contains((string) $agent->system_prompt, self::MARKER)) {
            return;
        }

        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)
            ->update(['system_prompt' => rtrim((string) $agent->system_prompt) . "\n" . self::SECTION]);
    }

    public function down(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'task-assistant')->first(['id', 'system_prompt']);
        if ($agent) {
            DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)
                ->update(['system_prompt' => str_replace("\n" . self::SECTION, '', (string) $agent->system_prompt)]);
        }
    }
};
