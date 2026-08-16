<?php

namespace Tests\Feature\Performance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Bundle D's LOCAL fixture helpers. Deliberately not shared with the other
 * bundles — each owns its own copy so the fixture contract cannot drift.
 */
trait ScaleFixtures
{
    private const STATUSES = ['active', 'pending', 'suspended', 'archived'];

    /** Deterministic rows: fixed names, fixed timestamps, no faker, no now(). */
    protected function seedScaleRows(int $count): void
    {
        $base = Carbon::parse('2026-01-01 00:00:00');
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $at = $base->copy()->addMinutes($i)->toDateTimeString();
            $rows[] = [
                'name' => 'Scale Row ' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'email' => 'scale' . $i . '@example.test',
                // Deliberately non-unique: the cursor tiebreak has to carry this.
                'status' => self::STATUSES[$i % 4],
                'amount' => ($i * 37) % 5000,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('myra_scale_rows')->insert($chunk);
        }
    }

    /** A committed fixture must not carry the developer's own APP_URL. */
    protected function pinRootUrl(): void
    {
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        URL::forceScheme('http');
    }

    /**
     * Compares (or, with MYRA_WRITE_FIXTURES=1, writes) the committed JSON the
     * vitest specs mount. The payload is always one the server just produced.
     */
    protected function syncFixture(string $relativePath, mixed $payload): void
    {
        $path = base_path($relativePath);
        $json = json_encode(
            self::sortKeysDeep(json_decode(json_encode($payload), true)),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n";

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        if (env('MYRA_WRITE_FIXTURES')) {
            file_put_contents($path, $json);
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertFileExists($path, "Missing fixture [{$relativePath}]. Regenerate with MYRA_WRITE_FIXTURES=1.");
        $this->assertSame(
            $json,
            file_get_contents($path),
            "Fixture [{$relativePath}] no longer matches the payload the server produces. "
            . 'Regenerate with MYRA_WRITE_FIXTURES=1 and re-run the vitest specs.',
        );
    }

    private static function sortKeysDeep(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = array_map(static fn ($v) => self::sortKeysDeep($v), $value);

        if (! array_is_list($out)) {
            ksort($out);
        }

        return $out;
    }
}
