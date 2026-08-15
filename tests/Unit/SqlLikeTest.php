<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Sql;
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

    public function test_an_escaped_wildcard_matches_a_literal_percent(): void
    {
        User::factory()->create(['name' => '100% cotton']);
        User::factory()->create(['name' => 'anything at all']);

        $matches = User::where('name', 'like', Sql::like('100%'))->pluck('name');

        $this->assertSame(['100% cotton'], $matches->all());
    }

    public function test_a_bare_percent_no_longer_matches_every_row(): void
    {
        User::factory()->count(3)->create();

        $this->assertSame(0, User::where('name', 'like', Sql::like('%'))->count());
    }
}
