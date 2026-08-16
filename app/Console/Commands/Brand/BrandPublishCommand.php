<?php

namespace App\Console\Commands\Brand;

use App\Brand\BrandAssetPipeline;
use App\Brand\BrandManager;
use App\Jobs\PublishBrandAssets;
use Illuminate\Console\Command;

class BrandPublishCommand extends Command
{
    protected $signature = 'brand:publish {--prune}';

    protected $description = 'Render brand derivatives and re-publish manifest.webmanifest and offline.html';

    public function handle(BrandManager $brand, BrandAssetPipeline $pipeline): int
    {
        $brand->forget();

        PublishBrandAssets::dispatchSync();

        $manifest = json_encode($brand->manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (is_string($manifest)) {
            @file_put_contents(public_path('manifest.webmanifest'), $manifest."\n");
        }

        $pipeline->publishOffline();

        if ($this->option('prune')) {
            foreach (BrandAssetPipeline::SLOTS as $slot) {
                $pipeline->purge($slot);
            }
        }

        $this->info('Brand assets published ('.$brand->hash().').');

        return self::SUCCESS;
    }
}
