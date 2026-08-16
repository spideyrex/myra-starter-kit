<?php

namespace App\Admin\Testing;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * Test-side entry point for the Myra probes. It ships in app/ rather than tests/
 * so plugin and package authors can use it too.
 *
 * @mixin \Illuminate\Foundation\Testing\TestCase
 */
trait InteractsWithMyra
{
    protected function myraTable(TestResponse $r, string $prop): TableProbe
    {
        return TableProbe::make($this, $r, $prop);
    }

    protected function myraSchema(TestResponse $r, string $prop = 'fields'): SchemaProbe
    {
        return SchemaProbe::make($this, $r, $prop);
    }

    protected function myraAction(string $routeName, array $params = []): ActionProbe
    {
        return ActionProbe::make($this, $routeName, $params);
    }

    protected function actingAsSuperAdmin(array $attributes = []): User
    {
        return MyraActors::superAdmin($this, $attributes);
    }

    /** @param string[] $abilities */
    protected function actingAsWithPermissions(array $abilities, array $attributes = []): User
    {
        return MyraActors::withPermissions($this, $abilities, $attributes);
    }

    protected function assertFlashed(string $level, ?string $message = null): void
    {
        Assert::assertTrue(session()->has($level), "No [{$level}] flash message was set.");

        if ($message !== null) {
            Assert::assertSame($message, session($level), "Flash [{$level}].");
        }
    }
}
