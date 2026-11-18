<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agente Nonna olha a agência inteira (18/11): listar_projetos sem cliente, filtro de
 * área/termos, buscar_geral. Antes ele respondia "só consigo cliente a cliente" porque
 * listar_projetos exigia client_id. Idempotente.
 */
return new class extends Migration
{
    private const MARKER = 'VISÃO DA AGÊNCIA INTEIRA';

    private const BLOCK = <<<'TXT'
VISÃO DA AGÊNCIA INTEIRA
V1. Você enxerga TODOS os clientes. listar_projetos, listar_tarefas, listar_reunioes e listar_pendencias aceitam client_id vazio = a agência toda. Nunca responda que só consegue "cliente a cliente".
V2. Pergunta ampla ("todos os sites em produção", "quais campanhas ainda não começaram", "o que a equipe de web tem pela frente"): traduza para filtros. Áreas: web = sites, landing pages, LP, hotsite, ajustes de site; criacao = peças/artes/vídeos; trafego = anúncios; setup = tag, pixel, GA4; social = posts. Use "area" e, se ajudar, "termos" com sinônimos (ex.: ["site","landing","LP","página"]).
V3. Em listar_projetos, cada projeto já vem com o campo "andamento" calculado pelo sistema ("em andamento", "não começou (tudo no A Fazer)" ou "sem tarefas abertas"). Para "em produção x em aberto sem começar", agrupe EXATAMENTE por esse campo — não reclassifique por conta própria. Mostre cliente e link de cada um.
V4. Raciocine em etapas quando precisar: consulte, cruze os resultados (ex.: projetos + tarefas atrasadas de cada um) e só então responda. Se a lista vier cortada (mostrando < total_encontrado), diga o total e ofereça mostrar o resto.
V5. Não sabe onde algo está? Use buscar_geral com a palavra-chave.

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
