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

        // A single-slot dispatch still regenerates the whole coherent set: the
        // icon derivatives are shared between the mark and favicon slots and
        // must never be rendered from a half-stale source.
        $pipeline->deriveAll(array_merge([
            'logo' => $raw['logo_path'] ?? null,
            'mark' => $raw['mark_path'] ?? null,
            'favicon' => $raw['favicon_path'] ?? null,
            'og_image' => $raw['og_image_path'] ?? null,
        ], array_intersect_key($this->slots, array_flip(BrandAssetPipeline::SLOTS))));
    }
}
