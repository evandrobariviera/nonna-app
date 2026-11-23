<?php

namespace App\Services\Documents;

use App\Models\AiAgent;
use App\Models\Document;
use App\Models\MacroPlan;
use App\Services\AiService;
use Illuminate\Support\Str;

/**
 * Rascunho do planejamento pelo Agente Nonna (23/11, etapa 3). O estrategista escolhe as
 * fontes (PlanningSources) e o agente lê tudo UMA vez e preenche as seções do modelo.
 * Número que ninguém falou fica "A confirmar"; o que faltou vira pergunta para a equipe.
 *
 * Só monta rascunho em documento que ninguém editou ainda (DocumentEditor::touchedByPerson):
 * depois disso o caminho é o agente sugerir no painel (etapa 4), sem sobrescrever pessoa.
 * As instruções (o "manual") moram aqui; o modelo e a chave são os do Agente Nonna.
 */
class PlanningDrafter
{
    public const TRIGGER    = 'documento:rascunho_planejamento';
    private const MAX_TOKENS = 24000;

    public function __construct(
        private AiService $ai,
        private PlanningSources $sources,
        private DocumentEditor $editor,
    ) {}

    /**
     * @param array<int, string> $keys fontes marcadas
     * @param array<int, string> $uploadIds anexos do planejamento enviados na hora
     * @return array{perguntas: array, used: array, skipped: array}
     */
    public function draft(Document $document, MacroPlan $plan, array $keys, array $uploadIds, int $userId, string $note = ''): array
    {
        if ($this->editor->touchedByPerson($document)) {
            throw new \InvalidArgumentException('Alguém já preencheu este documento: o rascunho do agente só entra em documento ainda não editado.');
        }
        $agent = AiAgent::bySlug(AiAgent::SLUG_NONNA_AGENT);
        if (!$agent) {
            throw new \RuntimeException('O Agente Nonna está inativo.');
        }

        $keys = array_merge($keys, array_map(fn ($id) => "att:macro:{$id}", $uploadIds));
        $gathered = $this->sources->gather($plan, $keys);
        if (!$gathered['blocks'] && !$gathered['files']) {
            throw new \InvalidArgumentException('Nenhuma das fontes marcadas tem conteúdo para ler.');
        }

        $plan->loadMissing('client', 'responsible');
        $message = "Cliente: {$plan->client->company_name}"
            . ($plan->client->nickname ? " (chamado internamente de {$plan->client->nickname})" : '')
            . "\nPeríodo do ciclo: " . $plan->period_start->format('Y-m-d') . ' a ' . $plan->period_end->format('Y-m-d')
            . "\nHoje: " . now()->format('Y-m-d')
            . (trim($note) !== '' ? "\n\nOBSERVAÇÃO DA EQUIPE (vale mais que as fontes quando houver conflito):\n" . trim($note) : '')
            . "\n\nFONTES (leia todas antes de escrever):\n";
        foreach ($gathered['blocks'] as $b) {
            $message .= "\n=== {$b['title']} ===\n{$b['text']}\n";
        }
        if ($gathered['files']) {
            $message .= "\n=== Arquivos anexados a esta mensagem ===\n" . implode(', ', array_column($gathered['files'], 'name')) . "\n";
        }
        // Sempre: o que já está rodando no App, pra não recriar campanha que só continua.
        if ($running = $this->runningItems($plan)) {
            $message .= "\n=== CAMPANHAS E PROJETOS DO CLIENTE QUE JÁ EXISTEM NO APP ===\n{$running}\n";
            $gathered['used'][] = 'Campanhas e projetos em andamento no App';
        }

        $answer = $this->ai->runStructuredWithFiles(
            $agent, $this->instructions($document->schema()), $message, $gathered['files'],
            self::MAX_TOKENS, $userId, $plan->client_id, self::TRIGGER
        );

        $content   = $this->toContent($document->schema(), $answer);
        $perguntas = collect($answer['perguntas'] ?? [])
            ->map(fn ($p) => is_array($p) ? ['secao' => (string) ($p['secao'] ?? ''), 'texto' => trim((string) ($p['texto'] ?? ''))] : ['secao' => '', 'texto' => trim((string) $p)])
            ->filter(fn ($p) => $p['texto'] !== '')->take(10)->values()->all();

        $this->editor->replaceByAgent($document, $content, $userId);

        return ['perguntas' => $perguntas, 'used' => $gathered['used'], 'skipped' => $gathered['skipped']];
    }

