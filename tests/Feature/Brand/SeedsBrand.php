<?php

namespace Tests\Feature\Brand;

use App\Brand\BrandManager;
use Illuminate\Support\Facades\DB;

/** Writes brand rows the way an operator would, then clears the resolver cache. */
trait SeedsBrand
{
    protected function seedBrand(array $overrides = []): void
    {
        $values = array_merge([
            'enabled' => true,
            'name' => 'Acme Corp',
            'short_name' => 'Acme',
            'tagline' => 'Everything, delivered.',
            'description' => 'The Acme control room.',
            'primary' => '#7c3aed',
            'preset' => 'violet',
            'dark_default' => false,
        ], $overrides);

        foreach ($values as $name => $payload) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'brand', 'name' => $name],
                ['payload' => json_encode($payload), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        // The general row still feeds the deprecated siteSettings prop.
        DB::table('settings')->updateOrInsert(
            ['group' => 'general', 'name' => 'site_name'],
            ['payload' => json_encode($values['name']), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
        );

        foreach (['meta_title' => $values['name'], 'meta_description' => $values['description']] as $name => $payload) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'seo', 'name' => $name],
                ['payload' => json_encode($payload), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        app(BrandManager::class)->forget();
    }
}
