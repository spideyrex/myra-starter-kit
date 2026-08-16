<?php

namespace Tests\Feature\Brand;

use App\Brand\Brand;
use App\Brand\BrandManager;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandFallbackTest extends TestCase
{
    public function test_nothing_throws_when_the_settings_table_is_unreachable(): void
    {
        Schema::drop('settings');

        app()->forgetInstance(BrandManager::class);
        $brand = app(BrandManager::class)->current();

        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertFalse($brand->enabled);
        $this->assertNotSame('', $brand->name);
        $this->assertSame('', (string) app(BrandManager::class)->styleTag());
    }

    public function test_the_fallback_never_throws_and_always_has_a_mark(): void
    {
        $brand = Brand::fallback();

        $this->assertNotSame('', $brand->initial());
        $this->assertSame('0.625rem', $brand->radius);
        $this->assertIsArray($brand->cssVariables());
    }

    public function test_an_empty_name_still_yields_a_mark(): void
    {
        $brand = new Brand(
            false, '   ', '', '', '', null, null, null, null, null, 'header',
            \App\Brand\BrandPalette::fromPreset('zinc'),
            new \App\Brand\BrandTypography(), '0.625rem', false, 'abcdefgh',
        );

        $this->assertSame('?', $brand->initial());
    }
}
