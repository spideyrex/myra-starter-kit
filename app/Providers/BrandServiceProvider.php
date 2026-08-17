<?php

namespace App\Providers;

use App\Brand\BrandAssetPipeline;
use App\Brand\BrandCacheSubscriber;
use App\Brand\BrandManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;

class BrandServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BrandManager::class);
        $this->app->singleton(BrandAssetPipeline::class);

        // >>> MYRA v2.8 [A] START — singleton so auth()/page() memoise per request.
        $this->app->singleton(\App\Appearance\AppearanceManager::class);
        // <<< MYRA v2.8 [A] END
    }

    public function boot(): void
    {
        Event::listen(SettingsSaved::class, [BrandCacheSubscriber::class, 'handle']);

        $this->commands([
            \App\Console\Commands\Brand\BrandClearCommand::class,
            \App\Console\Commands\Brand\BrandPublishCommand::class,
            \App\Console\Commands\Brand\BrandFixtureCommand::class,
            // >>> MYRA v2.8 [A] START
            \App\Console\Commands\AppearanceResetCommand::class,
            // <<< MYRA v2.8 [A] END
        ]);
    }
}
