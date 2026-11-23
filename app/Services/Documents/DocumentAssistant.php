<?php

namespace App\Services\Documents;

use App\Models\AiAgent;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Support\Str;

/**
 * Agente Nonna ao lado do documento (23/11, etapa 4). A equipe conversa; o agente responde e
 * PROPÕE mudanças (nunca escreve direto). Cada proposta é um conjunto de operações no modelo
 * (definir campo, adicionar/remover item, trocar tipo) que a pessoa aceita ou descarta.
 *
 * A conversa e as propostas moram em documents.agent_state (chat, compartilhado pela equipe).
 * Reunião escolhida entra como fonte: a ATA inteira + os trechos da transcrição ligados à pergunta
 * (busca por palavra feita aqui, sem a IA ler a transcrição toda a cada mensagem).
 */
class DocumentAssistant
{
    public const TRIGGER     = 'documento:agente';
    private const MAX_TOKENS = 12000;
    private const KEEP       = 40;   // mensagens guardadas na conversa
    private const HISTORY    = 8;    // mensagens que voltam para a IA

    public function __construct(private AiService $ai, private DocumentEditor $editor) {}

    /** @return array{chat: array} */
    public function ask(Document $document, User $user, string $message, ?string $meetingId): array
    {
        $agent = AiAgent::bySlug(AiAgent::SLUG_NONNA_AGENT);
        if (!$agent) {
            throw new \RuntimeException('O Agente Nonna está inativo.');
        }
        $schema = $document->schema();
        $state  = $document->agent_state ?? [];
        $chat   = $state['chat'] ?? [];

        $meeting = $meetingId ? Meeting::where('client_id', $document->client_id)->find($meetingId) : null;

        $context = "DOCUMENTO ATUAL (JSON, com os ids dos itens):\n" . json_encode($document->content, JSON_UNESCAPED_UNICODE)
            . "\n\nCAMPOS QUE UMA PESSOA JÁ EDITOU (só proponha mudar se pedirem ou se houver motivo forte, e diga qual):\n"
            . (implode(', ', $this->personPaths($document)) ?: 'nenhum')
            . "\n\nAVISOS DO MODELO AGORA:\n" . (collect($schema->issues($document->content))->map(fn ($i) => "- [{$i['level']}] {$i['text']}")->implode("\n") ?: 'nenhum');
        if ($meeting) {
            $context .= "\n\nREUNIÃO ESCOLHIDA: {$meeting->title} (" . $meeting->scheduled_at?->format('d/m/Y') . ")\nATA:\n"
                . mb_substr(trim(SourceText::html((string) $meeting->ata)), 0, 30000);
            if ($excerpts = $this->excerpts((string) $meeting->transcricao, $message)) {
                $context .= "\n\nTRECHOS DA TRANSCRIÇÃO LIGADOS À PERGUNTA:\n{$excerpts}";
            }
        }

        $history = collect($chat)->take(-self::HISTORY)->map(fn ($m) => ($m['role'] === 'user' ? "Equipe ({$m['name']})" : 'Agente') . ': ' . $m['text'])->implode("\n");
        $userMessage = $context . ($history ? "\n\nCONVERSA ATÉ AGORA:\n{$history}" : '') . "\n\nPEDIDO AGORA ({$user->name}): {$message}";

        $answer = $this->ai->runStructuredWithFiles($agent, $this->instructions($schema), $userMessage, [], self::MAX_TOKENS,
            $user->id, $document->client_id, self::TRIGGER);

        $suggestions = [];
        foreach (array_slice((array) ($answer['sugestoes'] ?? []), 0, 6) as $s) {
            $ops = array_values(array_filter((array) ($s['ops'] ?? []), fn ($o) => is_array($o) && isset($o['op'])));
            if (!$ops) {
                continue;
            }
            $suggestions[] = [
                'id'     => Str::lower(Str::random(8)),
                'resumo' => Str::limit(trim((string) ($s['resumo'] ?? 'Mudança sugerida')), 200),
                'motivo' => Str::limit(trim((string) ($s['motivo'] ?? '')), 400),
                'ops'    => array_slice($ops, 0, 20),
                'status' => 'pendente',
            ];
        }

        $now = now()->toIso8601String();
        $chat[] = ['role' => 'user', 'name' => $user->name, 'user_id' => $user->id, 'text' => Str::limit($message, 4000), 'at' => $now,
            'meeting' => $meeting ? "{$meeting->title} (" . $meeting->scheduled_at?->format('d/m/Y') . ')' : null];
        $chat[] = ['role' => 'agent', 'text' => trim((string) ($answer['resposta'] ?? '')) ?: 'Pronto.', 'at' => now()->toIso8601String(), 'sugestoes' => $suggestions];
        $state['chat'] = array_slice($chat, -self::KEEP);
        Document::whereKey($document->id)->update(['agent_state' => json_encode($state, JSON_UNESCAPED_UNICODE)]);

        return ['chat' => $state['chat']];
    }

