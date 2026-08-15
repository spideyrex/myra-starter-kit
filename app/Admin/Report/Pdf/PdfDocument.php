<?php

namespace App\Admin\Report\Pdf;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF 1.4 writer over the 14 core fonts (Helvetica family). Page content
 * streams to a tempnam() file so memory is O(1); close() assembles the xref
 * table and trailer, which is why bytes only reach the client at close().
 * The streaming guarantee here is BOUNDED MEMORY, not incremental delivery —
 * the same caveat that already applies to xlsx.
 *
 * Content-stream lengths are written as indirect objects so a page never has
 * to be buffered to learn its own size.
 *
 * WinAnsi only: there is no font embedding, so non-Latin text cannot render.
 * isSupported()/canRender() gate this.
 */
final class PdfDocument
{
    private const OBJ_CATALOG = 1;

    private const OBJ_PAGES = 2;

    private const OBJ_FONT_REGULAR = 3;

    private const OBJ_FONT_BOLD = 4;

    private const OBJ_FONT_ITALIC = 5;

    private const OBJ_INFO = 6;

    private const FIRST_FREE_OBJ = 7;

    /** cp1252 codepoints that live outside Latin-1, keyed by Unicode. */
    private const WIN_ANSI_SPECIALS = [
        0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85,
        0x2020 => 0x86, 0x2021 => 0x87, 0x02C6 => 0x88, 0x2030 => 0x89, 0x0160 => 0x8A,
        0x2039 => 0x8B, 0x0152 => 0x8C, 0x017D => 0x8E, 0x2018 => 0x91, 0x2019 => 0x92,
        0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97,
        0x02DC => 0x98, 0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B, 0x0153 => 0x9C,
        0x017E => 0x9E, 0x0178 => 0x9F,
    ];

    /** @var resource|null */
    private $fh = null;

    private string $path = '';

    /** @var array<int,int> object number => byte offset */
    private array $offsets = [];

    private int $nextObj = self::FIRST_FREE_OBJ;

    /** @var int[] */
    private array $pageIds = [];

    /** @var array<string, array{obj:int, lengthObj:int, width:int, height:int, data:string}> */
    private array $images = [];

    /** @var string[] hashes whose object bodies have not been written yet */
    private array $pendingImages = [];

    private int $bytes = 0;

    private bool $pageOpen = false;

    private int $contentObj = 0;

    private int $lengthObj = 0;

    private int $streamStart = 0;

    private float $pageWidth = 595.276;

    private float $pageHeight = 841.89;

    private float $margin = 48.0;

    private float $y = 0.0;

    private string $title = '';

    private string $author = '';

    private string $headerTitle = '';

    private string $headerSubtitle = '';

    private string $footerLeft = '';

    private bool $pageNumbers = true;

    /** @var array{labels: string[], widths: float[]}|null */
    private ?array $tableHead = null;

    private function __construct() {}

    /** Kept for parity with XlsxRowWriter: the writer has no optional extension. */
    public static function isSupported(): bool
    {
        return true;
    }

    /** False when $text contains a codepoint outside WinAnsiEncoding. */
    public static function canRender(string $text): bool
    {
        foreach (self::codepoints($text) as $cp) {
            if ($cp === 0x09 || $cp === 0x0A || $cp === 0x0D) {
                continue;
            }
            if ($cp >= 0x20 && $cp <= 0x7E) {
                continue;
            }
            if ($cp >= 0xA0 && $cp <= 0xFF) {
                continue;
            }
            if (isset(self::WIN_ANSI_SPECIALS[$cp])) {
                continue;
            }

            return false;
        }

        return true;
    }

    public static function make(string $size = 'A4', string $orientation = 'portrait'): self
    {
        $doc = new self;

        [$w, $h] = match (strtoupper($size)) {
            'LETTER' => [612.0, 792.0],
            'LEGAL' => [612.0, 1008.0],
            default => [595.276, 841.89],
        };

        if (strtolower($orientation) === 'landscape') {
            [$w, $h] = [$h, $w];
        }

        $doc->pageWidth = $w;
        $doc->pageHeight = $h;

        $path = tempnam(sys_get_temp_dir(), 'myra-pdf-');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary file for the PDF.');
        }

