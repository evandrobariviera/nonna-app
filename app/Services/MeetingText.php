<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Pauta e ATA da reunião (2026-10-22): o time escreve no editor rico (HTML); a IA escreve e
 * lê Markdown no formato do agente "ATA Estruturada" (## seções, listas, checklist
 * "- [ ] texto — Responsável · prazo"). Os dois viram os MESMOS blocos de
 * AtaMarkdownRenderer::parse — é com eles que a tela e a "ATA formatada" desenham os cards.
 *
 * - Gravar o que a IA devolveu: fromAi() (Markdown → HTML do editor).
 * - Mandar pra IA: toMarkdown() (HTML → Markdown; texto antigo já é Markdown e passa direto).
 * - Exibir/editar: toHtml() e cards() aceitam os dois formatos — reunião antiga não precisa
 *   de migração.
 *
 * Checklist no HTML = item de lista começando com ☐ (aberto) ou ☑ (feito), sem extensão
 * nova no editor.
 */
class MeetingText
{
    private const TODO = '☐';
    private const DONE = '☑';

    /** Mesmo critério de <x-rich-content>: só é HTML do editor se COMEÇA com um bloco dele. */
    public static function isHtml(?string $value): bool
    {
        return (bool) preg_match('/^\s*<(p|h[1-6]|ul|ol|blockquote|pre|hr|img|figure|table|div)\b/i', (string) $value);
    }

    /** Valor pro editor rico / exibição. Markdown antigo (ou da IA) vira HTML. */
    public static function toHtml(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || self::isHtml($value)) {
            return $value;
        }

