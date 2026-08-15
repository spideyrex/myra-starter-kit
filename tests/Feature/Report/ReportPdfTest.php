<?php

namespace Tests\Feature\Report;

use App\Admin\Report\Pdf\PdfReportWriter;
use App\Admin\Report\ReportException;
use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportRunner;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportPdfTest extends TestCase
{
    /** @return array{0: mixed, 1: mixed, 2: mixed} */
    private function runReport(array $state = []): array
    {
        $actor = $this->actingAsSuperAdmin();
        User::factory()->count(3)->create(['status' => 'active']);

        $definition = ReportRegistry::resolve('users');
        $request = ReportRequest::parse($state ?: [
            'period' => ['preset' => 'last_30_days'],
            'dimension' => 'status',
            'measures' => ['signups'],
        ], $definition, $actor);

        return [$definition, $request, (new ReportRunner($definition))->run($request, $actor)];
    }

    public function test_it_streams_a_pdf_document(): void
    {
        [$definition, $request, $result] = $this->runReport();

        $response = (new PdfReportWriter)->stream($definition, $request, $result);

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $this->assertStringStartsWith('%PDF', $body);
        $this->assertStringContainsString('%%EOF', $body);
    }

    public function test_write_to_disk_produces_a_readable_file(): void
    {
        Storage::fake('local');
        [$definition, $request, $result] = $this->runReport();

        $path = (new PdfReportWriter)->writeToDisk($definition, $request, $result, 'local');

        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertStringStartsWith('%PDF', (string) Storage::disk('local')->get($path));
    }

    public function test_non_latin_row_labels_are_refused_rather_than_mangled(): void
    {
        $this->actingAsSuperAdmin();
        User::factory()->create(['status' => '用户报告']);

        $definition = ReportRegistry::resolve('users');
        $request = ReportRequest::parse([
            'period' => ['preset' => 'last_30_days'],
            'dimension' => 'status',
            'measures' => ['signups'],
        ], $definition, auth()->user());

        $result = (new ReportRunner($definition))->run($request, auth()->user());

        $this->expectException(ReportException::class);

        (new PdfReportWriter)->stream($definition, $request, $result);
    }

    public function test_a_posted_chart_image_is_decoration_and_is_validated(): void
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );

        $sanitised = PdfReportWriter::sanitiseImages([
            'ok' => $png,
            'okDataUri' => 'data:image/png;base64,'.base64_encode($png),
            'notPng' => 'GIF89a'.str_repeat('x', 64),
            'svgDataUri' => 'data:image/svg+xml;base64,'.base64_encode('<svg/>'),
            'tooLarge' => $png.str_repeat("\0", PdfReportWriter::MAX_IMAGE_BYTES),
        ]);

        $this->assertSame(['ok', 'okDataUri'], array_keys($sanitised));
    }
}
