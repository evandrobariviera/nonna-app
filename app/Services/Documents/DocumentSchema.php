<?php

namespace App\Services\Documents;

use App\Services\Documents\Schemas\PlanejamentoSchema;
use Illuminate\Support\Str;

/**
 * O MODELO de um tipo de documento, descrito em código: seções, campos, componentes
 * repetíveis (listas) e regras. É a "forma" da regra forma-e-mérito — a tela, a versão do
 * cliente e (depois) o agente leem daqui; ninguém cria componente que não esteja aqui.
 *
 * Bloco de seção:
 *   campo: ['type' => 'field', 'key', 'label', 'input' => line|text|date|money|number|select|ref|refs,
 *           'placeholder'?, 'required'?, 'options'? (select), 'refs'? (lista de origem),
 *           'refs_filter'? (['tipo' => 'campanha']), 'only'? (['tipo' => 'projeto']), 'half'?]
 *   lista: ['type' => 'list', 'key', 'item_label', 'title_field', 'fields' => [campos...],
 *           'add' => [['label', 'attrs' => [...]], ...], 'switch'? => campo select que troca o tipo,
 *           'min'?, 'max'? (quantos itens o modelo pede), 'start'? (itens com que o documento nasce)]
 *   ref = escolhe UM item de outra lista (guarda o id); refs = vários.
 *   user = uma pessoa da equipe (guarda o id do usuário). Só PESSOA escolhe; o agente não preenche nem propõe.
 *
 * Conteúdo: valores simples por chave; lista = array de itens com 'id' próprio.
 * Caminho de um campo: "chave" ou "lista.<id do item>.chave".
 */
abstract class DocumentSchema
{
    public const PENDING = 'A confirmar';

    abstract public function kind(): string;

    abstract public function version(): int;

    /** @return array<int, array{key:string, num:?string, title:string, help:string, blocks:array}> */
    abstract public function sections(): array;

    /** Regras próprias do tipo (além de obrigatório vazio e "A confirmar"). */
    abstract protected function rules(array $content): array;

    /** View Blade da versão do cliente (recebe $document, $content, $schema, $owner). */
    abstract public function clientView(): string;

    /** Nome do documento na tela ("Documento da campanha"). */
    abstract public function title(): string;

    /**
     * Campos do documento que espelham uma coluna do dono (ex.: nome da campanha -> projects.title),
     * pra o resto do App (listas, linha do tempo, tarefas) continuar lendo de onde sempre leu.
     * O documento manda: editar aqui atualiza lá.
     */
    public function mirrors(): array
    {
        return [];
    }

