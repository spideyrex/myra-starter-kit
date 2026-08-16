<?php

namespace App\Admin\Report\Pdf;

use App\Admin\Report\ReportResult;

/**
 * ReportResult -> native PDF vector operators. Bar, line and pie only: the
 * three that carry meaning in static print. No raster, no headless browser.
 * Every other chart type degrades to its data table, which the document
 * already prints below the chart.
 */
final class ChartVector
{
    public const MAX_CATEGORIES = 24;

    public const MAX_SLICES = 8;

    /** @var array<int, array{0:float,1:float,2:float}> */
    private const PALETTE = [
        [0.23, 0.45, 0.85], [0.11, 0.62, 0.47], [0.90, 0.55, 0.13], [0.75, 0.25, 0.42],
        [0.42, 0.34, 0.76], [0.16, 0.63, 0.72], [0.62, 0.55, 0.16], [0.55, 0.35, 0.28],
    ];

    // >>> MYRA v2.6 [C] START
    /**
     * A null palette is today's constants, byte for byte.
     *
     * @return array<int, array{0:float,1:float,2:float}>
     */
    public static function palette(?\App\Brand\BrandPalette $b = null): array
    {
        return $b === null ? self::PALETTE : $b->chartSeries(count(self::PALETTE));
    }

    /** @var array<int, array{0:float,1:float,2:float}>|null */
    private ?array $brandSwatches = null;

    public function withPalette(?\App\Brand\BrandPalette $b): self
    {
        $this->brandSwatches = self::palette($b);

        return $this;
    }

    /** @return array<int, array{0:float,1:float,2:float}> */
    private function swatches(): array
    {
        return $this->brandSwatches ?? self::PALETTE;
    }
    // <<< MYRA v2.6 [C] END

    private const AXIS = [0.55, 0.58, 0.63];

    private const GRID = [0.89, 0.90, 0.92];

    private const TICK = [0.35, 0.38, 0.44];