    /** Campanhas e projetos do cliente ainda abertos (de qualquer planejamento), em texto. */
    private function runningItems(MacroPlan $plan): string
    {
        $labels = \App\Models\Project::$statuses;

        return \App\Models\Project::where('client_id', $plan->client_id)
            ->whereIn('status', ['em_planejamento', 'aprovacao', 'em_execucao', 'stand_by'])
            ->with('macroPlan:id,title')
            ->orderBy('start_date')
            ->limit(40)
            ->get()
            ->map(fn ($p) => '- ' . ($p->type === 'campanha' ? 'Campanha' : 'Projeto') . ": {$p->title}"
                . ' | situação: ' . ($labels[$p->status]['label'] ?? $p->status)
                . ($p->start_date ? ' | ' . $p->start_date->format('d/m/Y') . ' a ' . ($p->end_date?->format('d/m/Y') ?? '?') : '')
                . ($p->objective ? ' | para que serve: ' . \Illuminate\Support\Str::limit($p->objective, 160) : '')
                . ($p->macroPlan ? " | do planejamento {$p->macroPlan->title}" : ''))
            ->implode("\n");
    }

    /** Resposta da IA -> conteúdo no formato do modelo (ids gerados aqui; índices viram ligações). */
    private function toContent(DocumentSchema $schema, array $a): array
    {
        $content = $schema->emptyContent();
        $field = function (string $key) use ($schema) {
            return $schema->block($key);
        };
        foreach (['objetivo', 'situacao', 'o_que_muda', 'foco', 'estrategia', 'revisao_meio', 'fechamento'] as $key) {
            $content[$key] = $schema->normalize($field($key), $a[$key] ?? null);
        }

        $listFields = fn (string $list) => collect($schema->block($list)['fields'])->keyBy('key');
        $build = function (string $list, array $rows) use ($schema, $listFields) {
            $fields = $listFields($list);
            $items  = [];
            foreach (array_slice($rows, 0, 12) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $item = $schema->newItem($list, $list === 'itens' ? ['tipo' => ($row['tipo'] ?? '') === 'projeto' ? 'projeto' : 'campanha'] : []);
                foreach ($fields as $key => $f) {
                    if ($key === 'tipo' || in_array($f['input'], ['refs', 'ref'], true)) {
                        continue;
                    }
                    $item[$key] = $schema->normalize($f, $row[$key] ?? null);
                }
                $items[] = $item;
            }

            return $items;
        };

        $content['metas']   = $build('metas', $a['metas'] ?? []);
        $content['itens']   = $build('itens', $a['itens'] ?? []);
        $content['fases']   = $build('fases', $a['fases'] ?? []);
        $content['pedidos'] = $build('pedidos', $a['pedidos'] ?? []);

        // Ligações: a IA manda índices (0, 1, ...) das metas e das campanhas.
        $metaIds = array_column($content['metas'], 'id');
        $rawItens = array_values(array_filter($a['itens'] ?? [], 'is_array'));
        foreach ($content['itens'] as $i => &$item) {
            $raw = $rawItens[$i] ?? [];
            $item['metas'] = $this->indexes($raw['metas'] ?? [], $metaIds);
            if ($item['tipo'] === 'projeto') {
                $campaignIds = array_map(fn ($it) => $it['tipo'] === 'campanha' ? $it['id'] : null, $content['itens']);
                $item['prepara'] = $this->indexes($raw['prepara'] ?? [], $campaignIds);
            }
        }
        unset($item);

        return $content;
    }

    private function indexes(mixed $list, array $ids): array
    {
        $out = [];
        foreach ((array) $list as $n) {
            if (is_numeric($n) && isset($ids[(int) $n]) && $ids[(int) $n] !== null) {
                $out[] = $ids[(int) $n];
            }
        }

        return array_values(array_unique($out));
    }