    /** O modelo tem campo de pessoa da equipe? (a tela só busca a equipe quando precisa) */
    public function hasUserField(): bool
    {
        foreach ($this->sections() as $section) {
            foreach ($section['blocks'] as $block) {
                $fields = $block['type'] === 'list' ? $block['fields'] : [$block];
                if (collect($fields)->contains('input', 'user')) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function for(string $kind): self
    {
        return match ($kind) {
            'planejamento' => new PlanejamentoSchema(),
            'campanha'     => new Schemas\CampanhaSchema(),
            'projeto'      => new Schemas\ProjetoSchema(),
            default        => throw new \InvalidArgumentException("Tipo de documento desconhecido: {$kind}"),
        };
    }

    /** Conteúdo de um documento novo, com os itens iniciais do modelo. */
    public function emptyContent(): array
    {
        $content = [];
        foreach ($this->sections() as $section) {
            foreach ($section['blocks'] as $block) {
                $content[$block['key']] = $block['type'] === 'list'
                    ? array_map(fn ($attrs) => $this->newItem($block['key'], $attrs), $block['start'] ?? [])
                    : null;
            }
        }

        return $content;
    }

    public function block(string $key): ?array
    {
        foreach ($this->sections() as $section) {
            foreach ($section['blocks'] as $block) {
                if ($block['key'] === $key) {
                    return $block + ['section' => $section['key']];
                }
            }
        }

        return null;
    }

    public function newItem(string $listKey, array $attrs = []): array
    {
        $list = $this->block($listKey);
        if (!$list || $list['type'] !== 'list') {
            throw new \InvalidArgumentException("Lista desconhecida: {$listKey}");
        }

        $item = ['id' => Str::lower(Str::random(8))];
        foreach ($list['fields'] as $field) {
            $item[$field['key']] = $field['input'] === 'refs' ? [] : ($field['default'] ?? null);
        }

        return array_merge($item, array_intersect_key($attrs, $item));
    }

    /**
     * Resolve um caminho contra o modelo.
     * @return array{field: array, list: ?string, item: ?string, key: string}
     */
    public function resolve(string $path): array
    {
        $parts = explode('.', $path);
        if (count($parts) === 1) {
            $block = $this->block($parts[0]);
            if ($block && $block['type'] === 'field') {
                return ['field' => $block, 'list' => null, 'item' => null, 'key' => $parts[0]];
            }
        } elseif (count($parts) === 3) {
            $block = $this->block($parts[0]);
            if ($block && $block['type'] === 'list') {
                foreach ($block['fields'] as $field) {
                    if ($field['key'] === $parts[2]) {
                        return ['field' => $field, 'list' => $parts[0], 'item' => $parts[1], 'key' => $parts[2]];
                    }
                }
            }
        }

        throw new \InvalidArgumentException("Campo que não existe no modelo: {$path}");
    }

    /** Normaliza o valor pelo tipo do campo (o que a tela manda nem sempre vem limpo). */
    public function normalize(array $field, mixed $value): mixed
    {
        switch ($field['input']) {
            case 'refs':
                return array_values(array_unique(array_filter(array_map('strval', (array) $value))));
            case 'money':
                if ($value === null || $value === '') {
                    return null;
                }
                $num = is_numeric($value) ? (float) $value : \App\Services\MacroPlanBudgetSync::parseMoney((string) $value);

                return $num === null ? null : round($num, 2);
            case 'number':
                if ($value === null || $value === '' || !is_numeric($value)) {
                    return null;
                }

                return $value + 0;
            case 'ref':
                return filled($value) ? (string) $value : null;
            case 'user':
                return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0 ? (int) $value : null;
            case 'date':
                $value = trim((string) $value);

                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
            case 'select':
                return in_array($value, array_keys($field['options'] ?? []), true) ? $value : null;
            default:
                $value = trim(str_replace("\r\n", "\n", (string) $value));

                return $value === '' ? null : mb_substr($value, 0, ($field['input'] === 'text') ? 8000 : 500);
        }
    }

    /** O campo vale para este item? (ex.: "Pronto quando" só em projeto) */
    public function applies(array $field, ?array $item): bool
    {
        foreach ($field['only'] ?? [] as $key => $value) {
            if (($item[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Avisos do documento. Nível "erro" (regra do modelo quebrada) segura a saída do
     * rascunho; "falta" (vazio obrigatório, "A confirmar") só avisa.
     * @return array<int, array{section:string, level:string, text:string, path:?string}>
     */
    public function issues(array $content): array
    {
        $issues = [];
        foreach ($this->sections() as $section) {
            foreach ($section['blocks'] as $block) {
                if ($block['type'] === 'field') {
                    $issues = array_merge($issues, $this->fieldIssues($section['key'], $block, $content[$block['key']] ?? null, $block['key'], null));
                    continue;
                }
                $count = count($content[$block['key']] ?? []);
                if (isset($block['min']) && $count < $block['min']) {
                    $issues[] = ['section' => $section['key'], 'level' => 'falta', 'path' => null,
                        'text' => "{$block['item_label']}: o modelo pede pelo menos {$block['min']} (tem {$count})."];
                }
                if (isset($block['max']) && $count > $block['max']) {
                    $issues[] = ['section' => $section['key'], 'level' => 'erro', 'path' => null,
                        'text' => "{$block['item_label']}: o modelo aceita no máximo {$block['max']} (tem {$count})."];
                }
                foreach ($content[$block['key']] ?? [] as $item) {
                    $title = trim((string) ($item[$block['title_field']] ?? '')) ?: $block['item_label'] . ' sem nome';
                    foreach ($block['fields'] as $field) {
                        if (!$this->applies($field, $item)) {
                            continue;
                        }
                        $issues = array_merge($issues, $this->fieldIssues(
                            $section['key'], $field, $item[$field['key']] ?? null,
                            "{$block['key']}.{$item['id']}.{$field['key']}", $title
                        ));
                    }
                }
            }
        }

        return array_merge($issues, $this->rules($content));
    }

    private function fieldIssues(string $section, array $field, mixed $value, string $path, ?string $itemTitle): array
    {
        $where = $itemTitle ? "{$itemTitle}: " : '';
        if (is_string($value) && Str::lower(trim($value)) === Str::lower(self::PENDING)) {
            return [['section' => $section, 'level' => 'falta', 'text' => "{$where}{$field['label']} está como \"A confirmar\".", 'path' => $path]];
        }
        if (($field['required'] ?? false) && ($value === null || $value === '' || $value === [])) {
            return [['section' => $section, 'level' => 'falta', 'text' => "{$where}falta {$field['label']}.", 'path' => $path]];
        }

        return [];
    }

    /** Situação de cada seção: vazia, erro, falta ou pronta. */
    public function sectionStatuses(array $content, array $issues): array
    {
        $out = [];
        foreach ($this->sections() as $section) {
            $mine = array_filter($issues, fn ($i) => $i['section'] === $section['key']);
            $filled = false;
            foreach ($section['blocks'] as $block) {
                $value = $content[$block['key']] ?? null;
                if ($block['type'] === 'list') {
                    foreach ($value ?? [] as $item) {
                        foreach ($block['fields'] as $f) {
                            if ($f['key'] !== ($block['switch'] ?? null) && !in_array($item[$f['key']] ?? null, [null, '', []], true)) {
                                $filled = true;
                            }
                        }
                    }
                } elseif (!in_array($value, [null, '', []], true)) {
                    $filled = true;
                }
            }

            $out[$section['key']] = match (true) {
                !$filled && !empty($section['optional']) && !$mine          => 'opcional',
                !$filled && empty($section['optional'])                    => 'vazia',
                (bool) array_filter($mine, fn ($i) => $i['level'] === 'erro') => 'erro',
                (bool) $mine                                                 => 'falta',
                default                                                      => 'pronta',
            };
        }

        return $out;
    }

    public function hasBlockingIssues(array $content): bool
    {
        return (bool) array_filter($this->issues($content), fn ($i) => $i['level'] === 'erro');
    }

    /**
     * O documento em texto corrido, seção por seção, com os rótulos do modelo — pra IA ler
     * (planejamento anterior como fonte do próximo, contexto do agente). Ligações viram nomes.
     */
    public function toText(array $content): string
    {
        $names = [];
        foreach ($this->sections() as $section) {
            foreach ($section['blocks'] as $block) {
                if ($block['type'] === 'list') {
                    foreach ($content[$block['key']] ?? [] as $item) {
                        $names[$item['id']] = $item[$block['title_field']] ?? '';
                    }
                }
            }
        }
        $fmt = function (array $field, mixed $v) use ($names) {
            if ($v === null || $v === '' || $v === []) {
                return null;
            }
            if ($field['input'] === 'user') {
                return \App\Models\User::withoutGlobalScopes()->whereKey($v)->value('name');
            }

            return match ($field['input']) {
                'refs'   => implode(', ', array_filter(array_map(fn ($id) => $names[$id] ?? null, (array) $v))),
                'ref'    => $names[$v] ?? null,
                'select' => $field['options'][$v] ?? $v,
                default  => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE),
            };
        };

        $out = [];
        foreach ($this->sections() as $section) {
            $lines = [];
            foreach ($section['blocks'] as $block) {
                if ($block['type'] === 'field') {
                    if (($t = $fmt($block, $content[$block['key']] ?? null)) !== null) {
                        $lines[] = "{$block['label']}: {$t}";
                    }
                    continue;
                }
                foreach ($content[$block['key']] ?? [] as $n => $item) {
                    $parts = [];
                    foreach ($block['fields'] as $field) {
                        if ($this->applies($field, $item) && ($t = $fmt($field, $item[$field['key']] ?? null)) !== null) {
                            $parts[] = "{$field['label']}: {$t}";
                        }
                    }
                    if ($parts) {
                        $lines[] = "- {$block['item_label']} " . ($n + 1) . ': ' . implode(' | ', $parts);
                    }
                }
            }
            if ($lines) {
                $out[] = '## ' . trim(($section['num'] ? $section['num'] . ' ' : '') . $section['title']) . "\n" . implode("\n", $lines);
            }
        }

        return implode("\n\n", $out);
    }

    /** Lista de itens por id (pra montar "Carregada por", referências etc.). */
    protected static function byId(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[$item['id']] = $item;
        }

        return $out;
    }
}
