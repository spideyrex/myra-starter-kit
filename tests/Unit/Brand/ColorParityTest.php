<?php

namespace Tests\Unit\Brand;

use App\Brand\Color;
use PHPUnit\Framework\TestCase;

/**
 * The fixture is written by tests/js/brandColorParity.spec.ts from the REAL
 * TypeScript implementation — it is never hand-authored, which is why this is
 * a genuine cross-language parity guard and not a literal restated twice.
 */
class ColorParityTest extends TestCase
{
    /** @return array<int, array{hex:string,oklch:string,isLight:bool,lighten:string,darken:string}> */
    private function entries(): array
    {
        $path = dirname(__DIR__, 2).'/js/fixtures/brand-oklch.json';

        $this->assertFileExists($path, 'Run: MYRA_WRITE_FIXTURES=1 npx vitest run tests/js/brandColorParity.spec.ts');

        $payload = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload['entries']);

        return $payload['entries'];
    }

    public function test_hex_to_oklch_agrees_with_the_typescript_implementation(): void
    {
        foreach ($this->entries() as $entry) {
            $this->assertSame($entry['oklch'], Color::hexToOklch($entry['hex']), $entry['hex']);
        }
    }

    public function test_is_light_agrees_with_the_typescript_implementation(): void
    {
        foreach ($this->entries() as $entry) {
            $this->assertSame($entry['isLight'], Color::isLight($entry['hex']), $entry['hex']);
        }
    }

    public function test_adjust_lightness_agrees_with_the_typescript_implementation(): void
    {
        foreach ($this->entries() as $entry) {
            $this->assertSame($entry['lighten'], Color::adjustLightness($entry['hex'], 0.08), $entry['hex']);
            $this->assertSame($entry['darken'], Color::adjustLightness($entry['hex'], -0.08), $entry['hex']);
        }
    }

    public function test_the_sweep_is_wide_enough_to_be_meaningful(): void
    {
        $this->assertGreaterThanOrEqual(256, count($this->entries()));
    }
}