    /** O manual do agente para esta tarefa: regras do modelo v4 + o formato de resposta. */
    private function instructions(DocumentSchema $schema): string
    {
        $helps = collect($schema->sections())->map(fn ($s) => '- ' . trim(($s['num'] ?? '') . ' ' . $s['title']) . ': ' . $s['help'])->implode("\n");

        return <<<TXT
Você é o Agente Nonna, da Nonna Agência Digital. Sua tarefa agora é montar o RASCUNHO do Planejamento do ciclo de um cliente, a partir das fontes que a equipe escolheu (reuniões, ATA, transcrição, planejamento anterior, briefing, arquivos). A equipe revisa e edita tudo depois: seu trabalho é poupar o tempo dela sem inventar nada.

O QUE É O PLANEJAMENTO DO CICLO
O plano de 60 a 90 dias, só no nível estratégico: onde o cliente está, aonde vamos chegar e com quais campanhas e projetos. O detalhe de cada campanha (ângulos, peças, mecânica) NÃO entra aqui: fica no documento de cada campanha.
- Campanha = comunicação com prazo que precisa trazer resultado (ex.: captação de leads de um produto).
- Projeto = algo que fica pronto e permanece (site, CRM, automação, identidade).

AS SEÇÕES DO MODELO (o que vai em cada uma)
{$helps}

REGRAS
1. Use SOMENTE o que está nas fontes. Nunca invente número, data, verba, plataforma ou nome. Se um número de hoje não foi dito, escreva exatamente "A confirmar" e crie um pedido em "pedidos" quando depender do cliente.
2. META É RESULTADO, NÃO TAREFA. Meta é algo que muda para o cliente e dá para medir: mais pedidos, mais vendas, custo menor, mais visitas. "Publicar artigos", "estruturar a campanha", "fazer o site" são tarefas: viram campanha ou projeto, e a meta é o resultado que eles devem trazer. Toda meta tem quanto (alvo) e até quando. "Melhorar" ou "aumentar" sem número não é meta: use o número das fontes; se não houver, alvo "A confirmar" e uma pergunta. Prefira de 1 a 4 metas que importam de verdade.
3. Toda meta é carregada por pelo menos uma campanha ou projeto. Toda campanha carrega pelo menos uma meta. Projeto carrega uma meta ou prepara o terreno para uma campanha.
4. Datas no formato AAAA-MM-DD, dentro do período do ciclo. Revisão do meio do ciclo perto da metade; fechamento perto do fim.
5. Escreva em português do Brasil, do jeito que se fala no dia a dia: frases curtas, sem jargão de marketing, sem exagero. Texto livre só em parágrafos curtos.
6. Nunca use nome de pessoa da equipe da agência como responsável; se precisar, use o papel (Estratégia, Tráfego, Criação, Atendimento).
7. "Onde vemos esse número" (fonte) é a FERRAMENTA ou o sistema onde o número aparece, no começo e no fim do ciclo (ex.: Gerenciador de Anúncios da Meta, Google Ads, GA4, Central de Leads, Perfil da Empresa no Google, planilha do cliente). Nunca é a reunião, a ATA ou um documento: eles são de onde veio a informação, não onde se mede. Se não souber a ferramenta, escreva "A confirmar".
7b. Se o planejamento anterior trouxer resultados ou aprendizados (fechamento), use em "situacao" e "o_que_muda".
8. CAMPANHAS E PROJETOS: antes de criar um, confira a lista "CAMPANHAS E PROJETOS DO CLIENTE QUE JÁ EXISTEM NO APP". Se o assunto já existe e continua neste ciclo, use EXATAMENTE o mesmo nome e não crie outro parecido. Só crie item para o que foi DECIDIDO nas fontes; ideia comentada mas não decidida vira pergunta, não campanha. Prefira poucos itens bem definidos a muitos pequenos: ajustes de rotina (trocar criativo, testar mensagem) ficam dentro da campanha que já existe.
9. "perguntas": de 3 a 8 perguntas objetivas para a equipe responder, sobre o que faltou ou ficou ambíguo nas fontes, cada uma dizendo a seção ("02", "04"...). Nada de pergunta genérica.

FORMATO DA RESPOSTA (um único objeto JSON; listas podem ter quantos itens fizerem sentido)
{
  "objetivo": "o objetivo do ciclo em uma frase",
  "situacao": "situação atual, poucas linhas",
  "o_que_muda": "o que muda neste ciclo, poucas linhas",
  "foco": "uma frase",
  "metas": [{"nome": "", "alvo": "", "ate": "AAAA-MM-DD", "hoje": "número ou A confirmar", "fonte": "ferramenta onde o número aparece (nunca a reunião)"}],
  "estrategia": "um ou dois parágrafos",
  "itens": [{"tipo": "campanha ou projeto", "nome": "", "inicio": "AAAA-MM-DD", "fim": "AAAA-MM-DD", "objetivo": "uma frase",
             "metas": [índices das metas que carrega, começando em 0],
             "metrica": "só campanha: métrica principal: alvo até dd/mm", "plataformas": "só campanha com anúncio: Meta, Google...", "verba_mensal": número ou null,
             "prepara": [só projeto: índices dos itens campanha para os quais prepara o terreno],
             "pronto_quando": "só projeto", "beneficio": "só projeto", "beneficio_em": "só projeto, AAAA-MM-DD"}],
  "fases": [{"quando": "Mês/aaaa", "nome": "", "texto": "o que acontece"}],
  "revisao_meio": "AAAA-MM-DD",
  "fechamento": "AAAA-MM-DD",
  "pedidos": [{"o_que": "o que precisamos do cliente", "ate": "AAAA-MM-DD"}],
  "perguntas": [{"secao": "02", "texto": ""}]
}
TXT;
    }
}
