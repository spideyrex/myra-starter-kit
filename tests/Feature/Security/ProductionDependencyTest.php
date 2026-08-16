<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * The suite runs with dev dependencies installed, so a runtime call into a
 * dev-only package passes every local test and fatals in production, which
 * deploys with `composer install --no-dev`.
 *
 * That is exactly how /admin/demo/import-export and /admin/demo/saved-views
 * 500'd in production from v2.2.0 to v2.5.1: app code called fake() while
 * fakerphp/faker sat in require-dev.
 */
class ProductionDependencyTest extends TestCase
{
    /** Helpers that runtime code may only use if their package ships in `require`. */
    private const RUNTIME_HELPERS = [
        'fake(' => 'fakerphp/faker',
    ];

    public function test_runtime_code_never_calls_into_a_dev_only_package(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $prod = array_keys($composer['require'] ?? []);
        $dev = array_keys($composer['require-dev'] ?? []);

        foreach (self::RUNTIME_HELPERS as $needle => $package) {
            $callers = $this->appFilesContaining($needle);

            if ($callers === []) {
                continue;
            }

            $this->assertContains($package, $prod, sprintf(
                '%s is called from runtime code (%s) but [%s] is not in composer "require". '
                . 'Production deploys with --no-dev, so this is a fatal there and green here.',
                rtrim($needle, '('),
                implode(', ', array_slice($callers, 0, 3)),
                $package,
            ));

            $this->assertNotContains($package, $dev,
                "[{$package}] must not be in require-dev as well as require.");
        }
    }

    /** @return string[] repo-relative paths under app/ containing the needle */
    private function appFilesContaining(string $needle): array
    {
        $hits = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), $needle)) {
                $hits[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        return $hits;
    }
}
