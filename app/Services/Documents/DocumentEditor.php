<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentRevision;
use Illuminate\Support\Facades\DB;

/**
 * Único caminho de escrita num documento em campos. Cada operação trava a linha do
 * documento (duas pessoas editando ao mesmo tempo não se atropelam: vale campo a campo),
 * valida o caminho contra o modelo, marca quem mudou (pessoa ou agente) e grava o histórico.
 *
 * Regra combinada (23/11): campo que uma PESSOA editou o agente não reescreve sozinho —
 * a marca fica em provenance[caminho].by = 'pessoa' (o agente lê isso na etapa 4).
 */
class DocumentEditor
{
    /** Edições seguidas da mesma pessoa no mesmo campo dentro desta janela viram uma linha só. */
    private const MERGE_MINUTES = 10;

    public function create(string $kind, string $clientId, ?string $documentableType, ?string $documentableId, ?int $userId): Document
    {
        $schema = DocumentSchema::for($kind);

        return Document::create([
            'client_id'         => $clientId,
            'kind'              => $kind,
            'schema_version'    => $schema->version(),
            'documentable_type' => $documentableType,
            'documentable_id'   => $documentableId,
            'content'           => $schema->emptyContent(),
            'provenance'        => [],
            'created_by'        => $userId,
            'updated_by'        => $userId,
        ]);
    }

    public function setField(Document $document, string $path, mixed $value, ?int $userId, string $actor = 'pessoa'): Document
    {
        return $this->mutate($document, function (array &$content, DocumentSchema $schema) use ($path, $value) {
            $target = $schema->resolve($path);
            $value  = $schema->normalize($target['field'], $value);

            if ($target['list'] === null) {
                $before = $content[$target['key']] ?? null;
                $content[$target['key']] = $value;
            } else {
                $index = $this->indexOf($content, $target['list'], $target['item']);
                $item  = $content[$target['list']][$index];
                if (!$schema->applies($target['field'], $item)) {
                    throw new \InvalidArgumentException('Este campo não vale para este tipo de item.');
                }
                $before = $item[$target['key']] ?? null;
                if ($target['field']['input'] === 'refs') {
                    $value = $this->validRefs($content, $schema, $target['field'], $value, $target['item']);
                }
                $content[$target['list']][$index][$target['key']] = $value;
            }

            return $before === $value ? null : ['campo', $path, $before, $value];
        }, $userId, $actor);
    }

    /**
     * Operações nas listas (componentes repetíveis): add, remove, duplicate, up, down, tipo.
     * Remover limpa as referências ao item (ex.: meta apagada sai do "Carrega" das campanhas).
     */
    public function listOp(Document $document, string $list, string $op, ?string $itemId, array $attrs, ?int $userId, string $actor = 'pessoa'): Document
    {
        return $this->mutate($document, function (array &$content, DocumentSchema $schema) use ($list, $op, $itemId, $attrs) {
            $block = $schema->block($list);
            if (!$block || $block['type'] !== 'list') {
                throw new \InvalidArgumentException("Lista desconhecida: {$list}");
            }
            $content[$list] ??= [];

            if ($op === 'add') {
                $allowed = collect($block['add'])->pluck('attrs')->first(fn ($a) => $a == array_intersect_key($attrs, $a)) ?? [];
                $item = $schema->newItem($list, $allowed);
                $content[$list][] = $item;

                return ['adicionar', "{$list}.{$item['id']}", null, $item];
            }

            $index = $this->indexOf($content, $list, $itemId);
            $item  = $content[$list][$index];

            switch ($op) {
                case 'remove':
                    array_splice($content[$list], $index, 1);
                    $this->dropRefs($content, $schema, $itemId);

                    return ['remover', "{$list}.{$itemId}", $item, null];

                case 'duplicate':
                    $copy = array_merge($item, ['id' => $schema->newItem($list)['id']]);
                    if (isset($copy[$block['title_field']]) && $copy[$block['title_field']] !== null) {
                        $copy[$block['title_field']] .= ' (cópia)';
                    }
                    array_splice($content[$list], $index + 1, 0, [$copy]);

                    return ['duplicar', "{$list}.{$copy['id']}", null, $copy];

                case 'up':
                case 'down':
                    $to = $op === 'up' ? $index - 1 : $index + 1;
                    if ($to < 0 || $to >= count($content[$list])) {
                        return null;
                    }
                    [$content[$list][$index], $content[$list][$to]] = [$content[$list][$to], $content[$list][$index]];

                    return ['mover', "{$list}.{$itemId}", $index, $to];

                case 'tipo':
                    $switch = $block['switch'] ?? null;
                    $field  = $switch ? collect($block['fields'])->firstWhere('key', $switch) : null;
                    $value  = $attrs[$switch] ?? null;
                    if (!$field || !array_key_exists($value, $field['options'])) {
                        throw new \InvalidArgumentException('Tipo inválido.');
                    }
                    $before = $item[$switch];
                    $content[$list][$index][$switch] = $value;
                    // Campanha deixa de ser campanha: projetos que preparavam o terreno pra ela perdem a ligação.
                    if ($value !== 'campanha') {
                        $this->dropRefs($content, $schema, $itemId, 'prepara');
                    }

                    return ['tipo', "{$list}.{$itemId}.{$switch}", $before, $value];
            }

            throw new \InvalidArgumentException("Operação desconhecida: {$op}");
        }, $userId, $actor);
    }

