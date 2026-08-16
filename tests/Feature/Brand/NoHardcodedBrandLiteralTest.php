<?php

namespace Tests\Feature\Brand;

use PHPUnit\Framework\TestCase;

/**
 * A CONTENT SCAN, not a file-hash or file-count guard: it asserts a property of
 * what the shipped source says, so editing this bundle's own files cannot
 * invalidate it. Only introducing a NEW brand literal makes it fail — which is
 * exactly the regression it exists to catch.
 */
class NoHardcodedBrandLiteralTest extends TestCase
{
    private const NEEDLES = [
        'Myra Admin',
        'myra-pdf-',
        '#09090b',
        "config('app.name')",
        'VITE_APP_NAME',
    ];

    private const ROOTS = [
        'app',
        'resources/js',
        'resources/views',
    ];

    private const EXTENSIONS = ['php', 'ts', 'vue', 'js'];

    /** @return array<string,array<int,string>> path prefix => allowed needles */
    private function allowlist(): array
    {
        $path = dirname(__DIR__, 2).'/fixtures/brand/literal-allowlist.json';

        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true)['allow'];
    }

    public function test_no_new_brand_literal_reaches_a_shipped_source_file(): void
    {
        $root = dirname(__DIR__, 3);
        $allow = $this->allowlist();
        $offenders = [];

        foreach (self::ROOTS as $dir) {
            $base = $root.'/'.$dir;

            if (! is_dir($base)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (! $file->isFile() || ! in_array($file->getExtension(), self::EXTENSIONS, true)) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $contents = (string) file_get_contents($file->getPathname());

                foreach (self::NEEDLES as $needle) {
                    if (! str_contains($contents, $needle)) {
                        continue;
                    }

                    if ($this->isAllowed($allow, $relative, $needle)) {
                        continue;
                    }

                    $offenders[] = $relative.' :: '.$needle;
                }
            }
        }

        $this->assertSame([], $offenders, "Hardcoded brand literals:\n".implode("\n", $offenders));
    }

    public function test_the_public_shell_files_carry_no_stock_product_name(): void
    {
        $root = dirname(__DIR__, 3);

        // manifest.webmanifest is a generated precache fallback; brand:publish
        // rewrites it. offline.html is rendered from resources/views/brand.
        foreach (['public/offline.html'] as $relative) {
            $this->assertStringNotContainsString('Myra Admin', (string) file_get_contents($root.'/'.$relative), $relative);
        }
    }

    /** @param array<string,array<int,string>> $allow */
    private function isAllowed(array $allow, string $relative, string $needle): bool
    {
        foreach ($allow as $prefix => $needles) {
            if (str_starts_with($relative, $prefix) && ($needles === ['*'] || in_array($needle, $needles, true))) {
                return true;
            }
        }

        return false;
    }
}
