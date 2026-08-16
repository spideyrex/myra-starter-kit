<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Sql;
use InvalidArgumentException;
use Tests\TestCase;

class SqlLikeTest extends TestCase
{
    public function test_it_escapes_wildcards_in_every_mode(): void
    {
        $this->assertSame('%100\%\_off%', Sql::like('100%_off'));
        $this->assertSame('100\%\_off%', Sql::like('100%_off', 'starts'));
        $this->assertSame('%100\%\_off', Sql::like('100%_off', 'ends'));
        $this->assertSame('100\%\_off', Sql::like('100%_off', 'exact'));
    }

    public function test_it_escapes_the_escape_character_first(): void
    {
        $this->assertSame('%a\\\\b%', Sql::like('a\\b'));
    }

    public function test_it_emits_an_explicit_escape_clause(): void
    {
        $sql = Sql::whereLike(User::query(), 'name', '100%')->toSql();

        $this->assertStringContainsString('escape', strtolower($sql));
    }

    /**
     * The MySQL branch doubles the backslash; every other driver takes it as
     * written. Only the non-MySQL branch is executable here (the suite runs on
     * sqlite) — the NO_BACKSLASH_ESCAPES case needs a real MySQL connection.
     */
    public function test_a_non_mysql_driver_emits_a_single_character_escape(): void
    {
        Sql::flushDriverCache();

        $sql = Sql::whereLike(User::query(), 'name', '100%')->toSql();

        $this->assertSame('sqlite', User::query()->getConnection()->getDriverName());
        $this->assertStringContainsString("escape '\\'", $sql);
        $this->assertStringNotContainsString("escape '\\\\'", $sql);
    }

    /**
     * Sql::like() stays public because the escaping tests above assert its exact
     * output, but a bare pattern with no ESCAPE clause is the original v2.2.1
     * defect. Nothing in app/ may call it directly — whereLike()/orWhereLike()
     * are the only sanctioned seams. This guard fails CI if that regresses.
     */
    public function test_no_application_code_calls_the_bare_pattern_helper(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // Sql.php itself legitimately calls like() from whereLike().
            if (str_ends_with(str_replace('\\', '/', $file->getPathname()), 'app/Support/Sql.php')) {
                continue;
            }

            if (str_contains($source, 'Sql::like(')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders,
            'Sql::like() produces a pattern with no ESCAPE clause. Use Sql::whereLike()/orWhereLike().');
    }

    public function test_flushing_the_driver_cache_is_idempotent(): void
    {
        Sql::flushDriverCache();
        Sql::flushDriverCache();

        $this->assertStringContainsString('escape', strtolower(
            Sql::whereLike(User::query(), 'name', 'x')->toSql()
        ));
    }

    public function test_an_escaped_wildcard_matches_a_literal_percent(): void
    {
        User::factory()->create(['name' => '100% cotton']);
        User::factory()->create(['name' => 'anything at all']);

        $matches = Sql::whereLike(User::query(), 'name', '100%')->pluck('name');

        $this->assertSame(['100% cotton'], $matches->all());
    }

    public function test_an_escaped_underscore_does_not_match_an_arbitrary_character(): void
    {
        User::factory()->create(['name' => 'a_b']);
        User::factory()->create(['name' => 'axb']);

        $matches = Sql::whereLike(User::query(), 'name', 'a_b')->pluck('name');

        $this->assertSame(['a_b'], $matches->all());
    }

    public function test_a_literal_backslash_in_the_term_still_matches(): void
    {
        User::factory()->create(['name' => 'domain\\user']);
        User::factory()->create(['name' => 'domain user']);

        $matches = Sql::whereLike(User::query(), 'name', 'domain\\user')->pluck('name');

        $this->assertSame(['domain\\user'], $matches->all());
    }

    public function test_a_bare_percent_no_longer_matches_every_row(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(0, Sql::whereLike(User::query(), 'name', '%')->count());
    }

    public function test_modes_anchor_the_pattern(): void
    {
        User::factory()->create(['name' => 'Alpha One']);
        User::factory()->create(['name' => 'One Alpha']);

        $this->assertSame(['Alpha One'], Sql::whereLike(User::query(), 'name', 'Alpha', 'starts')->pluck('name')->all());
        $this->assertSame(['One Alpha'], Sql::whereLike(User::query(), 'name', 'Alpha', 'ends')->pluck('name')->all());
        $this->assertSame(['Alpha One'], Sql::whereLike(User::query(), 'name', 'Alpha One', 'exact')->pluck('name')->all());
    }

    public function test_or_where_like_stays_inside_its_group(): void
    {
        User::factory()->create(['name' => '100% cotton']);
        User::factory()->create(['name' => 'pure wool']);

        $matches = User::query()
            ->where(function ($q) {
                Sql::orWhereLike($q, 'name', '100%');
                Sql::orWhereLike($q, 'name', 'wool');
            })
            ->orderBy('name')
            ->pluck('name');

        $this->assertSame(['100% cotton', 'pure wool'], $matches->all());
    }

    /** The escape literal is driver-dependent, so a public const was actively misleading. */
    public function test_the_dead_escape_constant_is_gone(): void
    {
        $this->assertFalse(defined(Sql::class . '::LIKE_ESCAPE'));
    }

    public function test_it_rejects_a_column_that_is_not_a_plain_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Sql::whereLike(User::query(), 'name) or (1=1', 'x');
    }
}
