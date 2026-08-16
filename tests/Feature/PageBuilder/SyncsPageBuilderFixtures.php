<?php

namespace Tests\Feature\PageBuilder;

/**
 * Bundle D's LOCAL fixture bridge — the tree keeps one copy per bundle on
 * purpose (see tests/Feature/Homepage/SyncsHomepageFixtures.php).
 *
 * There is exactly ONE mode, and it asserts. Under MYRA_WRITE_FIXTURES=1 the
 * file is rewritten from the payload the server just produced; otherwise the
 * committed file is checked against that payload. tests/js therefore cannot
 * mount a literal the server would never emit: a drifting payload fails here,
 * in PHP, before the vitest job on the other runner ever reads the file.
 *
 * The check is a PROJECTION rather than a byte snapshot. Every path present in
 * the committed file must carry exactly the value the server shipped; keys the
 * server adds on top are ignored. A section type may declare a new field, and
 * HomepageSettings a new property, without invalidating the committed sample —
 * but no committed value may disagree with the server, and no committed key may
 * vanish from it.
 */
trait SyncsPageBuilderFixtures
{
    protected function syncFixture(string $relativePath, mixed $payload): void
    {
        $path = base_path($relativePath);
        $actual = $this->normaliseFixture($payload);

        if (getenv('MYRA_WRITE_FIXTURES') === '1' || env('MYRA_WRITE_FIXTURES') === '1') {
            $this->writeFixture($path, $this->encodeFixture($actual));

            return;
        }

        $this->assertFileExists($path, "Missing fixture {$relativePath}. Regenerate with MYRA_WRITE_FIXTURES=1.");

        $expected = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($expected, "Fixture {$relativePath} is not valid JSON.");

        // Round-trip through JSON so stdClass props (templateOptions) and array
        // props compare on the same footing.
        $shipped = json_decode((string) json_encode($actual), true);

        $this->assertSame(
            $expected,
            $this->projectOnto($expected, $shipped),
            "Fixture {$relativePath} disagrees with the payload the server produces. "
            .'Re-run with MYRA_WRITE_FIXTURES=1 and review the diff.',
        );
    }

    private function encodeFixture(mixed $payload): string
    {
        return json_encode(
            $payload,
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

    /**
     * Reduce the server payload to the shape the committed fixture declares, so
     * the comparison reports drift instead of additive growth.
     */
    private function projectOnto(mixed $expected, mixed $actual): mixed
    {
        if (! is_array($expected) || ! is_array($actual)) {
            return $actual;
        }

        if (array_is_list($expected)) {
            if (! array_is_list($actual) || count($actual) !== count($expected)) {
                return $actual;
            }

            return array_map(fn ($e, $a) => $this->projectOnto($e, $a), $expected, $actual);
        }

        $out = [];

        foreach ($expected as $key => $value) {
            $out[$key] = array_key_exists($key, $actual)
                ? $this->projectOnto($value, $actual[$key])
                : '__ABSENT_FROM_THE_SERVER_PAYLOAD__';
        }

        return $out;
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
