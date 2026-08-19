<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

// Allowlist de HTML gerado pelo editor rico (Tiptap, ver resources/js/tiptap-editor.js) —
// remove qualquer tag/atributo fora do que o editor consegue produzir. Não usa
// strip_tags() sozinho porque isso não valida atributos (onerror, href javascript:) nem
// esquema de URL — só a lista de tags. Sem dependência nova (DOMDocument é núcleo do PHP).
class RichTextSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'blockquote', 'hr', 'h2', 'h3', 'a', 'img',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a'   => ['href'],
        'img' => ['src', 'alt'],
    ];

    // Tags cujo conteúdo não deve virar texto solto ao remover a tag — diferente de uma
    // tag desconhecida qualquer (ex: <div>texto</div>), onde preservar o texto é o certo.
    private const STRIP_ENTIRELY = ['script', 'style'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // Envolve num <div> raiz pra poder extrair só o conteúdo depois (DOMDocument
        // sempre monta html/body por baixo dos panos ao carregar um fragmento).
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);
        if (!$root) {
            return '';
        }

        self::cleanChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    // Texto puro do HTML rico — usado pra virar título de item de ação (checklist)
    // a partir de um comentário: perde formatação de propósito, só o texto.
    public static function toPlainText(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $text = trim(preg_replace('/\s+/', ' ', $dom->textContent ?? ''));

        return $text;
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }

            if ($child instanceof DOMText) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::STRIP_ENTIRELY, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Tag não permitida (ex: <script>, <style>, <div>) — remove a tag mas
                // preserva o conteúdo de dentro dela, movendo os filhos pro lugar dela.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
            self::cleanChildren($child);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->name);

            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
                continue;
            }

            if ($name === 'href' && !self::isSafeUrl($attr->value, allowData: false)) {
                $el->removeAttribute($attr->name);
            }
            if ($name === 'src' && !self::isSafeUrl($attr->value, allowData: true)) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    // allowData=true permite data:image/... (paste de imagem em base64); href de link
    // nunca permite data: (não faz sentido pra <a> e evita truque de data:text/html).
    private static function isSafeUrl(string $value, bool $allowData): bool
    {
        $value = trim($value);

        if (preg_match('#^(https?://|mailto:)#i', $value)) {
            return true;
        }

        if ($allowData && preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml);base64,#i', $value)) {
            return true;
        }

        return false;
    }
}