    /**
     * Aplica uma proposta (a pessoa clicou Aceitar). Cada operação passa pelo DocumentEditor, que
     * valida contra o modelo; o que não couber é pulado e avisado.
     * @return array{document: Document, skipped: array, chat: array}
     */
    public function decide(Document $document, string $suggestionId, bool $accept, User $user): array
    {
        $state = $document->agent_state ?? [];
        [$mi, $si] = $this->locate($state, $suggestionId);
        $suggestion = $state['chat'][$mi]['sugestoes'][$si];
        if ($suggestion['status'] !== 'pendente') {
            throw new \InvalidArgumentException('Esta sugestão já foi decidida.');
        }

        $skipped = [];
        if ($accept) {
            foreach ($suggestion['ops'] as $op) {
                try {
                    $document = $this->apply($document, $op, $user->id);
                } catch (\InvalidArgumentException $e) {
                    $skipped[] = $e->getMessage();
                }
            }
        }

        $state = $document->fresh()->agent_state ?? $state;
        [$mi, $si] = $this->locate($state, $suggestionId);
        $state['chat'][$mi]['sugestoes'][$si]['status'] = $accept ? 'aceita' : 'descartada';
        $state['chat'][$mi]['sugestoes'][$si]['by'] = $user->name;
        Document::whereKey($document->id)->update(['agent_state' => json_encode($state, JSON_UNESCAPED_UNICODE)]);

        return ['document' => $document->fresh(), 'skipped' => $skipped, 'chat' => $state['chat']];
    }

    private function apply(Document $document, array $op, int $userId): Document
    {
        $schema = $document->schema();

        return match ($op['op']) {
            'set'    => $this->editor->setField($document, (string) ($op['path'] ?? ''), $op['value'] ?? null, $userId, 'agente'),
            'remove' => $this->editor->listOp($document, (string) ($op['list'] ?? ''), 'remove', (string) ($op['item'] ?? ''), [], $userId, 'agente'),
            'tipo'   => $this->editor->listOp($document, (string) ($op['list'] ?? ''), 'tipo', (string) ($op['item'] ?? ''),
                [($schema->block((string) ($op['list'] ?? ''))['switch'] ?? 'tipo') => $op['value'] ?? null], $userId, 'agente'),
            'add'    => $this->add($document, (string) ($op['list'] ?? ''), (array) ($op['values'] ?? []), $userId),
            default  => throw new \InvalidArgumentException("Operação desconhecida: {$op['op']}"),
        };
    }

    private function add(Document $document, string $list, array $values, int $userId): Document
    {
        $block  = $document->schema()->block($list) ?? throw new \InvalidArgumentException("Lista desconhecida: {$list}");
        $switch = $block['switch'] ?? null;
        $attrs  = $switch && isset($values[$switch]) ? [$switch => $values[$switch]] : [];
        $document = $this->editor->listOp($document, $list, 'add', null, $attrs, $userId, 'agente');
        $id = array_slice($document->content[$list], -1)[0]['id'];
        foreach ($values as $key => $value) {
            if ($key === $switch || $key === 'id') {
                continue;
            }
            try {
                $document = $this->editor->setField($document, "{$list}.{$id}.{$key}", $value, $userId, 'agente');
            } catch (\InvalidArgumentException) {
                // campo que não vale para este tipo de item: ignora
            }
        }

        return $document;
    }

    private function locate(array $state, string $suggestionId): array
    {
        foreach ($state['chat'] ?? [] as $mi => $m) {
            foreach ($m['sugestoes'] ?? [] as $si => $s) {
                if ($s['id'] === $suggestionId) {
                    return [$mi, $si];
                }
            }
        }
        throw new \InvalidArgumentException('Sugestão não encontrada.');
    }

    private function personPaths(Document $document): array
    {
        return array_keys(array_filter($document->provenance ?? [], fn ($m) => ($m['by'] ?? null) === 'pessoa'));
    }

