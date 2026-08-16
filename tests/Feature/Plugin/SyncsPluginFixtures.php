<?php

namespace Tests\Feature\Plugin;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Bundle A's LOCAL fixture helper (each bundle defines its own on purpose).
 *
 * With MYRA_WRITE_FIXTURES=1 it writes the payload the server actually
 * produced; otherwise it asserts the committed file still matches it. The
 * committed JSON is what the vitest specs mount against, so a drifting server
 * payload fails here before it can silently invalidate a client test.
 */
trait SyncsPluginFixtures
{
    protected function syncFixture(string $relativePath, mixed $payload): void
    {
        $path = base_path($relativePath);
        $fresh = $this->normaliseFixture($payload);

        if (getenv('MYRA_WRITE_FIXTURES') === '1' || env('MYRA_WRITE_FIXTURES') === '1') {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents(
                $path,
                json_encode($fresh, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
            );

            $this->addToAssertionCount(1);

            return;
        }

        $this->assertFileExists(
            $path,
            "Missing fixture {$relativePath}. Regenerate with MYRA_WRITE_FIXTURES=1.",
        );

        $committed = json_decode(file_get_contents($path), true);

        $this->assertSame(
            $fresh,
            $this->normaliseFixture($committed),
            "Fixture {$relativePath} no longer matches the payload the server produces. "
            ."Regenerate it with MYRA_WRITE_FIXTURES=1 and update the vitest spec that reads it.",
        );
    }

    /** Recursively arrayify and sort associative keys so comparison is stable. */
    private function normaliseFixture(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

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
