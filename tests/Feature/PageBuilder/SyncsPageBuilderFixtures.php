<?php

namespace Tests\Feature\PageBuilder;

/**
 * Bundle D's LOCAL fixture bridge — the tree keeps one copy per bundle on
 * purpose (see tests/Feature/Homepage/SyncsHomepageFixtures.php).
 *
 * `syncFixture()` behaves exactly like the others: it asserts, and only writes
 * under MYRA_WRITE_FIXTURES=1.
 *
 * `publishFixture()` ALWAYS writes. It exists for one file,
 * tests/js/fixtures/page-builder-blocks.json, which is not a drift guard but a
 * hand-off: the PHP end-to-end test saves a block list through the real admin
 * endpoint and the vitest spec mounts the resulting payload, so the JS side is
 * proven against what the server emits rather than a hand-authored literal.
 * The guarantee lives in the PHP assertions in the same test; the file is the
 * artefact of them, and regenerating it must never be a separate manual step.
 */
trait SyncsPageBuilderFixtures
{
    protected function syncFixture(string $relativePath, mixed $payload): void
    {
        $path = base_path($relativePath);
        $actual = $this->encodeFixture($payload);

        if (getenv('MYRA_WRITE_FIXTURES') === '1' || env('MYRA_WRITE_FIXTURES') === '1') {
            $this->writeFixture($path, $actual);

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

    protected function publishFixture(string $relativePath, mixed $payload): void
    {
        $this->writeFixture(base_path($relativePath), $this->encodeFixture($payload));
    }

    private function encodeFixture(mixed $payload): string
    {
        return json_encode(
            $this->normaliseFixture($payload),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";
    }

    private function writeFixture(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);
        $this->addToAssertionCount(1);
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
