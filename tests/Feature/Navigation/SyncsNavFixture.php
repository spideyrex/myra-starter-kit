<?php

namespace Tests\Feature\Navigation;

/**
 * Bundle B's own fixture sync — deliberately not shared with the other bundles.
 *
 * With MYRA_WRITE_FIXTURES=1 it writes the JSON the server just produced;
 * otherwise it asserts the committed file still equals that payload. The vitest
 * specs import the same file, so a server-side shape change breaks the client
 * test instead of silently drifting past it.
 */
trait SyncsNavFixture
{
    protected function syncFixture(string $relativePath, mixed $payload): void
    {
        $path = base_path($relativePath);
        $json = $this->encodeFixture($payload);

        if ($this->writingFixtures()) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, $json."\n");
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertFileExists(
            $path,
            "Fixture {$relativePath} is missing. Re-run with MYRA_WRITE_FIXTURES=1 to write it.",
        );

        $this->assertSame(
            trim((string) file_get_contents($path)),
            trim($json),
            "Fixture {$relativePath} is stale. Re-run with MYRA_WRITE_FIXTURES=1 to refresh it.",
        );
    }

    private function writingFixtures(): bool
    {
        return getenv('MYRA_WRITE_FIXTURES') === '1' || env('MYRA_WRITE_FIXTURES') === '1';
    }

    private function encodeFixture(mixed $payload): string
    {
        return json_encode(
            $this->sortFixtureKeys($payload),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function sortFixtureKeys(mixed $value): mixed
    {
        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $this->sortFixtureKeys($item);
        }

        if (! array_is_list($out)) {
            ksort($out);
        }

        return $out;
    }
}
