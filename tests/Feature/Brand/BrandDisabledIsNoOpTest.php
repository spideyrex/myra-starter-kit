<?php

namespace Tests\Feature\Brand;

use App\Admin\Export\PdfRowWriter;
use App\Brand\BrandManager;
use App\Mail\ScheduledReportMail;
use App\Services\EmailService;
use Tests\TestCase;

/** brand.enabled ships false, and with it false every surface is pre-2.6. */
class BrandDisabledIsNoOpTest extends TestCase
{
    public function test_the_migration_ships_the_switch_off(): void
    {
        $this->assertFalse(app(BrandManager::class)->current()->enabled);
    }

    public function test_no_brand_style_block_or_boot_script_is_emitted(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('<style id="myra-brand">', $html);
        $this->assertStringNotContainsString('localStorage.getItem("theme")', $html);
    }

    public function test_no_open_graph_meta_is_emitted(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('og:title', $html);
        $this->assertStringNotContainsString('twitter:card', $html);
    }

    public function test_the_title_falls_back_to_the_framework_name(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>'.config('app.name').'</title>', $html);
    }

    public function test_the_manifest_still_serves_the_static_document(): void
    {
        $payload = $this->get(route('brand.manifest'))->assertOk()->json();
        $static = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame($static, $payload);
    }

    public function test_a_generated_pdf_keeps_the_historic_producer(): void
    {
        $writer = new PdfRowWriter('Users');
        $writer->open();
        $writer->header(['Name']);
        $writer->row(['Ada']);
        $writer->flush();

        ob_start();
        $writer->close();
        $bytes = (string) ob_get_clean();

        $this->assertStringContainsString('/Producer (Myra)', $bytes);
    }

    public function test_mail_is_not_wrapped_in_the_brand_layout(): void
    {
        $mailer = app(EmailService::class)->mailer();
        $mailer->send((new ScheduledReportMail('Subject', '<p>Body</p>'))->to('ops@example.test'));

        $raw = $mailer->getSymfonyTransport()->messages()[0]->getMessage()->toString();

        $this->assertStringNotContainsString('Content-ID:', $raw);
    }

    public function test_mail_tokens_are_empty_so_replace_variables_is_the_identity(): void
    {
        $this->assertSame([], app(BrandManager::class)->mailTokens());
    }
}
