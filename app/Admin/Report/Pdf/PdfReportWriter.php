<?php

namespace App\Admin\Report\Pdf;

use App\Admin\Report\Contracts\ReportDocumentRenderer;
use App\Admin\Report\ReportDefinition;
use App\Admin\Report\ReportException;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A report you cannot audit is a report you cannot act on, so the document
 * always states its own period, comparison, filters and author before it shows
 * a single number.
 */
final class PdfReportWriter implements ReportDocumentRenderer
{
    /** @return string[] */
    public static function formats(): array
    {
        return ['pdf'];
    }

    /** Cap on a posted chart image. Decoration only, so the ceiling is generous but hard. */
    public const MAX_IMAGE_BYTES = 2097152;

    public function stream(
        ReportDefinition $d,
        ReportRequest $r,
        ReportResult $res,
        array $chartImages = [],
    ): StreamedResponse {
        $doc = $this->render($d, $r, $res, self::sanitiseImages($chartImages));

        return $doc->stream($d->key().'-'.now()->format('Ymd-His').'.pdf');
    }

    /** @return string path relative to the given disk */
    public function writeToDisk(
        ReportDefinition $d,
        ReportRequest $r,
        ReportResult $res,
        string $disk = 'local',
    ): string {
        $doc = $this->render($d, $r, $res);
        $path = 'reports/'.$d->key().'-'.Str::lower((string) Str::ulid()).'.pdf';

        $handle = fopen($doc->path(), 'rb');
        Storage::disk($disk)->writeStream($path, $handle);

        if (is_resource($handle)) {
            fclose($handle);
        }

        @unlink($doc->path());

        return $path;
    }