        $doc->path = $path;
        $doc->fh = fopen($path, 'w+b');
        $doc->write("%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");

        return $doc;
    }

    public function meta(string $title, string $author): self
    {
        $this->title = $title;
        $this->author = $author;

        return $this;
    }

    public function runningHeader(string $title, string $subtitle = ''): self
    {
        $this->headerTitle = $title;
        $this->headerSubtitle = $subtitle;

        return $this;
    }

    public function runningFooter(string $left, bool $pageNumbers = true): self
    {
        $this->footerLeft = $left;
        $this->pageNumbers = $pageNumbers;

        return $this;
    }

    // ---- layout -----------------------------------------------------------

    public function heading(string $text, int $level = 1): self
    {
        $size = match (max(1, min(3, $level))) {
            1 => 17.0,
            2 => 13.0,
            default => 11.0,
        };

        $this->ensure($size + 10);
        $this->y -= $size + 4;
        $this->text($text, $this->margin, $this->y, $size, 'bold');
        $this->y -= 6;

        return $this;
    }

    public function paragraph(string $text): self
    {
        foreach ($this->wrap($text, $this->contentWidth(), 10.0, 'regular') as $line) {
            $this->ensure(14);
            $this->y -= 13;
            $this->text($line, $this->margin, $this->y, 10.0, 'regular');
        }

        $this->y -= 5;

        return $this;
    }

    /** @param array<string,string> $pairs */
    public function keyValues(array $pairs): self
    {
        $labelWidth = min(150.0, $this->contentWidth() * 0.32);

        foreach ($pairs as $label => $value) {
            $this->ensure(15);
            $this->y -= 13;
            $this->text((string) $label, $this->margin, $this->y, 9.5, 'bold', [0.35, 0.38, 0.44]);

            foreach ($this->wrap((string) $value, $this->contentWidth() - $labelWidth, 9.5, 'regular') as $i => $line) {
                if ($i > 0) {
                    $this->ensure(13);
                    $this->y -= 12;
                }
                $this->text($line, $this->margin + $labelWidth, $this->y, 9.5, 'regular');
            }
        }

        $this->y -= 6;

        return $this;
    }

    /** @param array<int, array{label:string, value:string, delta?:string, direction?:string, good?:bool}> $kpis */
    public function kpiRow(array $kpis): self
    {
        $kpis = array_values($kpis);

        if ($kpis === []) {
            return $this;
        }

        $count = count($kpis);
        $gap = 10.0;
        $boxWidth = ($this->contentWidth() - $gap * ($count - 1)) / $count;
        $boxHeight = 56.0;

        $this->ensure($boxHeight + 10);
        $top = $this->y - 4;

        foreach ($kpis as $i => $kpi) {
            $x = $this->margin + $i * ($boxWidth + $gap);
            $this->rect($x, $top - $boxHeight, $boxWidth, $boxHeight, [0.96, 0.97, 0.98]);
            $this->text($this->clip((string) ($kpi['label'] ?? ''), $boxWidth - 16, 8.5, 'regular'), $x + 8, $top - 18, 8.5, 'regular', [0.35, 0.38, 0.44]);
            $this->text($this->clip((string) ($kpi['value'] ?? ''), $boxWidth - 16, 16.0, 'bold'), $x + 8, $top - 38, 16.0, 'bold');

            $delta = (string) ($kpi['delta'] ?? '');

            if ($delta !== '') {
                $good = (bool) ($kpi['good'] ?? true);
                $colour = $good ? [0.11, 0.51, 0.30] : [0.72, 0.18, 0.20];
                $this->text($this->clip($delta, $boxWidth - 16, 8.5, 'regular'), $x + 8, $top - 50, 8.5, 'regular', $colour);
            }
        }

        $this->y = $top - $boxHeight - 10;

        return $this;
    }

    /**
     * @param  string[]  $labels
     * @param  float[]  $widths  relative weights; normalised to the content width
     */
    public function tableHeader(array $labels, array $widths = []): self
    {
        $labels = array_values(array_map('strval', $labels));
        $this->tableHead = ['labels' => $labels, 'widths' => $this->normaliseWidths($labels, $widths)];

        $this->ensure(30);
        $this->drawTableHead();

        return $this;
    }

    public function tableRow(array $values): self
    {
        if ($this->tableHead === null) {
            $this->tableHeader(array_map(static fn ($k) => (string) $k, array_keys($values)));
        }

        if (! $this->fits(16)) {
            $this->pageBreak();
            $this->drawTableHead();
        }

        $this->y -= 15;
        $x = $this->margin;
        $i = 0;

        foreach (array_values($values) as $value) {
            $width = $this->tableHead['widths'][$i] ?? ($this->contentWidth() / max(1, count($values)));
            $this->text($this->clip((string) $value, $width - 8, 9.0, 'regular'), $x + 2, $this->y, 9.0, 'regular');
            $x += $width;
            $i++;
        }

        $this->line($this->margin, $this->y - 4, $this->margin + $this->contentWidth(), $this->y - 4, [0.90, 0.91, 0.93]);

        return $this;
    }

    public function chart(ChartVector $chart, float $height = 180.0): self
    {
        if (! $this->fits($height + 16)) {
            $this->pageBreak();
        }

        $this->y -= $height + 8;
        $chart->draw($this, $this->margin, $this->y, $this->contentWidth(), $height);
        $this->y -= 8;

        return $this;
    }

    /** Decoration only. A no-op when GD is unavailable — the vector chart stands in. */
    public function imagePng(string $binary, float $width, float $height): self
    {
        $ref = $this->registerImage($binary);

        if ($ref === null) {
            return $this;
        }

        if (! $this->fits($height + 12)) {
            $this->pageBreak();
        }

        $this->y -= $height + 6;
        $this->raw(sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $width, $height, $this->margin, $this->y, $ref,
        ));

        return $this;
    }

    public function pageBreak(): self
    {
        $this->endPage();
        $this->beginPage();

        return $this;
    }

    public function flush(): void
    {
        if (is_resource($this->fh)) {
            fflush($this->fh);
        }
    }

    public function close(): void
    {
        if (! is_resource($this->fh)) {
            return;
        }

        $this->endPage();

        if ($this->pageIds === []) {
            $this->beginPage();
            $this->endPage();
        }

        $this->writeFonts();
        $this->writeObject(self::OBJ_INFO, sprintf(
            '<< /Title (%s) /Author (%s) /Producer (Myra) /CreationDate (D:%s) >>',
            $this->escape($this->title ?: 'Report'),
            $this->escape($this->author ?: 'Myra'),
            date('YmdHis'),
        ));

        $kids = implode(' ', array_map(static fn (int $id) => $id.' 0 R', $this->pageIds));
        $this->writeObject(self::OBJ_PAGES, sprintf(
            '<< /Type /Pages /Count %d /Kids [%s] /MediaBox [0 0 %.3F %.3F] >>',
            count($this->pageIds), $kids, $this->pageWidth, $this->pageHeight,
        ));
        $this->writeObject(self::OBJ_CATALOG, '<< /Type /Catalog /Pages '.self::OBJ_PAGES.' 0 R >>');

        $this->writeXref();

        fclose($this->fh);
        $this->fh = null;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function stream(string $filename): StreamedResponse
    {
        $path = $this->path;

        return new StreamedResponse(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    // ---- drawing primitives, used by ChartVector --------------------------

    public function contentWidth(): float
    {
        return $this->pageWidth - 2 * $this->margin;
    }

    public function marginSize(): float
    {
        return $this->margin;
    }

    /** @param array{0:float,1:float,2:float}|null $colour */
    public function text(string $value, float $x, float $y, float $size = 10.0, string $weight = 'regular', ?array $colour = null): void
    {
        $this->open();

        $font = match ($weight) {
            'bold' => 'F2',
            'italic' => 'F3',
            default => 'F1',
        };

        $rgb = $colour ?? [0.07, 0.09, 0.13];

        $this->raw(sprintf(
            "BT %.3F %.3F %.3F rg /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $font, $size, $x, $y, $this->escape($value),
        ));
    }

    /** @param array{0:float,1:float,2:float} $colour */
    public function rect(float $x, float $y, float $w, float $h, array $colour, bool $stroke = false): void
    {
        $this->open();
        $op = $stroke ? 'S' : 'f';
        $set = $stroke ? 'RG' : 'rg';

        $this->raw(sprintf(
            "%.3F %.3F %.3F %s %.2F %.2F %.2F %.2F re %s\n",
            $colour[0], $colour[1], $colour[2], $set, $x, $y, $w, $h, $op,
        ));
    }

    /** @param array{0:float,1:float,2:float} $colour */
    public function line(float $x1, float $y1, float $x2, float $y2, array $colour, float $width = 0.5): void
    {
        $this->open();
        $this->raw(sprintf(
            "%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $colour[0], $colour[1], $colour[2], $width, $x1, $y1, $x2, $y2,
        ));
    }

    /** @param array<int, array{0:float,1:float}> $points */
    public function polyline(array $points, array $colour, float $width = 1.2): void
    {
        $points = array_values($points);

        if (count($points) < 2) {
            return;
        }

        $this->open();
        $ops = sprintf("%.3F %.3F %.3F RG %.2F w ", $colour[0], $colour[1], $colour[2], $width);
        $ops .= sprintf('%.2F %.2F m ', $points[0][0], $points[0][1]);

        foreach (array_slice($points, 1) as $point) {
            $ops .= sprintf('%.2F %.2F l ', $point[0], $point[1]);
        }

        $this->raw($ops."S\n");
    }

    /** Filled circular sector, used by the pie renderer. */
    public function sector(float $cx, float $cy, float $r, float $from, float $to, array $colour): void
    {
        $this->open();
        $steps = max(2, (int) ceil(abs($to - $from) / 0.15));
        $ops = sprintf("%.3F %.3F %.3F rg %.2F %.2F m ", $colour[0], $colour[1], $colour[2], $cx, $cy);

        for ($i = 0; $i <= $steps; $i++) {
            $angle = $from + ($to - $from) * ($i / $steps);
            $ops .= sprintf('%.2F %.2F l ', $cx + cos($angle) * $r, $cy + sin($angle) * $r);
        }

        $this->raw($ops."h f\n");
    }

    public function stringWidth(string $text, float $size, string $weight = 'regular'): float
    {
        $widths = $weight === 'bold' ? FontMetrics::HELVETICA_BOLD : FontMetrics::HELVETICA;
        $total = 0;

        foreach (str_split(self::winAnsi($text)) as $char) {
            $total += $widths[ord($char)] ?? 556;
        }

        return $total / 1000 * $size;
    }

    // ---- internals --------------------------------------------------------

    private function fits(float $height): bool
    {
        return ($this->y - $height) > ($this->margin + 24);
    }

    private function ensure(float $height): void
    {
        $this->open();

        if (! $this->fits($height)) {
            $this->pageBreak();
        }
    }

    private function open(): void
    {
        if (! $this->pageOpen) {
            $this->beginPage();
        }
    }

    private function beginPage(): void
    {
        $this->contentObj = $this->nextObj++;
        $this->lengthObj = $this->nextObj++;

        $this->offsets[$this->contentObj] = $this->bytes;
        $this->write($this->contentObj." 0 obj\n<< /Length ".$this->lengthObj." 0 R >>\nstream\n");
        $this->streamStart = $this->bytes;
        $this->pageOpen = true;
        $this->y = $this->pageHeight - $this->margin;

        $this->drawRunningChrome();
    }

    private function endPage(): void
    {
        if (! $this->pageOpen) {
            return;
        }

        $length = $this->bytes - $this->streamStart;
        $this->write("endstream\nendobj\n");

        $this->offsets[$this->lengthObj] = $this->bytes;
        $this->write($this->lengthObj." 0 obj\n".$length."\nendobj\n");

        $pageId = $this->nextObj++;
        $this->offsets[$pageId] = $this->bytes;
        $this->write(sprintf(
            "%d 0 obj\n<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.3F %.3F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R /F3 %d 0 R >>%s >> /Contents %d 0 R >>\nendobj\n",
            $pageId, self::OBJ_PAGES, $this->pageWidth, $this->pageHeight,
            self::OBJ_FONT_REGULAR, self::OBJ_FONT_BOLD, self::OBJ_FONT_ITALIC,
            $this->imageResources(), $this->contentObj,
        ));

        $this->pageIds[] = $pageId;
        $this->pageOpen = false;

        $this->writePendingImages();
    }

    private function drawRunningChrome(): void
    {
        $top = $this->pageHeight - 26;

        if ($this->headerTitle !== '') {
            $this->text($this->clip($this->headerTitle, $this->contentWidth() * 0.7, 9.0, 'bold'), $this->margin, $top, 9.0, 'bold', [0.35, 0.38, 0.44]);
        }

        if ($this->headerSubtitle !== '') {
            $width = $this->stringWidth($this->headerSubtitle, 8.5);
            $this->text($this->headerSubtitle, $this->pageWidth - $this->margin - $width, $top, 8.5, 'regular', [0.45, 0.48, 0.54]);
        }

        if ($this->headerTitle !== '' || $this->headerSubtitle !== '') {
            $this->line($this->margin, $top - 7, $this->pageWidth - $this->margin, $top - 7, [0.87, 0.89, 0.91]);
            $this->y = $top - 20;
        }

        $bottom = $this->margin - 18;

        if ($this->footerLeft !== '') {
            $this->text($this->clip($this->footerLeft, $this->contentWidth() * 0.75, 8.0, 'regular'), $this->margin, $bottom, 8.0, 'regular', [0.45, 0.48, 0.54]);
        }

        if ($this->pageNumbers) {
            $label = (string) (count($this->pageIds) + 1);
            $width = $this->stringWidth($label, 8.0);
            $this->text($label, $this->pageWidth - $this->margin - $width, $bottom, 8.0, 'regular', [0.45, 0.48, 0.54]);
        }
    }

    private function drawTableHead(): void
    {
        if ($this->tableHead === null) {
            return;
        }

        $this->open();
        $this->y -= 16;
        $this->rect($this->margin, $this->y - 5, $this->contentWidth(), 18, [0.94, 0.95, 0.97]);

        $x = $this->margin;

        foreach ($this->tableHead['labels'] as $i => $label) {
            $width = $this->tableHead['widths'][$i];
            $this->text($this->clip($label, $width - 8, 9.0, 'bold'), $x + 2, $this->y, 9.0, 'bold', [0.25, 0.28, 0.34]);
            $x += $width;
        }

        $this->y -= 4;
    }

    /**
     * @param  string[]  $labels
     * @param  float[]  $widths
     * @return float[]
     */
    private function normaliseWidths(array $labels, array $widths): array
    {
        $count = max(1, count($labels));
        $widths = array_values(array_map('floatval', $widths));

        if (count($widths) !== $count) {
            $widths = array_fill(0, $count, 1.0);
        }

        $sum = array_sum($widths) ?: 1.0;

        return array_map(fn (float $w) => $w / $sum * $this->contentWidth(), $widths);
    }

    /** @return string[] */
    private function wrap(string $text, float $width, float $size, string $weight): array
    {
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $paragraph) {
            $current = '';

            foreach (preg_split('/\s+/', trim($paragraph)) ?: [] as $word) {
                if ($word === '') {
                    continue;
                }

                $candidate = $current === '' ? $word : $current.' '.$word;

                if ($this->stringWidth($candidate, $size, $weight) <= $width || $current === '') {
                    $current = $candidate;

                    continue;
                }

                $lines[] = $current;
                $current = $word;
            }

            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    private function clip(string $text, float $width, float $size, string $weight): string
    {
        if ($this->stringWidth($text, $size, $weight) <= $width) {
            return $text;
        }

        $out = '';

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if ($this->stringWidth($out.$char.'…', $size, $weight) > $width) {
                break;
            }
            $out .= $char;
        }

        return $out.'…';
    }

    private function registerImage(string $binary): ?string
    {
        $hash = md5($binary);

        if (isset($this->images[$hash])) {
            return 'Im'.$this->images[$hash]['obj'];
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('gzcompress')) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return null;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $samples = '';

        for ($y = 0; $y < $h; $y++) {
            $row = '';
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $row .= chr(($rgb >> 16) & 0xFF).chr(($rgb >> 8) & 0xFF).chr($rgb & 0xFF);
            }
            $samples .= $row;
        }

        imagedestroy($image);

        $obj = $this->nextObj++;

        // Object numbers are allocated now but the body is deferred: writing it
        // here would land the samples inside the open page content stream.
        $this->images[$hash] = [
            'obj' => $obj,
            'lengthObj' => $this->nextObj++,
            'width' => $w,
            'height' => $h,
            'data' => gzcompress($samples, 6),
        ];
        $this->pendingImages[] = $hash;

        return 'Im'.$obj;
    }

    private function writePendingImages(): void
    {
        foreach ($this->pendingImages as $hash) {
            $image = $this->images[$hash];

            $this->offsets[$image['obj']] = $this->bytes;
            $this->write(sprintf(
                "%d 0 obj\n<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length %d 0 R >>\nstream\n",
                $image['obj'], $image['width'], $image['height'], $image['lengthObj'],
            ));
            $this->write($image['data']);
            $this->write("\nendstream\nendobj\n");

            $this->offsets[$image['lengthObj']] = $this->bytes;
            $this->write($image['lengthObj']." 0 obj\n".strlen($image['data'])."\nendobj\n");

            $this->images[$hash]['data'] = '';
        }

        $this->pendingImages = [];
    }

    private function imageResources(): string
    {
        if ($this->images === []) {
            return '';
        }

        $entries = '';

        foreach ($this->images as $image) {
            $entries .= sprintf(' /Im%d %d 0 R', $image['obj'], $image['obj']);
        }

        return ' /XObject <<'.$entries.' >>';
    }

    private function writeFonts(): void
    {
        $this->writeObject(self::OBJ_FONT_REGULAR, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $this->writeObject(self::OBJ_FONT_BOLD, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $this->writeObject(self::OBJ_FONT_ITALIC, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>');
    }

    private function writeObject(int $number, string $body): void
    {
        $this->offsets[$number] = $this->bytes;
        $this->write($number." 0 obj\n".$body."\nendobj\n");
    }

    private function writeXref(): void
    {
        $max = $this->nextObj;
        $start = $this->bytes;

        $this->write("xref\n0 ".$max."\n");
        $this->write("0000000000 65535 f \n");

        for ($i = 1; $i < $max; $i++) {
            $this->write(sprintf("%010d 00000 n \n", $this->offsets[$i] ?? 0));
        }

        $this->write(sprintf(
            "trailer\n<< /Size %d /Root %d 0 R /Info %d 0 R >>\nstartxref\n%d\n%%%%EOF\n",
            $max, self::OBJ_CATALOG, self::OBJ_INFO, $start,
        ));
    }

    private function raw(string $ops): void
    {
        $this->write($ops);
    }

    private function write(string $chunk): void
    {
        if (! is_resource($this->fh)) {
            throw new RuntimeException('The PDF document is already closed.');
        }

        fwrite($this->fh, $chunk);
        $this->bytes += strlen($chunk);
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', ' ', ' '],
            self::winAnsi($value),
        );
    }

    /** @return int[] */
    private static function codepoints(string $utf8): array
    {
        $out = [];

        foreach (preg_split('//u', $utf8, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $bytes = unpack('N', str_pad(mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'), 4, "\0", STR_PAD_LEFT));
            $out[] = $bytes[1] ?? 0;
        }

        return $out;
    }

    private static function winAnsi(string $utf8): string
    {
        $out = '';

        foreach (self::codepoints($utf8) as $cp) {
            if ($cp < 0x100) {
                $out .= chr($cp);

                continue;
            }

            $out .= chr(self::WIN_ANSI_SPECIALS[$cp] ?? 0x3F);
        }

        return $out;
    }
}
