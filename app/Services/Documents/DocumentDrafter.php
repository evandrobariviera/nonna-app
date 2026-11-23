<?php

namespace App\Services\Documents;

use App\Models\AiAgent;
use App\Models\Document;
use App\Models\Project;
use App\Services\AiService;

/**
 * Rascunho da CAMPANHA ou do PROJETO pelo Agente Nonna (23/11, etapa C). Igual ao do planejamento,
 * com uma diferença: o documento já nasce com o que herdou do plano. O agente só COMPLETA o que
 * está vazio (campos vazios e listas sem conteúdo); o que veio do planejamento fica como está.
 * O formato de resposta é gerado do próprio modelo (SchemaPrompt), então serve a qualquer tipo.
 */
class DocumentDrafter
{
    public const TRIGGER     = 'documento:rascunho_item';
    private const MAX_TOKENS = 24000;

    public function __construct(private AiService $ai, private PlanningSources $sources, private DocumentEditor $editor) {}

    /** @return array{perguntas: array, used: array, skipped: array} */
    public function draft(Document $document, Project $project, array $keys, array $uploadIds, int $userId, string $note = ''): array
    {
        if ($this->editor->touchedByPerson($document)) {
            throw new \InvalidArgumentException('Alguém já editou este documento: o rascunho do agente só entra em documento ainda não editado.');
        }
        $agent = AiAgent::bySlug(AiAgent::SLUG_NONNA_AGENT) ?? throw new \RuntimeException('O Agente Nonna está inativo.');

        $keys = array_merge($keys, array_map(fn ($id) => "att:project:{$id}", $uploadIds));
        $gathered = $this->sources->gatherForProject($project, $keys);
        if (!$gathered['blocks'] && !$gathered['files']) {
            throw new \InvalidArgumentException('Nenhuma das fontes marcadas tem conteúdo para ler.');
        }

        $schema = $document->schema();
        $project->loadMissing('client');
        $message = "Cliente: {$project->client->company_name}"
            . "\n" . ($project->type === 'campanha' ? 'Campanha' : 'Projeto') . ": {$project->title}"
            . "\nHoje: " . now()->format('Y-m-d')
            . "\n\nDOCUMENTO COMO ESTÁ (o que já tem valor veio do planejamento: NÃO repita nem mude):\n"
            . json_encode($document->content, JSON_UNESCAPED_UNICODE)
            . (trim($note) !== '' ? "\n\nOBSERVAÇÃO DA EQUIPE (vale mais que as fontes quando houver conflito):\n" . trim($note) : '')
            . "\n\nFONTES:\n";
        foreach ($gathered['blocks'] as $b) {
            $message .= "\n=== {$b['title']} ===\n{$b['text']}\n";
        }
        if ($gathered['files']) {
            $message .= "\n=== Arquivos anexados a esta mensagem ===\n" . implode(', ', array_column($gathered['files'], 'name')) . "\n";
        }

        $answer = $this->ai->runStructuredWithFiles($agent, $this->instructions($schema), $message, $gathered['files'],
            self::MAX_TOKENS, $userId, $project->client_id, self::TRIGGER);

        [$fields, $lists] = $this->toValues($schema, $document->content, $answer);
        $this->editor->fillEmptyByAgent($document, $fields, $lists, $userId);

        $perguntas = collect($answer['perguntas'] ?? [])
            ->map(fn ($p) => is_array($p) ? ['secao' => (string) ($p['secao'] ?? ''), 'texto' => trim((string) ($p['texto'] ?? ''))] : ['secao' => '', 'texto' => trim((string) $p)])
            ->filter(fn ($p) => $p['texto'] !== '')->take(10)->values()->all();

        return ['perguntas' => $perguntas, 'used' => $gathered['used'], 'skipped' => $gathered['skipped']];
    }

