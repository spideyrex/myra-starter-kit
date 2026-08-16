<?php

namespace Tests\Feature\Brand;

use App\Admin\Export\PdfRowWriter;
use App\Mail\ScheduledReportMail;
use App\Services\EmailService;
use Tests\TestCase;

/**
 * Every assertion here is made against REAL emitted output — rendered HTML, a
 * really-generated PDF's bytes, a really-sent MIME message — never a fixture
 * restating what the code was told to produce.
 */
class BrandSurfaceTest extends TestCase
{
    use SeedsBrand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBrand();
    }

    public function test_the_public_home_and_login_pages_carry_the_brand_and_server_emitted_tokens(): void
    {
        foreach (['/', '/login'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('Acme Corp', $html, $url);
            $this->assertStringContainsString('<style id="myra-brand">', $html, $url);
            $this->assertStringContainsString('--primary:oklch(', $html, $url);
        }
    }

    public function test_the_shell_no_longer_links_a_font_cdn(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.bunny.net', $html);
    }

    public function test_the_guest_layout_no_longer_hardcodes_a_letter_mark(): void
    {
        $source = (string) file_get_contents(resource_path('js/Layouts/GuestLayout.vue'));

        $this->assertStringNotContainsString('rounded-lg bg-primary-foreground text-primary', $source);
        $this->assertStringContainsString('BrandMark', $source);
    }

    public function test_the_manifest_route_is_brand_derived(): void
    {
        $payload = $this->get('/manifest.webmanifest')->assertOk()->json();

        $this->assertSame('Acme Corp', $payload['name']);
        $this->assertSame('Acme', $payload['short_name']);
        $this->assertNotSame('#09090b', $payload['theme_color']);
    }

    public function test_a_really_sent_mail_carries_the_brand_and_a_cid_embedded_logo(): void
    {
        // The mailer EmailService really builds, so the From name is exercised too.
        $mailer = app(EmailService::class)->mailer();
        $mailer->send((new ScheduledReportMail('Weekly numbers', '<p>Body</p>'))->to('ops@example.test'));

        $messages = $mailer->getSymfonyTransport()->messages();

        $this->assertNotEmpty($messages);

        $raw = $messages[0]->getMessage()->toString();

        $this->assertStringContainsString('Acme Corp', $raw);
        // A CID part, not a remote <img src>.
        $this->assertStringContainsString('image/png', $raw);
        $this->assertStringContainsString('Content-ID:', $raw);
        $this->assertStringNotContainsString('Laravel', $raw);
    }

    public function test_a_genuinely_generated_pdf_declares_the_brand_as_its_producer(): void
    {
        $writer = new PdfRowWriter('Users');
        $writer->open();
        $writer->header(['Name', 'Email']);
        $writer->row(['Ada', 'ada@example.test']);
        $writer->flush();

        ob_start();
        $writer->close();
        $bytes = (string) ob_get_clean();

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('/Producer (Acme Corp)', $bytes);
        $this->assertStringNotContainsString('/Producer (Myra)', $bytes);
    }

    public function test_no_stock_framework_or_product_literal_survives_on_a_rendered_page(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('Myra Admin', $html);
        $this->assertStringNotContainsString('>Laravel<', $html);
    }

    public function test_the_favicon_fallback_is_a_generated_branded_svg(): void
    {
        $svg = $this->get('/brand/favicon.svg')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Acme Corp</title>', $svg);
        $this->assertStringContainsString('#7c3aed', $svg);
    }
}
