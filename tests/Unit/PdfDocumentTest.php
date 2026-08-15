<?php

namespace Tests\Unit;

use App\Admin\Report\Pdf\PdfDocument;
use PHPUnit\Framework\TestCase;

class PdfDocumentTest extends TestCase
{
    private function build(callable $fn): string
    {
        $doc = PdfDocument::make();
        $fn($doc);
        $doc->close();

        $bytes = (string) file_get_contents($doc->path());
        @unlink($doc->path());

        return $bytes;
    }

    public function test_it_emits_a_well_formed_container(): void
    {
        $pdf = $this->build(function (PdfDocument $doc) {
            $doc->heading('Signups')->paragraph('Covering 1 Aug to 16 Aug.');
        });

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith('%%EOF', trim($pdf));
    }

    public function test_the_trailer_start_offset_points_at_the_xref_table(): void
    {
        $pdf = $this->build(fn (PdfDocument $doc) => $doc->paragraph('x'));

        $this->assertSame(1, preg_match('/startxref\s+(\d+)/', $pdf, $m));

        $offset = (int) $m[1];

        $this->assertSame('xref', substr($pdf, $offset, 4));
    }

    public function test_a_long_table_paginates_and_repeats_the_header(): void
    {
        $pdf = $this->build(function (PdfDocument $doc) {
            $doc->tableHeader(['Bucket', 'Signups']);

            for ($i = 0; $i < 500; $i++) {
                $doc->tableRow(['2026-08-'.str_pad((string) ($i % 28 + 1), 2, '0', STR_PAD_LEFT), (string) $i]);
            }
        });

        $pages = substr_count($pdf, '/Type /Page ');
        $headers = substr_count($pdf, '(Bucket) Tj');

        $this->assertGreaterThan(1, $pages);
        $this->assertSame($pages, $headers, 'Every page must repeat the table header.');
    }

    public function test_a_large_table_stays_within_a_memory_ceiling(): void
    {
        gc_collect_cycles();
        $before = memory_get_peak_usage(true);

        $pdf = $this->build(function (PdfDocument $doc) {
            $doc->tableHeader(['A', 'B', 'C']);

            for ($i = 0; $i < 5000; $i++) {
                $doc->tableRow(['row-'.$i, (string) $i, 'value-'.$i]);
            }
        });

        $growth = memory_get_peak_usage(true) - $before;

        $this->assertGreaterThan(100000, strlen($pdf));
        $this->assertLessThan(8 * 1024 * 1024, $growth, 'Page content must stream, not buffer.');
    }

    public function test_can_render_gates_non_latin_text(): void
    {
        $this->assertTrue(PdfDocument::canRender('Hello'));
        $this->assertTrue(PdfDocument::canRender('Café — Ångström'));
        $this->assertFalse(PdfDocument::canRender('用户报告'));
    }
}
