<?php

namespace App\Admin\Export;

use App\Admin\Report\Pdf\PdfDocument;
use App\Support\Csv;

/**
 * A tabular PDF for the existing streamExport() path. RowWriter itself is left
 * untouched: a report document is not a row stream, so PdfReportWriter composes
 * its own layout rather than abusing row().
 *
 * Non-WinAnsi values degrade to '?' here rather than throwing — an export that
 * refuses mid-stream has already sent its headers.
 */
final class PdfRowWriter implements RowWriter
{
    private ?PdfDocument $doc = null;

    public function __construct(private readonly string $title = 'Export') {}

    public static function isSupported(): bool
    {
        return PdfDocument::isSupported();
    }

    public function open(): void
    {
        $this->doc = PdfDocument::make('A4', 'landscape')
            ->meta($this->title, (string) config('app.name'))
            ->runningHeader($this->title, now()->toDateString())
            ->runningFooter((string) config('app.name'));
    }

    public function header(array $labels): void
    {
        $this->doc?->tableHeader(array_map(static fn ($l) => Csv::cell($l), $labels));
    }

    public function row(array $values): void
    {
        $this->doc?->tableRow(array_map(static fn ($v) => Csv::cell($v), $values));
    }

    public function flush(): void
    {
        $this->doc?->flush();
    }

    public function close(): void
    {
        if ($this->doc === null) {
            return;
        }

        $this->doc->close();
        $path = $this->doc->path();
        $this->doc = null;

        readfile($path);
        @unlink($path);
    }
}