    /** Tudo que a tela precisa depois de uma operação. */
    public function payload(Document $document): array
    {
        $schema = $document->schema();
        $issues = $schema->issues($document->content);

        return [
            'content'    => $document->content,
            'provenance' => (object) ($document->provenance ?? []),
            'issues'     => $issues,
            'statuses'   => $schema->sectionStatuses($document->content, $issues),
            'blocking'   => (bool) array_filter($issues, fn ($i) => $i['level'] === 'erro'),
            'saved_at'   => $document->updated_at?->toIso8601String(),
        ];
    }

    private function mutate(Document $document, callable $change, ?int $userId, string $actor): Document
    {
        return DB::connection('pgsql')->transaction(function () use ($document, $change, $userId, $actor) {
            $fresh   = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $content = $fresh->content ?? [];
            $result  = $change($content, $fresh->schema());

            if ($result === null) {
                return $fresh;
            }
            [$action, $path, $before, $after] = $result;

            $provenance = $fresh->provenance ?? [];
            $provenance[$path] = ['by' => $actor, 'user_id' => $userId, 'at' => now()->toIso8601String()];
            if ($action === 'remover') {
                $provenance = array_filter($provenance, fn ($k) => !str_starts_with($k, $path . '.') && $k !== $path, ARRAY_FILTER_USE_KEY);
            }

            $fresh->update(['content' => $content, 'provenance' => $provenance, 'updated_by' => $userId]);
            $this->log($fresh, $userId, $actor, $action, $path, $before, $after);

            return $fresh;
        });
    }

    private function log(Document $document, ?int $userId, string $actor, string $action, string $path, mixed $before, mixed $after): void
    {
        if ($action === 'campo') {
            $last = DocumentRevision::where('document_id', $document->id)->latest('updated_at')->first();
            if ($last && $last->action === 'campo' && $last->path === $path && $last->user_id === $userId
                && $last->actor === $actor && $last->updated_at->gt(now()->subMinutes(self::MERGE_MINUTES))) {
                $last->update(['after' => ['v' => $after]]);

                return;
            }
        }

        DocumentRevision::create([
            'document_id' => $document->id,
            'user_id'     => $userId,
            'actor'       => $actor,
            'action'      => $action,
            'path'        => $path,
            'before'      => ['v' => $before],
            'after'       => ['v' => $after],
        ]);
    }

    private function indexOf(array $content, string $list, ?string $itemId): int
    {
        foreach ($content[$list] ?? [] as $i => $item) {
            if (($item['id'] ?? null) === $itemId) {
                return $i;
            }
        }

        throw new \InvalidArgumentException('Item não encontrado (talvez alguém tenha removido). Recarregue a página.');
    }

    /** Só referências a itens que existem na lista de origem e passam no filtro (ex.: só campanhas). */
    private function validRefs(array $content, DocumentSchema $schema, array $field, array $ids, string $selfId): array
    {
        $valid = [];
        foreach ($content[$field['refs']] ?? [] as $item) {
            if ($item['id'] === $selfId) {
                continue;
            }
            foreach ($field['refs_filter'] ?? [] as $k => $v) {
                if (($item[$k] ?? null) !== $v) {
                    continue 2;
                }
            }
            $valid[] = $item['id'];
        }

        return array_values(array_intersect($ids, $valid));
    }

    private function dropRefs(array &$content, DocumentSchema $schema, string $itemId, ?string $onlyField = null): void
    {
        foreach ($schema->sections() as $section) {
            foreach ($section['blocks'] as $block) {
                if ($block['type'] !== 'list') {
                    continue;
                }
                foreach ($block['fields'] as $field) {
                    if ($field['input'] !== 'refs' || ($onlyField && $field['key'] !== $onlyField)) {
                        continue;
                    }
                    foreach ($content[$block['key']] ?? [] as $i => $item) {
                        $refs = $item[$field['key']] ?? [];
                        if (in_array($itemId, $refs, true)) {
                            $content[$block['key']][$i][$field['key']] = array_values(array_diff($refs, [$itemId]));
                        }
                    }
                }
            }
        }
    }
}
