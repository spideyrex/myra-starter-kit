<?php

namespace Tests\Feature\Brand;

use App\Brand\BrandPalette;
use App\Brand\Color;
use PHPUnit\Framework\TestCase;

class BrandContrastTest extends TestCase
{
    public function test_every_preset_reaches_aa_against_its_picked_foreground(): void
    {
        foreach (array_keys(BrandPalette::PRESETS) as $preset) {
            $palette = BrandPalette::fromPreset($preset);

            foreach ([false, true] as $dark) {
                $tokens = $dark ? $palette->dark() : $palette->light();

                $this->assertArrayHasKey('--primary', $tokens);
                $this->assertGreaterThanOrEqual(
                    4.5,
                    $palette->contrastOn($palette->primaryHex),
                    $preset.($dark ? ' (dark)' : ' (light)'),
                );
            }
        }
    }

    public function test_the_foreground_pick_is_measured_not_guessed(): void
    {
        $palette = BrandPalette::fromPreset('yellow');

        // Yellow is light: black must win, and by measurement.
        $this->assertSame('#000000', $palette->foregroundOn($palette->primaryHex));
        $this->assertGreaterThan(
            Color::contrastRatio($palette->primaryHex, '#ffffff'),
            Color::contrastRatio($palette->primaryHex, '#000000'),
        );
    }

    public function test_the_worst_possible_background_still_clears_aa(): void
    {
        // L ~= 0.179 is the crossover where black and white are equally poor.
        $palette = new BrandPalette('#767676');

        $this->assertGreaterThanOrEqual(4.5, $palette->contrastOn('#767676'));
        $this->assertGreaterThanOrEqual(4.5, $palette->contrastOn('#5e5e5e'));
    }

    public function test_chart_series_are_deterministic_and_normalised(): void
    {
        $palette = BrandPalette::fromPreset('blue');

        $first = $palette->chartSeries(8);

        $this->assertCount(8, $first);
        $this->assertSame($first, $palette->chartSeries(8));

        foreach ($first as $rgb) {
            foreach ($rgb as $channel) {
                $this->assertGreaterThanOrEqual(0.0, $channel);
                $this->assertLessThanOrEqual(1.0, $channel);
            }
        }
    }
}
