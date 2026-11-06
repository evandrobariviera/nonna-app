<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Atualizar com reunião" → Pendências (2026-11-06): os pontos em aberto, demandas e tarefas
 * "fora deste documento" ficavam só na tela da análise. Agora a IA devolve "pendencias"
 * (tipo + quem deve entregar) e cada uma vira Pendência do projeto/planejamento num clique.
 * Idempotente: só acrescenta se o bloco ainda não estiver no prompt.
 */
return new class extends Migration
{
    private const MARK = 'PENDÊNCIAS E DEMANDAS (o que fica para depois';

    private const RULES = <<<'TXT'
PENDÊNCIAS E DEMANDAS (o que fica para depois da reunião — obrigatório). Em "pendencias", liste tudo que alguém precisa FAZER, ENTREGAR, CONFIRMAR ou DECIDIR depois da reunião e que não se resolve só mudando o documento: material ou acesso a pedir, confirmação com alguém, decisão adiada, tarefa operacional que apareceu na pauta como "fora". Cada uma vira uma pendência no App, com dono e cobrança.
- "titulo": a ação, começando por verbo, clara para quem não estava na reunião (ex.: "Enviar os materiais de divulgação já existentes do Assistente").
- "tipo": insumo (material, arquivo, foto) | informacao (dado, confirmação, resposta) | acesso (login, permissão) | agendamento | decisao | pagamento | outro.
- "quem_deve": quem precisa entregar — cliente | agencia | terceiro.
- "quem": nome da pessoa ou empresa citada na reunião (ou null). Nunca invente.
- "origem": aberto (ponto em aberto) | nova_demanda | fora (fora deste documento).
- "contexto": uma frase com o porquê, para quem for cobrar.
Não inclua o que já foi decidido e não pede ação de ninguém (ex.: "decidimos não usar a credencial X"), nem o que já virou sugestão no documento. Uma pendência por ação; não repita a mesma em origens diferentes.


TXT;

    private const FORMAT_FROM = '  "pontos_em_aberto": ["..."]';
    private const FORMAT_TO   = <<<'TXT'
  "pendencias": [
    { "titulo": "ação começando por verbo", "tipo": "insumo | informacao | acesso | agendamento | decisao | pagamento | outro", "quem_deve": "cliente | agencia | terceiro", "quem": "nome citado ou null", "origem": "aberto | nova_demanda | fora", "contexto": "uma frase" }
  ],
  "pontos_em_aberto": ["..."]
TXT;

    public function up(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'document-updater')->first(['id', 'system_prompt']);
        if (!$agent || str_contains($agent->system_prompt, self::MARK)) {
            return;
        }

        $prompt = $agent->system_prompt;
        $anchor = 'Linguagem: o time não é técnico.';
        $prompt = str_contains($prompt, $anchor)
            ? str_replace($anchor, self::RULES . $anchor, $prompt)
            : $prompt . "\n\n" . self::RULES;
        $prompt = str_replace(self::FORMAT_FROM, self::FORMAT_TO, $prompt);

        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update(['system_prompt' => $prompt, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $agent = DB::connection('pgsql')->table('ai_agents')->where('slug', 'document-updater')->first(['id', 'system_prompt']);
        if (!$agent) {
            return;
        }

        $prompt = str_replace([self::RULES, self::FORMAT_TO], ['', self::FORMAT_FROM], $agent->system_prompt);
        DB::connection('pgsql')->table('ai_agents')->where('id', $agent->id)->update(['system_prompt' => $prompt, 'updated_at' => now()]);
    }
};
