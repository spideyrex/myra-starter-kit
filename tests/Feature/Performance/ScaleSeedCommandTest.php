<?php

namespace Tests\Feature\Performance;

use App\Models\MyraScaleRow;
use Tests\TestCase;

class ScaleSeedCommandTest extends TestCase
{
    public function test_it_seeds_the_requested_number_of_rows(): void
    {
        $this->artisan('myra:scale-seed', ['count' => 25])->assertExitCode(0);

        $this->assertSame(25, MyraScaleRow::query()->count());
        $this->assertSame(
            ['active', 'archived', 'pending', 'suspended'],
            MyraScaleRow::query()->distinct()->orderBy('status')->pluck('status')->all(),
        );
    }

    public function test_fresh_replaces_rather_than_appends(): void
    {
        $this->artisan('myra:scale-seed', ['count' => 10]);
        $this->artisan('myra:scale-seed', ['count' => 10, '--fresh' => true]);

        $this->assertSame(10, MyraScaleRow::query()->count());
    }

    public function test_it_never_runs_itself(): void
    {
        $this->assertSame(0, MyraScaleRow::query()->count());
    }
}