    /** Resposta -> [campos simples, listas completas], normalizados pelo modelo; ligações por índice. */
    private function toValues(DocumentSchema $schema, array $current, array $a): array
    {
        $fields = [];
        $lists  = [];
        $rawLists = (array) ($a['listas'] ?? []);

        // 1) listas sem ligações resolvidas
        foreach ($schema->sections() as $section) {
            foreach ($section['blocks'] as $b) {
                if ($b['type'] === 'field') {
                    if (array_key_exists($b['key'], (array) ($a['campos'] ?? [])) && !in_array($b['input'], ['ref', 'refs'], true)) {
                        $fields[$b['key']] = $schema->normalize($b, $a['campos'][$b['key']]);
                    }
                    continue;
                }
                $rows = array_values(array_filter((array) ($rawLists[$b['key']] ?? []), 'is_array'));
                if (!$rows) {
                    continue;
                }
                $items = [];
                foreach (array_slice($rows, 0, $b['max'] ?? 12) as $row) {
                    $attrs = isset($b['switch'], $row[$b['switch']]) ? [$b['switch'] => $row[$b['switch']]] : [];
                    $item  = $schema->newItem($b['key'], $attrs);
                    foreach ($b['fields'] as $f) {
                        if ($f['key'] === ($b['switch'] ?? null) || in_array($f['input'], ['ref', 'refs'], true)) {
                            continue;
                        }
                        if (array_key_exists($f['key'], $row)) {
                            $item[$f['key']] = $schema->normalize($f, $row[$f['key']]);
                        }
                    }
                    $items[] = ['item' => $item, 'raw' => $row];
                }
                $lists[$b['key']] = $items;
            }
        }

        // 2) ligações: índice na lista final (a nova, se veio; senão a que já existe)
        $finalIds = fn (string $list) => isset($lists[$list])
            ? array_map(fn ($x) => $x['item']['id'], $lists[$list])
            : array_column($current[$list] ?? [], 'id');
        foreach ($lists as $key => &$entries) {
            $block = $schema->block($key);
            foreach ($entries as &$e) {
                foreach ($block['fields'] as $f) {
                    if (!in_array($f['input'], ['ref', 'refs'], true) || !array_key_exists($f['key'], $e['raw'])) {
                        continue;
                    }
                    $ids = $finalIds($f['refs']);
                    $pick = fn ($n) => is_numeric($n) && isset($ids[(int) $n]) ? $ids[(int) $n] : null;
                    $e['item'][$f['key']] = $f['input'] === 'ref'
                        ? $pick($e['raw'][$f['key']])
                        : array_values(array_unique(array_filter(array_map($pick, (array) $e['raw'][$f['key']]))));
                }
            }
            unset($e);
            $entries = array_map(fn ($x) => $x['item'], $entries);
        }
        unset($entries);

        return [$fields, $lists];
    }

    private function instructions(DocumentSchema $schema): string
    {
        $map   = SchemaPrompt::map($schema);
        $title = $schema->title();

        return <<<TXT
Você é o Agente Nonna, da Nonna Agência Digital. Sua tarefa agora é COMPLETAR o rascunho de um documento padrão: {$title}. O documento já nasceu com o que veio do planejamento; você preenche o que está vazio, a partir das fontes que a equipe escolheu. A equipe revisa tudo depois.

O MODELO DO DOCUMENTO (use exatamente estas chaves)
{$map}

REGRAS
1. Preencha SÓ o que está vazio no "DOCUMENTO COMO ESTÁ". O que já tem valor veio do planejamento: não repita e não mude.
2. DADOS você nunca inventa: número, data, verba, prazo, nome de pessoa, depoimento, fato sobre o cliente. Número que não aparece: "A confirmar"; data que ninguém deu: null. O que faltar vira pergunta.
3. O QUE É CRIATIVO OU ESTRATÉGICO você PROPÕE, mesmo que a reunião não tenha detalhado: ângulos criativos (de 3 a 6), frase do conceito, mecânica (o caminho da pessoa), o que cada área faz, peças, sinais de alerta, escopo e entregas de projeto. Proponha a partir do briefing (quem compra), do planejamento e da reunião, coerente com o objetivo. É rascunho: a equipe revisa e troca o que não servir. Só deixe vazio o que realmente depende de uma decisão que ninguém tomou (ex.: verba).
4. Campanha: os ângulos criativos precisam ser ideias realmente diferentes (dois ângulos nunca com o mesmo motivo e o mesmo momento); os ganchos são só as primeiras frases do anúncio, uma por linha; a primeira peça da lista é a peça conceito; toda peça que é anúncio diz de qual ângulo é.
4b. "Onde vemos esse número" (fonte) é a FERRAMENTA ou o sistema onde o número aparece, no começo e no fim do ciclo (ex.: Gerenciador de Anúncios da Meta, Google Ads, GA4, Central de Leads, Perfil da Empresa no Google, planilha do cliente). Nunca é a reunião, a ATA ou um documento: eles são de onde veio a informação, não onde se mede. Se não souber a ferramenta, escreva "A confirmar".
5. Sinais de alerta fáceis de ver, com prazo ("7 dias sem nenhum pedido"); quem age é um papel (Tráfego, Atendimento, Gestor de Projetos), nunca nome de pessoa.
6. Português do dia a dia: frases curtas, sem jargão, sem exagero.
7. "perguntas": de 3 a 8 perguntas objetivas sobre o que faltou, com a seção ("01", "02"...).

FORMATO DA RESPOSTA (um único objeto JSON)
{
  "campos": {"chave": valor, ...},                         // só os campos simples que você preencheu
  "listas": {"chave_da_lista": [{"campo": valor, ...}]},   // só as listas que estavam vazias e você preencheu
  "perguntas": [{"secao": "02", "texto": "..."}]
}
Ligações (campos de "ligação"): use o ÍNDICE do item (0, 1, 2...) na lista final — a sua, se você preencheu a lista; senão a que já existe no documento.
TXT;
    }
}
