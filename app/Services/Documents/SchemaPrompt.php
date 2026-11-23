<?php

namespace App\Services\Documents;

/**
 * O modelo de um documento descrito para a IA (seções, campos, listas, tipos e chaves), gerado
 * a partir do DocumentSchema — assim o "manual" do agente nunca fica desatualizado em relação ao
 * modelo. Usado pelo agente da gaveta (DocumentAssistant) e pelo rascunho (DocumentDrafter).
 */
class SchemaPrompt
{
    public static function map(DocumentSchema $schema): string
    {
        $out = [];
        foreach ($schema->sections() as $section) {
            $lines = [];
            foreach ($section['blocks'] as $b) {
                if ($b['type'] === 'field') {
                    $lines[] = "  campo \"{$b['key']}\" = {$b['label']} [" . self::type($b) . ']';
                } else {
                    $fields = collect($b['fields'])->map(fn ($f) => "{$f['key']}={$f['label']} [" . self::type($f) . ']'
                        . (isset($f['only']) ? ' (só ' . implode('/', $f['only']) . ')' : ''))->implode('; ');
                    $limits = trim((isset($b['min']) ? "mín. {$b['min']} " : '') . (isset($b['max']) ? "máx. {$b['max']}" : ''));
                    $lines[] = "  lista \"{$b['key']}\" (cada item: {$b['item_label']}" . ($limits ? ", {$limits}" : '') . "): {$fields}";
                }
            }
            $out[] = '- ' . trim(($section['num'] ?? '') . ' ' . $section['title']) . ': ' . $section['help'] . "\n" . implode("\n", $lines);
        }

        return implode("\n", $out);
    }

    public static function type(array $f): string
    {
        return match ($f['input']) {
            'select' => 'um de: ' . implode(', ', array_map(fn ($k, $v) => "{$k} ({$v})", array_keys($f['options'] ?? []), $f['options'] ?? [])),
            'refs'   => 'ligação com itens de "' . $f['refs'] . '"',
            'ref'    => 'ligação com um item de "' . $f['refs'] . '"',
            'date'   => 'data AAAA-MM-DD',
            'money'  => 'número em reais',
            'number' => 'número',
            'text'   => 'texto, parágrafos curtos ou uma linha por item',
            default  => 'texto curto',
        };
    }
}
