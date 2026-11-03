<?php

namespace Tests\Unit;

use App\Services\DocumentUpdate\HtmlPatch;
use PHPUnit\Framework\TestCase;

class HtmlPatchTest extends TestCase
{
    private string $doc = <<<'HTML'
<!DOCTYPE html><html><head><style>body{color:red}</style></head><body>
<h2>Peças e Datas</h2>
<ul>
    <li>3 reels de depoimento — 04/11</li>
    <li>1 landing page — 02/11</li>
</ul>
<p>Vai ao ar em 09/11.</p>
<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==">
<script type="application/json" id="nonna-dados">{"inicio":"2026-11-09"}</script>
</body></html>
HTML;

    public function test_for_ai_keeps_body_and_drops_styles_scripts_and_embedded_images(): void
    {
        $out = HtmlPatch::forAi($this->doc);

        $this->assertStringContainsString('<li>3 reels de depoimento — 04/11</li>', $out);
        $this->assertStringNotContainsString('<style', $out);
        $this->assertStringNotContainsString('nonna-dados', $out);
        $this->assertStringNotContainsString('base64', $out);
        $this->assertStringContainsString('src="[imagem-embutida]"', $out);
    }

    public function test_replaces_exact_unique_snippet(): void
    {
        $out = HtmlPatch::replace($this->doc, '<p>Vai ao ar em 09/11.</p>', '<p>Vai ao ar em 16/11.</p>');

        $this->assertStringContainsString('Vai ao ar em 16/11.', $out);
        $this->assertStringNotContainsString('09/11', $out);
        $this->assertStringContainsString('nonna-dados', $out);
    }

    public function test_tolerates_whitespace_differences(): void
    {
        $needle = "<ul><li>3 reels de depoimento — 04/11</li>\n<li>1 landing page — 02/11</li></ul>";

        $out = HtmlPatch::replace($this->doc, $needle, '<ul><li>2 reels — 04/11</li></ul>');

        $this->assertNotNull($out);
        $this->assertStringContainsString('<ul><li>2 reels — 04/11</li></ul>', $out);
        $this->assertStringNotContainsString('landing page', $out);
    }

    public function test_refuses_missing_or_repeated_snippet(): void
    {
        $this->assertNull(HtmlPatch::replace($this->doc, '<p>Não existe</p>', 'x'));
        $this->assertNull(HtmlPatch::replace($this->doc, '<li>', 'x'));
        $this->assertNull(HtmlPatch::replace($this->doc, '   ', 'x'));
    }

    public function test_readable_turns_html_into_plain_text(): void
    {
        $text = HtmlPatch::readable('<table><tr><td>Reels</td><td>3</td></tr></table><ul><li>Um &amp; dois</li></ul>');

        $this->assertSame("Reels | 3\n• Um & dois", $text);
    }
}