    /** Trechos da transcrição com as palavras da pergunta (janelas de ~1.200 caracteres, até 4). */
    private function excerpts(string $transcript, string $question): string
    {
        if (trim($transcript) === '') {
            return '';
        }
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)))
            ->filter(fn ($w) => mb_strlen($w) >= 5)->unique()->take(8);
        $lower = mb_strtolower($transcript);
        $hits  = [];
        foreach ($words as $w) {
            $pos = 0;
            while (($pos = mb_strpos($lower, $w, $pos)) !== false && count($hits) < 30) {
                $hits[] = $pos;
                $pos += mb_strlen($w);
            }
        }
        if (!$hits) {
            return '';
        }
        // Janelas onde as palavras se concentram.
        $scores = [];
        foreach ($hits as $p) {
            $bucket = intdiv($p, 1200);
            $scores[$bucket] = ($scores[$bucket] ?? 0) + 1;
        }
        arsort($scores);
        $out = [];
        foreach (array_slice(array_keys($scores), 0, 4) as $bucket) {
            $out[$bucket] = '…' . trim(mb_substr($transcript, max(0, $bucket * 1200 - 200), 1600)) . '…';
        }
        ksort($out);

        return implode("\n\n", $out);
    }

    private function instructions(DocumentSchema $schema): string
    {
        $map = [];
        foreach ($schema->sections() as $section) {
            $lines = [];
            foreach ($section['blocks'] as $b) {
                if ($b['type'] === 'field') {
                    $lines[] = "  campo \"{$b['key']}\" = {$b['label']} [" . $this->type($b) . ']';
                } else {
                    $fields = collect($b['fields'])->map(fn ($f) => "{$f['key']}={$f['label']} [" . $this->type($f) . ']'
                        . (isset($f['only']) ? ' (só ' . implode('/', $f['only']) . ')' : ''))->implode('; ');
                    $lines[] = "  lista \"{$b['key']}\" (cada item: {$b['item_label']}): {$fields}";
                }
            }
            $out = '- ' . trim(($section['num'] ?? '') . ' ' . $section['title']) . ': ' . $section['help'] . "\n" . implode("\n", $lines);
            $map[] = $out;
        }
        $map = implode("\n", $map);
        $title = $schema->title();

        return <<<TXT
Você é o Agente Nonna, da Nonna Agência Digital, trabalhando ao lado da equipe num documento padrão: {$title}. A equipe conversa com você para ajustar o documento. Você responde e PROPÕE mudanças; quem decide é a pessoa (cada proposta tem Aceitar ou Descartar). Nunca diga que mudou algo: diga o que está propondo.

O MODELO DO DOCUMENTO (seções, campos e listas; use exatamente estas chaves)
{$map}

COMO RESPONDER
1. "resposta": curta, em português do dia a dia, direto ao ponto (até 6 frases). Se a pergunta for só uma dúvida, responda sem propor mudança.
2. "sugestoes": de 0 a 6 propostas. Cada uma agrupa mudanças que fazem sentido juntas, com "resumo" (o que muda, numa frase) e "motivo" (por quê; cite a reunião ou a fonte quando houver).
3. Operações ("ops"):
   {"op": "set", "path": "chave" ou "lista.ID.chave", "value": valor}  (datas AAAA-MM-DD; dinheiro em número; ligações = lista de IDs de itens existentes)
   {"op": "add", "list": "lista", "values": {"chave": valor, ...}}  (em "itens", diga "tipo": "campanha" ou "projeto")
   {"op": "remove", "list": "lista", "item": "ID"}
   {"op": "tipo", "list": "itens", "item": "ID", "value": "campanha" ou "projeto"}
   Use só IDs que existem no DOCUMENTO ATUAL. Para juntar dois itens: ajuste um com "set" e remova o outro.
4. Use só o que está no documento, na conversa e na reunião escolhida. Nunca invente número, data ou verba. Nunca proponha APAGAR um campo por falta de informação: se o número não existe, o valor é "A confirmar" (e, se depender do cliente, proponha um pedido na lista de pedidos).
5. Respeite o que uma pessoa já editou: só proponha mudar esses campos se pedirem ou se houver motivo forte, e diga isso no motivo.
6. Regras do modelo: meta é RESULTADO medível, não tarefa; toda meta ajudada por pelo menos uma campanha ou projeto; campanha que continua é a mesma campanha; quem age é um papel, nunca nome de pessoa.

FORMATO: {"resposta": "...", "sugestoes": [{"resumo": "...", "motivo": "...", "ops": [...]}]}
TXT;
    }

    private function type(array $f): string
    {
        return match ($f['input']) {
            'select' => 'um de: ' . implode(', ', array_keys($f['options'] ?? [])),
            'refs'   => 'lista de IDs de "' . $f['refs'] . '"',
            'ref'    => 'ID de um item de "' . $f['refs'] . '"',
            'date'   => 'data',
            'money'  => 'número em reais',
            'number' => 'número',
            'text'   => 'texto, parágrafos',
            default  => 'texto curto',
        };
    }
}
