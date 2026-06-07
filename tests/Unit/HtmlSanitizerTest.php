<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_keeps_allowlisted_tags(): void
    {
        $html = '<h2>Title</h2><p>Hello <strong>world</strong> and <em>more</em>.</p><ul><li>one</li></ul>';
        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function test_strips_script_tags(): void
    {
        $out = HtmlSanitizer::clean('<p>ok</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('<p>ok</p>', $out);
    }

    public function test_strips_event_handler_attributes(): void
    {
        $out = HtmlSanitizer::clean('<p onclick="evil()">x</p>');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('x', $out);
    }

    public function test_strips_javascript_urls(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript:alert(1)">link</a>');
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_allows_safe_links_and_hardens_blank_targets(): void
    {
        $out = HtmlSanitizer::clean('<a href="https://example.com" target="_blank">x</a>');
        $this->assertStringContainsString('href="https://example.com"', $out);
        $this->assertStringContainsString('noopener', $out);
    }

    public function test_preserves_templating_placeholders_in_urls(): void
    {
        // Regression guard: DOMDocument percent-encodes braces; sanitizer must restore them.
        $out = HtmlSanitizer::clean('<a href="{{reset_link}}">Reset</a>');
        $this->assertStringContainsString('{{reset_link}}', $out);
        $this->assertStringNotContainsString('%7B', $out);
    }

    public function test_blocks_non_image_data_uri(): void
    {
        $out = HtmlSanitizer::clean('<img src="data:text/html;base64,PHNjcmlwdD4=">');
        $this->assertStringNotContainsString('data:text/html', $out);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }
}