        return self::blocksToHtml(AtaMarkdownRenderer::parse($value));
    }

    /** O que veio do editor rico, limpo; editor vazio ("<p></p>") vira null. */
    public static function fromEditor(?string $html): ?string
    {
        $clean = \App\Support\RichTextSanitizer::clean($html);
        $hasContent = trim(\App\Support\RichTextSanitizer::toPlainText($clean)) !== '' || str_contains($clean, '<img');

        return $hasContent ? $clean : null;
    }

    /** O que a IA devolveu (Markdown), no formato que o editor grava. */
    public static function fromAi(?string $markdown): ?string
    {
        return trim((string) $markdown) === '' ? null : self::toHtml($markdown);
    }

    /** Texto pra IA ler (e pra variáveis de mensagem): sempre Markdown. */
    public static function toMarkdown(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || !self::isHtml($value)) {
            return $value;
        }

        $lines = [];
        foreach (self::blocks($value) as $block) {
            $lines[] = match ($block['type']) {
                'h2'        => "\n## {$block['text']}",
                'h3'        => "\n### {$block['text']}",
                'p'         => self::inlineToMarkdown($block['html']),
                'ul'        => implode("\n", array_map(fn ($i) => '- ' . self::inlineToMarkdown($i), $block['items'])),
                'ol'        => implode("\n", array_map(fn ($i, $n) => ($n + 1) . '. ' . self::inlineToMarkdown($i), $block['items'], array_keys($block['items']))),
                'checklist' => implode("\n", array_map(
                    fn ($i) => '- [' . ($i['done'] ? 'x' : ' ') . '] ' . self::inlineToMarkdown($i['html']) . ($i['resp'] ? " — {$i['resp']}" : ''),
                    $block['items'],
                )),
                default     => '',
            };
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n\n", $lines)));
    }

    /**
     * Mesmo conteúdo, ignorando a forma (HTML x Markdown antigo, espaços, como o editor
     * serializou) — os dois lados passam pelo mesmo caminho antes de comparar.
     */
    public static function sameContent(?string $a, ?string $b): bool
    {
        $canonical = fn (?string $v) => preg_replace('/\s+/u', ' ', self::toMarkdown(self::toHtml($v)));

        return $canonical($a) === $canonical($b);
    }

    /** Texto corrido, sem formatação (resumos de uma linha). */
    public static function plain(?string $value): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', self::toHtml($value))), ENT_QUOTES, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Cards (um por seção ##/H2) pra tela da reunião e "ATA formatada". */
    public static function cards(?string $value): array
    {
        return AtaMarkdownRenderer::groupCards(self::blocks($value));
    }

    public static function blocks(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }
        if (!self::isHtml($value)) {
            return AtaMarkdownRenderer::parse($value);
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $value . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('root');

        $blocks = [];
        foreach ($root?->childNodes ?? [] as $node) {
            if (!$node instanceof DOMElement) {
                $text = trim($node->textContent);
                if ($text !== '') {
                    $blocks[] = ['type' => 'p', 'html' => e($text)];
                }
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, ['h1', 'h2'], true)) {
                $blocks[] = ['type' => 'h2', 'text' => trim($node->textContent)];
            } elseif (in_array($tag, ['h3', 'h4', 'h5', 'h6'], true)) {
                $blocks[] = ['type' => 'h3', 'text' => trim($node->textContent)];
            } elseif ($tag === 'ul' || $tag === 'ol') {
                array_push($blocks, ...self::listBlocks($node, $tag));
            } else {
                $html = trim(self::innerHtml($node));
                if ($html === '') {
                    continue;
                }
                $item = self::checklistItem($html);
                $blocks[] = $item ? ['type' => 'checklist', 'items' => [$item]] : ['type' => 'p', 'html' => $tag === 'p' ? $html : self::outerHtml($node)];
            }
        }

        // Itens de checklist vizinhos viram um bloco só.
        $merged = [];
        foreach ($blocks as $block) {
            $last = array_key_last($merged);
            if ($block['type'] === 'checklist' && $last !== null && $merged[$last]['type'] === 'checklist') {
                array_push($merged[$last]['items'], ...$block['items']);
            } else {
                $merged[] = $block;
            }
        }

        return $merged;
    }

    /** Lista do editor → ul/ol, separando os trechos que são checklist (☐/☑). */
    private static function listBlocks(DOMElement $list, string $tag): array
    {
        $blocks = [];
        foreach ($list->childNodes as $li) {
            if (!$li instanceof DOMElement || strtolower($li->tagName) !== 'li') {
                continue;
            }
            // O tiptap embrulha o texto do item num <p> — desembrulha.
            $html = trim(preg_replace('#^<p>(.*)</p>$#s', '$1', trim(self::innerHtml($li))));
            $item = self::checklistItem($html);
            $type = $item ? 'checklist' : $tag;
            $last = array_key_last($blocks);
            if ($last === null || $blocks[$last]['type'] !== $type) {
                $blocks[] = ['type' => $type, 'items' => []];
                $last = array_key_last($blocks);
            }
            $blocks[$last]['items'][] = $item ?: $html;
        }

        return $blocks;
    }

    /** "☐ texto — Responsável · prazo" → item de checklist; null se não for. */
    private static function checklistItem(string $html): ?array
    {
        $text = ltrim(html_entity_decode(strip_tags(mb_substr($html, 0, 20)), ENT_QUOTES, 'UTF-8'));
        $mark = mb_substr($text, 0, 1);
        if ($mark !== self::TODO && $mark !== self::DONE) {
            return null;
        }

        $body = trim(preg_replace('/^(\s*<[^>]+>)*\s*[' . self::TODO . self::DONE . ']\s*/u', '$1', $html, 1));
        $resp = null;
        if (preg_match('/^(.*?)\s+[—-]\s+([^<>]+)$/u', $body, $m)) {
            $body = trim($m[1]);
            $resp = trim(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
        }

        return ['done' => $mark === self::DONE, 'html' => $body, 'resp' => $resp];
    }

    private static function blocksToHtml(array $blocks): string
    {
        $li = fn (string $html) => "<li><p>{$html}</p></li>";

        return implode('', array_map(fn ($block) => match ($block['type']) {
            'h2'        => '<h2>' . e($block['text']) . '</h2>',
            'h3'        => '<h3>' . e($block['text']) . '</h3>',
            'p'         => "<p>{$block['html']}</p>",
            'ul'        => '<ul>' . implode('', array_map($li, $block['items'])) . '</ul>',
            'ol'        => '<ol>' . implode('', array_map($li, $block['items'])) . '</ol>',
            'checklist' => '<ul>' . implode('', array_map(
                fn ($i) => $li(($i['done'] ? self::DONE : self::TODO) . ' ' . $i['html'] . ($i['resp'] ? ' — ' . e($i['resp']) : '')),
                $block['items'],
            )) . '</ul>',
            default     => '',
        }, $blocks));
    }

    private static function inlineToMarkdown(string $html): string
    {
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</?(strong|b)>#i', '**', $html);
        $html = preg_replace('#</?(em|i)>#i', '_', $html);
        $html = preg_replace('#<a\s[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    private static function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    private static function outerHtml(DOMNode $node): string
    {
        return $node->ownerDocument->saveHTML($node);
    }
}
