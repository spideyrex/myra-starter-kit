<?php

namespace Tests\Feature\Report;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The version short-circuit, driven end to end by the REAL endpoint payload.
 *
 * The committed fixture is the actual server response with ONE substitution:
 * `version` is a hash of the dataset stamp, which is the only value that can
 * legitimately differ between environments. Every other byte is verbatim
 * server output, so a wire-shape change breaks this test.
 */
class WidgetBatchVersionTest extends TestCase
{
    private const SLOT = 'signups-by-status';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-16 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function spec(string $report = 'users'): array
    {
        return [
            'report' => $report,
            'dimension' => 'status',
            'measures' => ['signups'],
            'compare' => 'none',
            'period' => ['preset' => 'custom', 'from' => '2026-01-01', 'to' => '2026-12-31'],
        ];
    }

    private function seedUsers(): void
    {
        User::factory()->count(2)->create([
            'status' => 'active',
            'created_at' => '2026-03-01 09:00:00',
            'updated_at' => '2026-03-01 09:00:00',
        ]);

        User::factory()->create([
            'status' => 'inactive',
            'created_at' => '2026-04-01 09:00:00',
            'updated_at' => '2026-04-01 09:00:00',
        ]);
    }

    public function test_an_echoed_version_short_circuits_and_runs_no_aggregation(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedUsers();

        $first = $this->postJson(route('admin.reports.widgets'), [
            'widgets' => [self::SLOT => $this->spec()],
        ])->assertOk();

        $version = $first->json('results.' . self::SLOT . '.version');

        $this->assertIsString($version);
        $this->assertNotSame('', $version);

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $second = $this->postJson(route('admin.reports.widgets'), [
            'widgets' => [self::SLOT => $this->spec()],
            'versions' => [self::SLOT => $version],
        ])->assertOk();

        $this->assertSame(
            ['unchanged' => true, 'version' => $version],
            $second->json('results.' . self::SLOT),
        );

        $grouped = array_values(array_filter(
            $statements,
            static fn (string $sql) => str_contains($sql, 'group by'),
        ));

        $this->assertSame([], $grouped, 'The short-circuited slot still ran an aggregation.');
    }

    public function test_a_stale_version_re_runs_the_report(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedUsers();

        $response = $this->postJson(route('admin.reports.widgets'), [
            'widgets' => [self::SLOT => $this->spec()],
            'versions' => [self::SLOT => 'not-the-current-stamp'],
        ])->assertOk();

        $this->assertNull($response->json('results.' . self::SLOT . '.unchanged'));
        $this->assertIsArray($response->json('results.' . self::SLOT . '.rows'));
    }

    public function test_a_report_without_a_version_key_never_short_circuits(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedUsers();

        $first = $this->postJson(route('admin.reports.widgets'), [
            'widgets' => ['events' => [
                'report' => 'activity',
                'dimension' => 'description',
                'period' => ['preset' => 'custom', 'from' => '2026-01-01', 'to' => '2026-12-31'],
            ]],
        ])->assertOk();

        // ActivityReport declares no version column, so the stamp is empty and
        // an echoed value can never match.
        $this->assertSame('', $first->json('results.events.version'));

        $second = $this->postJson(route('admin.reports.widgets'), [
            'widgets' => ['events' => [
                'report' => 'activity',
                'dimension' => 'description',
                'period' => ['preset' => 'custom', 'from' => '2026-01-01', 'to' => '2026-12-31'],
            ]],
            'versions' => ['events' => ''],
        ])->assertOk();

        $this->assertNull($second->json('results.events.unchanged'));
    }

    public function test_a_write_changes_the_version(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedUsers();

        $before = $this->postJson(route('admin.reports.widgets'), [
            'widgets' => [self::SLOT => $this->spec()],
        ])->json('results.' . self::SLOT . '.version');

        User::factory()->create([
            'status' => 'active',
            'created_at' => '2026-05-01 09:00:00',
            'updated_at' => '2026-05-01 09:00:00',
        ]);

        $after = $this->postJson(route('admin.reports.widgets'), [
            'widgets' => [self::SLOT => $this->spec()],
        ])->json('results.' . self::SLOT . '.version');

        $this->assertNotSame($before, $after);
    }

    /**
     * §0.6 fixture bridge — the vitest suite mocks the batch response with this
     * file, so the client is exercised against the shape the server actually
     * emits, never a hand-invented one.
     *
     * The gate is the recursive KEY-AND-TYPE structure rather than the bytes:
     * this payload carries a dataset hash, absolute dates and an absolute drill
     * URL, so byte equality would fail on a different clock, host or driver
     * while telling us nothing about the wire shape. `MYRA_STRICT_FIXTURES=1`
     * additionally demands byte equality for a same-environment check, and
     * `MYRA_WRITE_FIXTURES=1` rewrites the file.
     */
    public function test_the_batch_payload_matches_the_committed_fixture(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedUsers();

        $payload = $this->postJson(route('admin.reports.widgets'), [
            'widgets' => [self::SLOT => $this->spec()],
        ])->assertOk()->json();

        $payload['results'][self::SLOT]['version'] = '<version>';

        $path = base_path('tests/js/fixtures/widget-batch.json');
        $actual = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if (getenv('MYRA_WRITE_FIXTURES')) {
            file_put_contents($path, $actual);
        }

        $this->assertFileExists($path);

        $committed = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($committed, 'The committed fixture is not valid JSON.');

        $this->assertSame(
            self::shapeOf($committed),
            self::shapeOf($payload),
            'Fixture drift: the widget batch wire shape changed. Re-run with MYRA_WRITE_FIXTURES=1 and review the diff.',
        );

        if (getenv('MYRA_STRICT_FIXTURES')) {
            $this->assertSame(file_get_contents($path), $actual);
        }
    }

    /** Recursive key path => scalar type. Order-insensitive, value-insensitive. */
    private static function shapeOf(mixed $value, string $prefix = ''): array
    {
        if (! is_array($value)) {
            return [$prefix => get_debug_type($value)];
        }

        // A list's shape is its FIRST element's shape: row count is data, not shape.
        if (array_is_list($value)) {
            return $value === []
                ? [$prefix . '[]' => 'empty']
                : self::shapeOf($value[0], $prefix . '[]');
        }

        $out = [];

        foreach ($value as $key => $child) {
            $out += self::shapeOf($child, $prefix === '' ? (string) $key : $prefix . '.' . $key);
        }

        ksort($out);

        return $out;
    }
}
