<?php

namespace Tests\Feature\Homepage;

/**
 * Bundle D's LOCAL fixture bridge (each bundle keeps its own copy on purpose).
 *
 * With MYRA_WRITE_FIXTURES=1 it writes the payload the server actually
 * produced; otherwise it asserts the committed file still matches. The
 * committed JSON is what tests/js/homepageTemplates.spec.ts mounts the real
 * dispatcher against, so a drifting server payload fails HERE first.
 */
trait SyncsHomepageFixtures
{
    protected function syncFixture(string $relativePath, mixed $payload): void
    {
        $path = base_path($relativePath);
        $actual = json_encode(
            $this->normaliseFixture($payload),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";

        if (getenv('MYRA_WRITE_FIXTURES') === '1' || env('MYRA_WRITE_FIXTURES') === '1') {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, $actual);
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertFileExists($path, "Missing fixture {$relativePath}. Regenerate with MYRA_WRITE_FIXTURES=1.");

        $this->assertSame(
            file_get_contents($path),
            $actual,
            "Fixture {$relativePath} drifted from the payload the server produces. "
            .'Re-run with MYRA_WRITE_FIXTURES=1 and review the diff.',
        );
    }

    /** Recursively sort associative keys so comparison is order-stable. */
    private function normaliseFixture(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $this->normaliseFixture($item);
        }

        if (! array_is_list($out)) {
            ksort($out);
        }

        return $out;
    }
}
