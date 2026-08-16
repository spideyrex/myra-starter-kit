<?php

namespace Tests\Feature\PageBuilder;

use App\Support\HtmlSanitizer;
use App\Support\UrlGuard;
use PHPUnit\Framework\TestCase;

class UrlGuardTest extends TestCase
{
    /** @return array<string,array{0:?string}> */
    public static function safeUrls(): array
    {
        return [
            'https' => ['https://example.com/a?b=1#c'],
            'http' => ['http://example.com'],
            'mailto' => ['mailto:hi@example.com'],
            'tel' => ['tel:+60123456789'],
            'root relative' => ['/register'],
            'anchor' => ['#pricing'],
            'empty' => [''],
            'padded' => ['   /register   '],
        ];
    }

    /** @dataProvider safeUrls */
    public function test_the_allowlist_admits_the_shapes_authors_actually_use(string $url): void
    {
        $this->assertTrue(UrlGuard::isSafe($url), "[{$url}] should be allowed.");
    }

    /** @return array<string,array{0:?string}> */
    public static function unsafeUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript padded and cased' => ['  JaVaScRiPt:alert(1)'],
            'data html' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'protocol relative' => ['//evil.example.com'],
            'backslash protocol relative' => ['/\\evil.example.com'],
            'bare relative' => ['about.html'],
            'null' => [null],
        ];
    }

    /** @dataProvider unsafeUrls */
    public function test_everything_else_is_refused(?string $url): void
    {
        $this->assertFalse(UrlGuard::isSafe($url), 'This URL should have been refused.');
    }

    public function test_safe_returns_the_fallback_rather_than_the_hostile_value(): void
    {
        $this->assertSame('', UrlGuard::safe('javascript:alert(1)'));
        $this->assertSame('/', UrlGuard::safe('javascript:alert(1)', '/'));
        $this->assertSame('/register', UrlGuard::safe('  /register  '));
        $this->assertSame('', UrlGuard::safe(null));
    }

    public function test_has_scheme_only_reports_an_explicit_scheme(): void
    {
        $this->assertTrue(UrlGuard::hasScheme('https://example.com'));
        $this->assertTrue(UrlGuard::hasScheme('  javascript:alert(1)'));
        $this->assertFalse(UrlGuard::hasScheme('/register'));
        $this->assertFalse(UrlGuard::hasScheme('{{reset_link}}'));
        $this->assertFalse(UrlGuard::hasScheme(null));
    }

    /**
     * The sanitizer now delegates its scheme decision, so its observable
     * behaviour has to be exactly what it was before.
     */
    public function test_the_sanitizer_behaves_exactly_as_it_did_before_delegating(): void
    {
        $this->assertStringNotContainsString(
            'javascript:',
            HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>'),
        );

        $this->assertStringContainsString(
            'href="https://example.com"',
            HtmlSanitizer::clean('<a href="https://example.com">x</a>'),
        );

        // Relative and templated hrefs are scheme-less and stay permitted.
        $this->assertStringContainsString('href="about.html"', HtmlSanitizer::clean('<a href="about.html">x</a>'));
        $this->assertStringContainsString('{{reset_link}}', HtmlSanitizer::clean('<a href="{{reset_link}}">x</a>'));
        $this->assertStringContainsString('href="//cdn.example.com/x"', HtmlSanitizer::clean('<a href="//cdn.example.com/x">x</a>'));

        // The <img>-only data: exception stays local to the sanitizer.
        $this->assertStringNotContainsString(
            'data:text/html',
            HtmlSanitizer::clean('<img src="data:text/html;base64,PHNjcmlwdD4=">'),
        );
    }
}