    /**
     * A posted chart image is decoration and nothing else — every number in the
     * document comes from the query this class just re-ran. Anything that is not
     * a PNG under the cap is dropped, silently and without failing the export.
     *
     * @param  array<string, string>  $images  raw bytes or `data:image/png;base64,` URIs
     * @return array<string, string> raw PNG bytes
     */
    public static function sanitiseImages(array $images): array
    {
        $out = [];

        foreach ($images as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            if (str_starts_with($value, 'data:')) {
                if (! preg_match('#^data:image/png;base64,#i', $value)) {
                    continue;
                }

                $decoded = base64_decode(substr($value, (int) strpos($value, ',') + 1), true);

                if ($decoded === false) {
                    continue;
                }

                $value = $decoded;
            }

            if (strlen($value) > self::MAX_IMAGE_BYTES) {
                continue;
            }

            $info = @getimagesizefromstring($value);

            if ($info === false || ($info['mime'] ?? '') !== 'image/png') {
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /** @param array<string,string> $chartImages */
    private function render(ReportDefinition $d, ReportRequest $r, ReportResult $res, array $chartImages = []): PdfDocument
    {
        $payload = $res->toArray();
        $this->assertRenderable($payload);

        $title = $this->label($this->definitionTitleKey($d), $d->key());
        $doc = PdfDocument::make('A4', 'portrait')
            ->meta($title, (string) (auth()->user()?->name ?? config('app.name')))
            ->runningHeader($title, $this->periodSentence($payload))
            ->runningFooter(__('reportDelivery.pdf.generatedBy', [
                'name' => (string) (auth()->user()?->name ?? config('app.name')),
                'at' => now()->toDayDateTimeString(),
            ]));

        $doc->heading($title, 1);
        $doc->paragraph($this->periodSentence($payload));

        if (($payload['comparison'] ?? null) !== null) {
            $doc->paragraph($this->comparisonSentence($payload));
        }

        $doc->heading(__('reportDelivery.pdf.provenance'), 3);
        $doc->keyValues($this->provenance($d, $r, $payload));

        $doc->kpiRow($this->kpis($payload));

        $doc->chart(
            ChartVector::fromResult($res, $this->printableChartType((string) ($payload['state']['chart'] ?? 'bar')), ''),
            190.0,
        );

        foreach ($chartImages as $binary) {
            $doc->imagePng($binary, $doc->contentWidth(), 190.0);
        }

        $this->table($doc, $payload);
        $doc->close();

        return $doc;
    }

    /** @param array<string,mixed> $payload */
    private function assertRenderable(array $payload): void
    {
        $texts = [(string) ($payload['dimension']['labelKey'] ?? '')];

        foreach ((array) ($payload['measures'] ?? []) as $measure) {
            $texts[] = $this->label((string) ($measure['labelKey'] ?? ''), (string) ($measure['key'] ?? ''));
        }

        foreach ((array) ($payload['rows'] ?? []) as $row) {
            $texts[] = (string) ($row['label'] ?? '');
        }

        foreach ($texts as $text) {
            if ($text !== '' && ! PdfDocument::canRender($text)) {
                throw ReportException::make('reports.errors.pdfUnsupportedCharset');
            }
        }
    }

    /** @param array<string,mixed> $payload */
    private function periodSentence(array $payload): string
    {
        return __('reportDelivery.pdf.periodSentence', [
            'from' => (string) ($payload['period']['from'] ?? ''),
            'to' => (string) ($payload['period']['to'] ?? ''),
            'tz' => (string) ($payload['period']['tz'] ?? 'UTC'),
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function comparisonSentence(array $payload): string
    {
        return __('reportDelivery.pdf.comparisonSentence', [
            'from' => (string) ($payload['comparison']['from'] ?? ''),
            'to' => (string) ($payload['comparison']['to'] ?? ''),
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,string>
     */
    private function provenance(ReportDefinition $d, ReportRequest $r, array $payload): array
    {
        $out = [
            __('reportDelivery.pdf.dimension') => $this->label(
                (string) ($payload['dimension']['labelKey'] ?? ''),
                (string) ($payload['dimension']['key'] ?? ''),
            ),
        ];

        if (($payload['period']['bucket'] ?? null) !== null) {
            $out[__('reportDelivery.pdf.bucket')] = $this->label(
                'reports.bucket.'.$payload['period']['bucket'],
                (string) $payload['period']['bucket'],
            );
        }

        $out[__('reportDelivery.pdf.filters')] = $this->filterSentence($r) ?: __('reportDelivery.pdf.noFilters');
        $out[__('reportDelivery.pdf.actor')] = (string) (auth()->user()?->name ?? config('app.name'));

        return $out;
    }

    private function filterSentence(ReportRequest $r): string
    {
        $parts = [];
        $this->collectRules($r->filters()->toArray(), $parts);

        return implode('; ', $parts);
    }

    /**
     * @param  array<string,mixed>  $group
     * @param  string[]  $parts
     */
    private function collectRules(array $group, array &$parts): void
    {
        foreach ((array) ($group['rules'] ?? []) as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $value = $rule['value'] ?? null;
            $value = is_array($value) ? implode(' – ', array_map('strval', $value)) : (string) $value;

            $parts[] = trim(sprintf(
                '%s %s %s',
                $this->humanise((string) ($rule['field'] ?? '')),
                $this->label('filters.op.'.($rule['operator'] ?? ''), (string) ($rule['operator'] ?? '')),
                $value,
            ));
        }

        foreach ((array) ($group['groups'] ?? []) as $child) {
            if (is_array($child)) {
                $this->collectRules($child, $parts);
            }
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<int, array{label:string, value:string, delta:string, good:bool}>
     */
    private function kpis(array $payload): array
    {
        $out = [];

        foreach ((array) ($payload['measures'] ?? []) as $measure) {
            $key = (string) ($measure['key'] ?? '');
            $delta = $payload['deltas'][$key] ?? null;

            $out[] = [
                'label' => $this->label((string) ($measure['labelKey'] ?? ''), $key),
                'value' => $this->number($payload['totals'][$key] ?? null, (int) ($measure['decimals'] ?? 0)),
                'delta' => $this->deltaText($delta),
                'good' => (bool) ($delta['good'] ?? true),
            ];
        }

        return array_slice($out, 0, 4);
    }

    /** @param array<string,mixed>|null $delta */
    private function deltaText(?array $delta): string
    {
        if ($delta === null) {
            return '';
        }

        $percent = $delta['percent'] ?? null;
        $sign = ($delta['absolute'] ?? 0) > 0 ? '+' : '';

        return $percent === null
            ? $sign.$this->number($delta['absolute'] ?? 0, 0)
            : $sign.round((float) $percent, 1).'%';
    }

    /** @param array<string,mixed> $payload */
    private function table(PdfDocument $doc, array $payload): void
    {
        $measures = array_values((array) ($payload['measures'] ?? []));
        $compared = ($payload['comparison'] ?? null) !== null;

        $labels = [$this->label((string) ($payload['dimension']['labelKey'] ?? ''), 'key')];
        $widths = [2.2];

        foreach ($measures as $measure) {
            $labels[] = $this->label((string) ($measure['labelKey'] ?? ''), (string) ($measure['key'] ?? ''));
            $widths[] = 1.2;

            if ($compared) {
                $labels[] = __('reportDelivery.pdf.previous');
                $widths[] = 1.2;
                $labels[] = __('reportDelivery.pdf.change');
                $widths[] = 1.0;
            }
        }

        $doc->heading(__('reportDelivery.pdf.data'), 3);
        $doc->tableHeader($labels, $widths);

        foreach ((array) ($payload['rows'] ?? []) as $row) {
            $cells = [(string) ($row['label'] ?? '')];

            foreach ($measures as $measure) {
                $key = (string) ($measure['key'] ?? '');
                $decimals = (int) ($measure['decimals'] ?? 0);
                $cells[] = $this->number($row['values'][$key] ?? null, $decimals);

                if ($compared) {
                    $cells[] = $this->number($row['previous'][$key] ?? null, $decimals);
                    $cells[] = $this->deltaText($row['deltas'][$key] ?? null) ?: '—';
                }
            }

            $doc->tableRow($cells);
        }

        if (($payload['truncated'] ?? false) === true) {
            $doc->paragraph(__('reportDelivery.pdf.truncated', [
                'shown' => count((array) ($payload['rows'] ?? [])),
                'total' => (int) ($payload['groupCount'] ?? 0),
            ]));
        }
    }

    private function number(mixed $value, int $decimals): string
    {
        if ($value === null || ! is_numeric($value)) {
            return '—';
        }

        return number_format((float) $value, $decimals);
    }

    private function printableChartType(string $chart): string
    {
        return match ($chart) {
            'pie', 'doughnut', 'polarArea' => 'pie',
            'line', 'area', 'stackedArea' => 'line',
            default => 'bar',
        };
    }

    private function definitionTitleKey(ReportDefinition $d): string
    {
        return method_exists($d, 'titleKeyValue')
            ? (string) $d->titleKeyValue()
            : 'reports.'.$d->key().'.title';
    }

    /** Falls back to a humanised last segment when no PHP translation exists. */
    private function label(string $key, string $fallback): string
    {
        if ($key === '') {
            return $this->humanise($fallback);
        }

        $translated = __($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : $this->humanise(str_contains($key, '.') ? substr($key, (int) strrpos($key, '.') + 1) : $key);
    }

    private function humanise(string $value): string
    {
        return Str::of($value)->snake(' ')->replace('_', ' ')->trim()->title()->value();
    }
}
