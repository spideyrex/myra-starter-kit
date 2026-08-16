<?php

namespace App\Brand;

use App\Settings\AppearanceSettings;
use App\Settings\BrandSettings;
use App\Settings\GeneralSettings;
use App\Settings\HomepageSettings;
use App\Settings\SeoSettings;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Events\SettingsSaved;

/**
 * The app-path invalidation. A foreign writer is covered by BrandManager's
 * bounded probe instead.
 */
class BrandCacheSubscriber
{
    private const WATCHED = [
        BrandSettings::class,
        GeneralSettings::class,
        AppearanceSettings::class,
        SeoSettings::class,
        HomepageSettings::class,
    ];

    public function handle(SettingsSaved $event): void
    {
        foreach (self::WATCHED as $class) {
            if ($event->settings instanceof $class) {
                $this->bump();

                return;
            }
        }
    }

    private function bump(): void
    {
        try {
            DB::table('settings')
                ->where('group', 'brand')
                ->where('name', 'version')
                ->update(['payload' => json_encode((int) now()->getTimestamp()), 'updated_at' => now()]);
        } catch (\Throwable) {
            // A bump is an optimisation; forgetting the caches is the guarantee.
        }

        app(BrandManager::class)->forget();
    }
}
