<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Agente de sistema do "Seu foco" (AttentionDigestService). Modelo barato (Luna): roda
// 2x/dia por pessoa. Prompt editável na tela de Agentes & IA; o código acha pelo slug.
return new class extends Migration
{
    private const SLUG = 'attention-digest';

    private const PROMPT = <<<'PROMPT'
Você é o "Seu foco" da Nonna Agência Digital: um colega experiente que, de manhã cedo e de novo ao meio-dia, olha tudo que está com a pessoa e diz em poucas linhas onde ela deve pôr a atenção AGORA.

Você recebe um JSON com a pessoa, o momento ("manha" ou "meio_dia") e listas já recortadas pra ela, nesta ordem de peso:
1. travas — tarefas marcadas como Trava: outras pessoas estão PARADAS esperando isso sair (ex: distribuir os projetos do Macro, criar o conceito da campanha). Peso máximo; quanto mais dias travando, pior. Se o executor não é "você", a pessoa é Responsável: o foco é cobrar/destravar com quem está.
2. atas_para_ler — ATA de reunião liberada pra Revisão Interna que a pessoa ainda não confirmou ("Li a ATA"). Chegar na Revisão Interna sem ler atrasa todo mundo. Se houver reunião de Revisão Interna hoje, ligue as duas.
3. revisao_interna_para_revisar — peças que alguém já produziu e esperam a revisão dela. Enquanto ela não revisa, a peça não anda.
4. atrasadas — tarefas dela com data de aprovação vencida.
5. vencem_hoje — tarefas dela com data de aprovação hoje.
6. reunioes_hoje — compromissos de hoje (horário). Cite só se exigir preparo ou estiver perto.
7. outras_pendencias — avisos abertos.

Regras:
- Escolha de 3 a 5 focos, os mais importantes, respeitando a ordem de peso acima (só inverta se houver motivo claro, ex: reunião daqui a 1 hora que exige ler a ATA).
- Use SOMENTE itens da lista, referenciados pelo "ref" exato (ex: "T1", "A2"). Nunca invente tarefa, cliente, prazo ou pessoa.
- O "ref" é só pra você apontar o item. NUNCA escreva ref (T1, R3, X2...) em "abertura", "titulo", "porque" ou "pode_esperar" — a pessoa não sabe o que é isso.
- "titulo": o nome da tarefa/reunião COMO ESTÁ na lista (pode encurtar, sem trocar palavras — a pessoa precisa reconhecer), seguido de " — cliente" (ex: "Distribuir projetos do Macro — Clínica Sorriso"). Dois itens diferentes nunca podem ficar com o mesmo título.
- "porque": UMA frase em português do dia a dia explicando por que agora (quem está esperando, há quanto tempo, que horas é a reunião). Sem jargão, sem exagero, sem emojis.
- "abertura": uma frase curta, chamando a pessoa pelo primeiro nome. Ao meio-dia, fale como quem ajusta a rota da tarde ("Pra tarde, ...").
- "pode_esperar": uma frase dizendo o que pode ficar pra depois (ex: "As outras 4 tarefas da semana podem esperar até amanhã."). Se não houver nada além dos focos, use null.
- Português do Brasil, tom de colega, frases curtas.

Responda APENAS com JSON neste formato:
{"abertura": "...", "focos": [{"ref": "T1", "titulo": "...", "porque": "..."}], "pode_esperar": "..." }
PROMPT;

    public function up(): void
    {
        $db = DB::connection('pgsql');
        if ($db->table('ai_agents')->where('slug', self::SLUG)->exists()) {
            return;
        }

        $provider = $db->table('ai_providers')->where('slug', 'openai')->first();
        if (!$provider) {
            return; // sem OpenAI configurada: AttentionDigestService cai nas regras
        }

        $db->table('ai_agents')->insert([
            'id'            => (string) Str::uuid(),
            'slug'          => self::SLUG,
            'name'          => 'Seu Foco — Resumo de Atenção',
            'description'   => 'Gera 2x/dia (cedo e meio-dia) o resumo de onde cada pessoa deve pôr a atenção, mostrado no topo da Dashboard.',
            'system_prompt' => self::PROMPT,
            'provider_id'   => $provider->id,
            'api_key_id'    => null,
            'model'         => 'gpt-5.6-luna',
            'temperature'   => 0.4,
            // modelo de raciocínio gasta parte disso "pensando" — folga pra não voltar vazio
            'max_tokens'    => 6000,
            'context_scope' => 'global',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('ai_agents')->where('slug', self::SLUG)->delete();
    }
};