    /**
     * @param  string[]  $labels
     * @param  array<string, array<int, float|null>>  $series
     */
    private function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly array $labels,
        private readonly array $series,
    ) {}

    public static function fromResult(ReportResult $result, string $type, string $title): self
    {
        $rows = array_values($result->rows());
        $type = in_array($type, ['bar', 'line', 'pie'], true) ? $type : 'bar';
        $limit = $type === 'pie' ? self::MAX_SLICES : self::MAX_CATEGORIES;
        $rows = array_slice($rows, 0, $limit);

        $labels = [];
        $series = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row->label;

            foreach ((array) $row->values as $measure => $value) {
                $series[$measure][] = is_numeric($value) ? (float) $value : null;
            }
        }

        // A pie can only honestly show one measure.
        if ($type === 'pie' && count($series) > 1) {
            $series = array_slice($series, 0, 1, true);
        }

        return new self($type, $title, $labels, $series);
    }

    public function draw(PdfDocument $doc, float $x, float $y, float $w, float $h): void
    {
        if ($this->title !== '') {
            $doc->text($this->title, $x, $y + $h + 4, 10.0, 'bold');
        }

        if ($this->labels === [] || $this->series === []) {
            $doc->text('—', $x, $y + $h / 2, 10.0, 'regular', self::TICK);

            return;
        }

        match ($this->type) {
            'pie' => $this->drawPie($doc, $x, $y, $w, $h),
            'line' => $this->drawLine($doc, $x, $y, $w, $h),
            default => $this->drawBar($doc, $x, $y, $w, $h),
        };

        $this->drawLegend($doc, $x, $y - 4, $w);
    }

    private function drawBar(PdfDocument $doc, float $x, float $y, float $w, float $h): void
    {
        [$plotX, $plotY, $plotW, $plotH] = $this->plot($x, $y, $w, $h);
        $max = $this->maxValue();
        $this->drawGrid($doc, $plotX, $plotY, $plotW, $plotH, $max);

        $groups = count($this->labels);
        $measures = array_keys($this->series);
        $slot = $plotW / max(1, $groups);
        $barWidth = max(2.0, ($slot * 0.7) / max(1, count($measures)));

        foreach ($measures as $m => $measure) {
            foreach ($this->series[$measure] as $i => $value) {
                if ($value === null || $value <= 0) {
                    continue;
                }

                $height = $plotH * ($value / $max);
                $bx = $plotX + $i * $slot + $slot * 0.15 + $m * $barWidth;
                $doc->rect($bx, $plotY, $barWidth, $height, $this->swatches()[$m % count($this->swatches())]);
            }
        }

        $this->drawCategoryTicks($doc, $plotX, $plotY, $slot);
    }

    private function drawLine(PdfDocument $doc, float $x, float $y, float $w, float $h): void
    {
        [$plotX, $plotY, $plotW, $plotH] = $this->plot($x, $y, $w, $h);
        $max = $this->maxValue();
        $this->drawGrid($doc, $plotX, $plotY, $plotW, $plotH, $max);

        $count = count($this->labels);
        $step = $count > 1 ? $plotW / ($count - 1) : 0.0;

        foreach (array_keys($this->series) as $m => $measure) {
            $points = [];

            foreach ($this->series[$measure] as $i => $value) {
                if ($value === null) {
                    continue;
                }
                $points[] = [$plotX + $i * $step, $plotY + $plotH * ($value / $max)];
            }

            $doc->polyline($points, $this->swatches()[$m % count($this->swatches())], 1.4);
        }

        $this->drawCategoryTicks($doc, $plotX, $plotY, $step > 0 ? $step : $plotW);
    }

    private function drawPie(PdfDocument $doc, float $x, float $y, float $w, float $h): void
    {
        $measure = array_key_first($this->series);
        $values = array_map(static fn ($v) => max(0.0, (float) ($v ?? 0)), $this->series[$measure]);
        $total = array_sum($values);

        if ($total <= 0) {
            $doc->text('—', $x, $y + $h / 2, 10.0, 'regular', self::TICK);

            return;
        }

        $radius = min($w * 0.30, $h * 0.45);
        $cx = $x + $radius + 8;
        $cy = $y + $h / 2;
        $angle = M_PI / 2;

        foreach ($values as $i => $value) {
            $sweep = ($value / $total) * 2 * M_PI;
            $doc->sector($cx, $cy, $radius, $angle, $angle + $sweep, $this->swatches()[$i % count($this->swatches())]);
            $angle += $sweep;
        }

        $legendX = $cx + $radius + 18;
        $rowY = $cy + $radius;

        foreach ($this->labels as $i => $label) {
            $doc->rect($legendX, $rowY - 6, 7, 7, $this->swatches()[$i % count($this->swatches())]);
            $percent = $total > 0 ? round($values[$i] / $total * 100, 1) : 0.0;
            $doc->text($label.'  '.$percent.'%', $legendX + 12, $rowY - 5, 8.5, 'regular', self::TICK);
            $rowY -= 13;
        }
    }

    /** @return array{0:float,1:float,2:float,3:float} */
    private function plot(float $x, float $y, float $w, float $h): array
    {
        $left = 46.0;
        $bottom = 18.0;

        return [$x + $left, $y + $bottom, max(10.0, $w - $left - 6), max(10.0, $h - $bottom - 10)];
    }

    private function maxValue(): float
    {
        $max = 0.0;

        foreach ($this->series as $values) {
            foreach ($values as $value) {
                if ($value !== null) {
                    $max = max($max, (float) $value);
                }
            }
        }

        return $max > 0 ? $max : 1.0;
    }

    private function drawGrid(PdfDocument $doc, float $x, float $y, float $w, float $h, float $max): void
    {
        for ($i = 0; $i <= 4; $i++) {
            $gy = $y + $h * ($i / 4);
            $doc->line($x, $gy, $x + $w, $gy, self::GRID);
            $doc->text($this->shortNumber($max * ($i / 4)), $x - 42, $gy - 3, 7.5, 'regular', self::TICK);
        }

        $doc->line($x, $y, $x, $y + $h, self::AXIS);
    }

    private function drawCategoryTicks(PdfDocument $doc, float $x, float $y, float $step): void
    {
        $count = count($this->labels);
        $every = (int) max(1, ceil($count / 12));

        foreach ($this->labels as $i => $label) {
            if ($i % $every !== 0) {
                continue;
            }

            $doc->text(mb_substr($label, 0, 10), $x + $i * $step + 1, $y - 10, 7.5, 'regular', self::TICK);
        }
    }

    private function drawLegend(PdfDocument $doc, float $x, float $y, float $w): void
    {
        if ($this->type === 'pie' || count($this->series) < 2) {
            return;
        }

        $offset = 0.0;

        foreach (array_keys($this->series) as $i => $measure) {
            $doc->rect($x + $offset, $y - 6, 7, 7, $this->swatches()[$i % count($this->swatches())]);
            $doc->text($measure, $x + $offset + 11, $y - 5, 8.0, 'regular', self::TICK);
            $offset += 22 + $doc->stringWidth($measure, 8.0);

            if ($offset > $w - 40) {
                return;
            }
        }
    }

    private function shortNumber(float $value): string
    {
        if (abs($value) >= 1_000_000) {
            return round($value / 1_000_000, 1).'M';
        }

        if (abs($value) >= 1_000) {
            return round($value / 1_000, 1).'k';
        }

        return (string) round($value, abs($value) < 10 ? 1 : 0);
    }
}
