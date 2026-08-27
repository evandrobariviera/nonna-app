<?php

namespace App\Services;

// Converte a ATA (Markdown gerado pelo agente "ATA Estruturada" — formato fixo definido
// no system_prompt dele: ## seções, ### subseções, **negrito**, listas, checklist "- [ ]
// texto — Responsável · prazo") numa árvore de blocos pra renderizar como cards na tela
// de impressão. Parser próprio e não uma lib de markdown genérica: o formato de entrada é
// 100% controlado por nós (é o nosso próprio prompt), então cobrir só esse subconjunto é
// suficiente e evita puxar uma dependência Composer nova (exigiria rebuild da imagem
// Docker — ver Dockerfile sem ext-zip como precedente desse custo).
class AtaMarkdownRenderer
{
    /**
     * @return array<int, array{type: string, ...}>
     */
    public static function parse(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($markdown));
        $blocks = [];
        $paragraphBuffer = [];
        $listBuffer = [];
        $checklistBuffer = [];
        $numberedBuffer = [];

        $flush = function () use (&$paragraphBuffer, &$listBuffer, &$checklistBuffer, &$numberedBuffer, &$blocks) {
            if ($paragraphBuffer) {
                $blocks[] = ['type' => 'p', 'html' => self::inline(implode(' ', $paragraphBuffer))];
                $paragraphBuffer = [];
            }
            if ($listBuffer) {
                $blocks[] = ['type' => 'ul', 'items' => $listBuffer];
                $listBuffer = [];
            }
            if ($checklistBuffer) {
                $blocks[] = ['type' => 'checklist', 'items' => $checklistBuffer];
                $checklistBuffer = [];
            }
            if ($numberedBuffer) {
                $blocks[] = ['type' => 'ol', 'items' => $numberedBuffer];
                $numberedBuffer = [];
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed === '---') {
                $flush();
                continue;
            }

            if (preg_match('/^##\s+(.+)$/', $trimmed, $m)) {
                $flush();
                $blocks[] = ['type' => 'h2', 'text' => trim($m[1])];
                continue;
            }

            if (preg_match('/^###\s+(.+)$/', $trimmed, $m)) {
                $flush();
                $blocks[] = ['type' => 'h3', 'text' => trim($m[1])];
                continue;
            }

            if (preg_match('/^-\s*\[( |x|X)\]\s*(.+)$/', $trimmed, $m)) {
                if ($paragraphBuffer || $listBuffer || $numberedBuffer) {
                    $flush();
                }
                $done = strtolower($m[1]) === 'x';
                $text = $m[2];
                $resp = null;
                if (preg_match('/^(.*?)\s+[—-]\s+(.+)$/u', $text, $rm)) {
                    $text = trim($rm[1]);
                    $resp = trim($rm[2]);
                }
                $checklistBuffer[] = ['done' => $done, 'html' => self::inline($text), 'resp' => $resp];
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
                if ($paragraphBuffer || $listBuffer || $checklistBuffer) {
                    $flush();
                }
                $numberedBuffer[] = self::inline($m[1]);
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
                if ($paragraphBuffer || $checklistBuffer || $numberedBuffer) {
                    $flush();
                }
                $listBuffer[] = self::inline($m[1]);
                continue;
            }

            if ($checklistBuffer || $listBuffer || $numberedBuffer) {
                $flush();
            }
            $paragraphBuffer[] = $trimmed;
        }

        $flush();

        return $blocks;
    }

    // Agrupa os blocos em cards: cada h2 abre um card novo, h3 vira subtítulo dentro do
    // card corrente. Conteúdo antes do primeiro h2 (se houver) vira um card sem título.
    public static function toCards(string $markdown): array
    {
        $blocks = self::parse($markdown);
        $cards = [];
        $current = null;

        foreach ($blocks as $block) {
            if ($block['type'] === 'h2') {
                if ($current) {
                    $cards[] = $current;
                }
                $current = ['title' => $block['text'], 'blocks' => []];
                continue;
            }

            if (!$current) {
                $current = ['title' => null, 'blocks' => []];
            }

            $current['blocks'][] = $block;
        }

        if ($current) {
            $cards[] = $current;
        }

        return $cards;
    }

    private static function inline(string $text): string
    {
        $escaped = e($text);
        return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped);
    }
}
