<?php

namespace Tests\Feature\Brand;

use App\Brand\BrandManager;
use App\Settings\BrandSettings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrandCacheInvalidationTest extends TestCase
{
    use SeedsBrand;

    public function test_a_settings_saved_write_invalidates_instantly(): void
    {
        $this->seedBrand();
        $this->assertSame('Acme Corp', app(BrandManager::class)->current()->name);

        $settings = app(BrandSettings::class);
        $settings->name = 'Initech';
        $settings->save();

        $this->assertSame('Initech', $this->freshManager()->current()->name);
    }

    public function test_a_raw_database_write_converges_within_the_probe_window(): void
    {
        config()->set('myra.brand.probe_ttl', 60);
        $this->seedBrand();

        $manager = $this->freshManager();
        $this->assertSame('Acme Corp', $manager->current()->name);

        // A foreign writer: no event, no cache forget.
        DB::table('settings')->where('group', 'brand')->where('name', 'name')
            ->update(['payload' => json_encode('Initech')]);

        // Still cached inside the window.
        $this->assertSame('Acme Corp', $this->freshManager()->current()->name);

        $this->travel(61)->seconds();

        $this->assertSame('Initech', $this->freshManager()->current()->name);
    }

    public function test_probe_ttl_zero_disables_the_probe_without_breaking_the_event_path(): void
    {
        config()->set('myra.brand.probe_ttl', 0);
        $this->seedBrand();

        $this->assertSame('Acme Corp', $this->freshManager()->current()->name);

        DB::table('settings')->where('group', 'brand')->where('name', 'name')
            ->update(['payload' => json_encode('Initech')]);

        // Far past the 60s probe window. With the probe off, staleness is bounded
        // only by cache_ttl, so the foreign write is still invisible here.
        $this->travel(30)->minutes();

        $this->assertSame('Acme Corp', $this->freshManager()->current()->name);

        // The event path still works.
        $settings = app(BrandSettings::class);
        $settings->tagline = 'Changed';
        $settings->save();

        $this->assertSame('Initech', $this->freshManager()->current()->name);
    }

    /**
     * queue:work / Horizon / Octane hold ONE BrandManager for the life of the
     * process and never call forget(). The memo must therefore be keyed on the
     * resolved version, or mail and PDF render a brand frozen at boot forever.
     */
    public function test_one_long_lived_instance_picks_up_a_foreign_write(): void
    {
        config()->set('myra.brand.probe_ttl', 60);
        $this->seedBrand();

        // The SAME instance for the whole test — never forgotten, never replaced.
        $worker = app(BrandManager::class);

        $this->assertSame('Acme Corp', $worker->current()->name);

        DB::table('settings')->where('group', 'brand')->where('name', 'name')
            ->update(['payload' => json_encode('Initech')]);

        $this->travel(61)->seconds();

        $this->assertSame('Initech', $worker->current()->name);
    }

    public function test_the_brand_clear_command_forgets_everything(): void
    {
        $this->seedBrand();
        $this->freshManager()->current();

        DB::table('settings')->where('group', 'brand')->where('name', 'name')
            ->update(['payload' => json_encode('Initech')]);

        $this->artisan('brand:clear')->assertSuccessful();

        $this->assertSame('Initech', $this->freshManager()->current()->name);
    }

    /** A new instance, so the per-request memo never masks a cache miss. */
    private function freshManager(): BrandManager
    {
        app()->forgetInstance(BrandManager::class);

        return app(BrandManager::class);
    }
}
