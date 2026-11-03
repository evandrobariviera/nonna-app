<?php

namespace App\Services\DocumentUpdate;

/**
 * Troca de trechos no HTML do documento (sugestões da IA em "Atualizar documento com reunião").
 * Mesma ideia de um "procurar e substituir" exato: o trecho atual precisa aparecer UMA vez
 * no documento — se não achar (ou achar repetido), a sugestão não é aplicada às cegas.
 * Diferença só de espaços/quebras de linha é tolerada (a IA costuma "arrumar" a indentação).
 */
class HtmlPatch
{
    /**
     * O que vai pra IA: o corpo do documento sem <style>/<script> (o bloco de dados vai à
     * parte, em JSON) e com imagens embutidas trocadas por marcador — um planejamento com
     * foto em base64 passa de 2 MB e quase tudo é imagem. O resto fica idêntico ao original,
     * pra a IA conseguir copiar trechos que existem de verdade no arquivo.
     */
    public static function forAi(string $html): string
    {
        if (preg_match('~<body[^>]*>(.*)</body>~is', $html, $m)) {
            $html = $m[1];
        }

        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
        $html = preg_replace('~(["\'(])data:[a-z]+/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+(["\')])~i', '$1[imagem-embutida]$2', $html);

        return trim($html);
    }

    /** Posição [início, tamanho] do trecho no HTML, só se aparecer exatamente uma vez. */
    public static function locate(string $html, string $needle): ?array
    {
        if (trim($needle) === '') {
            return null;
        }

        $first = strpos($html, $needle);
        if ($first !== false) {
            return strpos($html, $needle, $first + 1) === false ? [$first, strlen($needle)] : null;
        }

        // Tolerante a espaço: cada sequência de espaços casa com qualquer outra, e entre
        // tags ("><") pode ou não haver espaço/quebra.
        $parts = preg_split('/\s+/u', trim($needle));
        $pattern = implode('\s+', array_map(fn ($p) => preg_quote($p, '~'), $parts));
        $pattern = str_replace('\>\<', '\>\s*\<', $pattern);

        if (@preg_match_all('~' . $pattern . '~u', $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return [$matches[0][0][1], strlen($matches[0][0][0])];
    }

    /** Trecho de HTML como texto pra tela de revisão (antes → depois), sem tag nenhuma. */
    public static function readable(string $html): string
    {
        $text = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
        $text = preg_replace('~<li\b[^>]*>~i', "\n• ", $text);
        $text = preg_replace('~</t[dh]>~i', ' | ', $text);
        // rótulo + valor em elementos colados ("<span>Vai ao ar</span><b>01/12</b>")
        $text = preg_replace('~</(span|b|strong|small|em|label|dt|dd)>~i', '$0 ', $text);
        $text = preg_replace('~<br\s*/?>|</(p|div|li|tr|h[1-6]|section|article|ul|ol|table)>~i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ ?\| ?(\n|$)/', '$1', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", $text);

        return trim($text);
    }

    public static function replace(string $html, string $needle, string $replacement): ?string
    {
        $at = self::locate($html, $needle);

        return $at === null ? null : substr_replace($html, $replacement, $at[0], $at[1]);
    }
}
