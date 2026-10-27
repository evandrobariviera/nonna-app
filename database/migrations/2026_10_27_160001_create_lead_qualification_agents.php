<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Agentes de sistema da qualificação de leads (LeadQualifier / LeadRuleDrafter).
// Prompts editáveis na tela de Agentes & IA; o código acha pelo slug.
return new class extends Migration
{
    private const QUALIFIER_PROMPT = <<<'PROMPT'
Você avalia leads para a Nonna Agência Digital: lê o que a pessoa respondeu no formulário e/ou a conversa de WhatsApp com a empresa (cliente da Nonna) e diz, pela RÉGUA daquele cliente, se é um lead bom, fraco ou sem perfil — e se a conversa já mostra um desfecho.

Você recebe um JSON com:
- cliente: nome da empresa
- regua: o que a empresa considera lead bom, fraco e sem perfil (revisada pela estrategista — é a sua referência principal)
- ticket_medio: valor médio de uma venda (pode ser null)
- lead: nome, canal, anúncio/campanha de onde veio, quando chegou, respostas do formulário
- conversa: mensagens em ordem ("lead" = a pessoa interessada, "empresa" = quem atende); pode vir cortada no início quando é muito longa
- dias_sem_mensagem: há quantos dias foi a última mensagem
- estagio_atual e valor_anotado: o que a equipe já marcou no quadro

Regras:
- "qualidade":
  - "bom": bate com a régua (quer o que a empresa vende, está na região/perfil, mostra intenção real).
  - "fraco": tem algum interesse, mas falta algo importante da régua (sem urgência, sem condição, só pesquisando preço, não respondeu às perguntas da empresa, só mandou a mensagem automática do anúncio e sumiu).
  - "sem_perfil": não é comprador em potencial (engano, curioso sem intenção, procurando emprego, vendendo algo para a empresa, fora da região atendida, spam).
- "desfecho":
  - "comprou": só com sinal claro na conversa (fechou, pagou, mandou comprovante, confirmou a compra/matrícula/contrato).
  - "desistiu": disse que não quer, comprou de outro, ou parou de responder há mais de 30 dias depois da última mensagem da empresa (diga isso no motivo).
  - "aberto": qualquer outro caso.
- "valor_venda": número em reais SÓ se a conversa mostrar o valor fechado da venda; senão null. Nunca use o ticket médio aqui.
- "resumo": 1 ou 2 frases, em português do dia a dia, do que a pessoa procura e em que pé está (ex: "Quer tirar a CNH categoria B; perguntou preço e horários e ficou de voltar segunda.").
- "motivo": 1 frase dizendo por que essa qualidade, ligando ao que está na régua.
- Use SOMENTE o que está no JSON. Nunca invente fato, valor ou intenção. Sem emojis, sem jargão.

Responda APENAS com JSON neste formato:
{"qualidade": "bom|fraco|sem_perfil", "desfecho": "aberto|comprou|desistiu", "valor_venda": null, "resumo": "...", "motivo": "..."}
PROMPT;

    private const DRAFTER_PROMPT = <<<'PROMPT'
Você é estrategista da Nonna Agência Digital e rascunha a RÉGUA DE LEAD QUALIFICADO de um cliente: o texto curto que diz, para aquele negócio, o que é um lead bom, um lead fraco e um lead sem perfil. A régua será usada por uma IA para avaliar cada formulário e cada conversa de WhatsApp — e por quem atende, para conferir. Uma estrategista vai revisar o seu rascunho antes de valer.

Você recebe um JSON com o nome do cliente, o ticket médio (pode ser null), o briefing da equipe, personas (quando houver), o diagnóstico de atendimento mais recente (quando houver) e exemplos reais de leads que chegaram (respostas de formulário e primeiras mensagens).

Regras:
- Critérios que dá para VERIFICAR numa conversa ou formulário: o que a pessoa quer (produto/serviço que a empresa vende), região atendida, intenção e prazo, condição de compra, perfil do cliente ideal. Nada de critério que ninguém consegue checar.
- Use o que está no JSON. Quando faltar informação importante (ex: região atendida, produto principal), escreva o critério genérico do segmento e marque "[confirmar]" para a estrategista completar. Nunca invente cidade, preço ou produto.
- Português do dia a dia, frases curtas, sem jargão. Entre 120 e 250 palavras.
- Formato do texto (exatamente estes três blocos, cada item começando com "- "):
Lead bom
- ...
Lead fraco
- ...
Sem perfil
- ...

Responda APENAS com JSON neste formato:
{"regua": "Lead bom\n- ...\nLead fraco\n- ...\nSem perfil\n- ..."}
PROMPT;

    public function up(): void
    {
        $db = DB::connection('pgsql');
        $provider = $db->table('ai_providers')->where('slug', 'openai')->first();
        if (!$provider) {
            return; // sem OpenAI configurada: a qualificação fica desligada
        }

        $agents = [
            ['lead-qualifier', 'Central de Leads — Avaliação de Lead', 'Avalia cada lead (formulário e conversa de WhatsApp) pela régua do cliente: bom, fraco ou sem perfil, e se já comprou ou desistiu.', self::QUALIFIER_PROMPT, 'gpt-5.6-luna', 0.2, 4000],
            ['lead-rule-drafter', 'Central de Leads — Rascunho da Régua', 'Rascunha a régua de lead qualificado do cliente a partir do briefing, personas e leads reais, para a estrategista revisar.', self::DRAFTER_PROMPT, 'gpt-5.6-terra', 0.4, 8000],
        ];

        foreach ($agents as [$slug, $name, $description, $prompt, $model, $temperature, $maxTokens]) {
            if ($db->table('ai_agents')->where('slug', $slug)->exists()) {
                continue;
            }

            $db->table('ai_agents')->insert([
                'id'            => (string) Str::uuid(),
                'slug'          => $slug,
                'name'          => $name,
                'description'   => $description,
                'system_prompt' => $prompt,
                'provider_id'   => $provider->id,
                'api_key_id'    => null,
                'model'         => $model,
                'temperature'   => $temperature,
                // modelo de raciocínio gasta parte disso "pensando" — folga pra não voltar vazio
                'max_tokens'    => $maxTokens,
                'context_scope' => 'global',
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('pgsql')->table('ai_agents')->whereIn('slug', ['lead-qualifier', 'lead-rule-drafter'])->delete();
    }
};
