<?php

namespace Tests\Unit;

use App\Support\RichTextSanitizer;
use Tests\TestCase;

class RichTextSanitizerTest extends TestCase
{
    public function test_strips_script_tags_entirely(): void
    {
        $clean = RichTextSanitizer::clean('<p>Oi</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringContainsString('<p>Oi</p>', $clean);
    }

    public function test_strips_onerror_attribute_and_invalid_img_src(): void
    {
        $clean = RichTextSanitizer::clean('<img src="x" onerror="alert(1)">');

        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringNotContainsString('src="x"', $clean);
    }

    public function test_strips_javascript_href(): void
    {
        $clean = RichTextSanitizer::clean('<a href="javascript:alert(1)">clique</a>');

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('clique', $clean);
    }

    public function test_keeps_allowed_formatting_tags_intact(): void
    {
        $html  = '<p><strong>negrito</strong> e <em>itálico</em></p><ul><li>item</li></ul>';
        $clean = RichTextSanitizer::clean($html);

        $this->assertStringContainsString('<strong>negrito</strong>', $clean);
        $this->assertStringContainsString('<em>itálico</em>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>item</li>', $clean);
    }

    public function test_keeps_base64_image_from_paste(): void
    {
        $src   = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $clean = RichTextSanitizer::clean('<img src="' . $src . '" alt="print">');

        $this->assertStringContainsString($src, $clean);
        $this->assertStringContainsString('alt="print"', $clean);
    }

    public function test_removes_disallowed_wrapper_tag_but_keeps_its_content(): void
    {
        $clean = RichTextSanitizer::clean('<div class="evil"><p>texto</p></div>');

        $this->assertStringNotContainsString('<div', $clean);
        $this->assertStringContainsString('<p>texto</p>', $clean);
    }

    public function test_valid_link_gets_target_blank_and_rel(): void
    {
        $clean = RichTextSanitizer::clean('<a href="https://exemplo.com">link</a>');

        $this->assertStringContainsString('href="https://exemplo.com"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', RichTextSanitizer::clean(''));
        $this->assertSame('', RichTextSanitizer::clean(null));
        $this->assertSame('', RichTextSanitizer::clean('   '));
    }
}
