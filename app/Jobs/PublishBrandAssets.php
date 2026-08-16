<?php

namespace App\Jobs;

use App\Brand\BrandAssetPipeline;
use App\Brand\BrandManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Derivative generation. Runs synchronously under QUEUE_CONNECTION=sync. */
class PublishBrandAssets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string,string|null> $slots slot => stored path */
    public function __construct(public readonly array $slots = []) {}

    public function handle(BrandAssetPipeline $pipeline, BrandManager $brand): void
    {
        $raw = $brand->raw()['brand'] ?? [];

        $slots = $this->slots !== [] ? $this->slots : [
            'logo' => $raw['logo_path'] ?? null,
            'favicon' => $raw['favicon_path'] ?? null,
            'mark' => $raw['mark_path'] ?? null,
            'og_image' => $raw['og_image_path'] ?? null,
        ];

        foreach ($slots as $slot => $path) {
            if (in_array($slot, BrandAssetPipeline::SLOTS, true)) {
                $pipeline->derive($slot, $path);
            }
        }
    }
}
