<?php

namespace Tests\Feature\Brand;

use App\Brand\BrandManager;
use Tests\TestCase;

/**
 * Keeps tests/js/fixtures/brand-tokens.json in step with the REAL resolver, so
 * the vitest specs consume a genuine server payload rather than a literal.
 *
 * Regenerate with:  MYRA_WRITE_FIXTURES=1 php artisan test --filter=BrandFixtureTest
 */
class BrandFixtureTest extends TestCase
{
    use SeedsBrand;

    private const PATH = 'tests/js/fixtures/brand-tokens.json';

    public function test_the_committed_fixture_matches_the_real_resolver(): void
    {
        $this->seedBrand([
            'accent' => '#f59e0b',
            'sidebar_background' => '#111827',
            'sidebar_foreground' => '#e5e7eb',
            'sidebar_accent' => '#374151',
            'radius' => '0.5rem',
            'font_sans' => 'inter',
        ]);

        $brand = app(BrandManager::class)->current();

        $payload = [
            'brand' => array_diff_key($brand->toArray(), ['hash' => null]),
            'light' => $brand->cssVariables(false),
            'dark' => $brand->cssVariables(true),
        ];

        $path = base_path(self::PATH);

        if (env('MYRA_WRITE_FIXTURES') === '1' || ! is_file($path)) {
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }

        $this->assertSame(
            $payload,
            json_decode((string) file_get_contents($path), true),
            'Run: MYRA_WRITE_FIXTURES=1 php artisan test --filter=BrandFixtureTest',
        );
    }
}
