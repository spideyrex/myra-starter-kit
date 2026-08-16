<?php

namespace Tests\Feature\Examples;

use App\Admin\Examples\ExampleRegistry;
use Tests\TestCase;

/**
 * Recurring mistake #5: fake() comes from fakerphp/faker, a require-dev
 * package, and production deploys with --no-dev. DemoController still calls it;
 * bundle B must not repeat that, in PHP or in a vendored/authored example.
 *
 * This is a CONTENT scan, not a file-hash guard — it stays valid when B's own
 * source is edited, because what it asserts is a property of the content rather
 * than a snapshot of it.
 */
class AuthoredExampleDataTest extends TestCase
{
    /** @return string[] */
    private function files(string $relativeDir, string $extensionPattern): array
    {
        $root = base_path($relativeDir);

        if (! is_dir($root)) {
            return [];
        }

        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if ($file->isFile() && preg_match($extensionPattern, $file->getFilename())) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    public function test_no_example_file_calls_fake(): void
    {
        $files = $this->files('resources/js/examples', '/\.(vue|ts|js|json|txt)$/');

        $this->assertNotEmpty($files, 'Expected vendored example files on disk.');

        foreach ($files as $file) {
            $this->assertStringNotContainsString(
                'fake(',
                (string) file_get_contents($file),
                basename($file).' calls fake(); example data must be static JSON.',
            );
        }
    }

    public function test_no_php_file_in_this_bundle_calls_fake(): void
    {
        $php = array_merge(
            $this->files('app/Admin/Examples', '/\.php$/'),
            [base_path('app/Http/Controllers/Admin/ExampleController.php')],
        );

        foreach ($php as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w$>])fake\s*\(/',
                (string) file_get_contents($file),
                basename($file).' calls fake(), a require-dev helper that is absent in production.',
            );
        }
    }

    public function test_every_authored_example_ships_static_data_rather_than_a_live_query(): void
    {
        foreach (ExampleRegistry::manifest() as $key => $row) {
            if (($row['origin'] ?? '') !== 'authored') {
                continue;
            }

            foreach ($row['files'] as $file) {
                $path = base_path(ExampleRegistry::ROOT."/{$key}/{$file['path']}");

                $this->assertStringNotContainsString(
                    '@inertiajs/vue3',
                    (string) file_get_contents($path),
                    "{$key}/{$file['path']} reaches for Inertia; an example must render from its own static data.",
                );
            }
        }
    }
}
