<?php

namespace App\Http\Controllers\Admin;

use App\Brand\BrandAssetPipeline;
use App\Brand\BrandManager;
use App\Brand\BrandPalette;
use App\Brand\BrandTypography;
use App\Brand\Color;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandRequest;
use App\Jobs\PublishBrandAssets;
use App\Settings\BrandSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(BrandManager $brand): Response
    {
        Gate::authorize('brand.view');

        $settings = app(BrandSettings::class);

        return Inertia::render('Admin/Brand/Index', [
            'brandSettings' => array_merge($settings->toArray(), [
                'logo_url' => $this->url($settings->logo_path),
                'logo_dark_url' => $this->url($settings->logo_dark_path),
                'mark_url' => $this->url($settings->mark_path),
                'favicon_url' => $this->url($settings->favicon_path),
                'og_image_url' => $this->url($settings->og_image_path),
            ]),
            'tokens' => $brand->toInertiaProp(),
            'contrast' => $this->contrast($brand->current()->palette),
            'options' => [
                'presets' => array_map(
                    static fn (string $hex, string $key) => ['key' => $key, 'color' => $hex],
                    array_values(BrandPalette::PRESETS),
                    array_keys(BrandPalette::PRESETS),
                ),
                'fontSans' => BrandTypography::SANS,
                'fontMono' => BrandTypography::MONO,
                'radii' => BrandTypography::RADII,
                'slots' => BrandAssetPipeline::SLOTS,
                'minContrast' => (float) config('myra.brand.min_contrast', 4.5),
            ],
        ]);
    }

    public function update(UpdateBrandRequest $request, BrandManager $brand): RedirectResponse
    {
        Gate::authorize('brand.update');

        $settings = app(BrandSettings::class);
        $data = $request->validated();

        foreach (['enabled', 'dark_default'] as $key) {
            $settings->$key = (bool) ($data[$key] ?? false);
        }

        foreach (['name', 'logo_position', 'font_sans', 'font_mono', 'radius'] as $key) {
            $settings->$key = (string) $data[$key];
        }

        foreach (['tagline', 'description'] as $key) {
            $settings->$key = (string) ($data[$key] ?? '');
        }

        $settings->short_name = $data['short_name'] ?? null;
        $settings->primary = strtolower((string) $data['primary']);
        $settings->preset = (string) ($data['preset'] ?? $settings->preset);

        foreach (['accent', 'sidebar_background', 'sidebar_foreground', 'sidebar_accent'] as $key) {
            $value = $data[$key] ?? null;
            $settings->$key = ($value === null || $value === '') ? null : strtolower((string) $value);
        }

        $settings->version = $settings->version + 1;
        $settings->save();

        $brand->forget();
        PublishBrandAssets::dispatch();

        return back()->with('success', __('brand.saved'));
    }

    public function uploadAsset(Request $request, string $slot, BrandAssetPipeline $pipeline, BrandManager $brand): RedirectResponse
    {
        Gate::authorize('brand.update');

        abort_unless(in_array($slot, BrandAssetPipeline::SLOTS, true), 404);

        $request->validate([$slot => ['required', new \App\Rules\SafeImage($slot)]]);

        $settings = app(BrandSettings::class);
        $property = $this->property($slot);

        $settings->$property = $pipeline->store($request->file($slot), $slot);
        $settings->version = $settings->version + 1;
        $settings->save();

        $brand->forget();
        PublishBrandAssets::dispatch([$slot => $settings->$property]);

        return back()->with('success', __('brand.assets.saved'));
    }

    public function destroyAsset(string $slot, BrandAssetPipeline $pipeline, BrandManager $brand): RedirectResponse
    {
        Gate::authorize('brand.update');

        abort_unless(in_array($slot, BrandAssetPipeline::SLOTS, true), 404);

        $settings = app(BrandSettings::class);
        $property = $this->property($slot);

        $settings->$property = null;
        $settings->version = $settings->version + 1;
        $settings->save();

        $brand->forget();
        $pipeline->purge($slot);

        return back()->with('success', __('brand.assets.removed'));
    }

    /** Real tokens for UNSAVED input. Nothing is persisted. */
    public function preview(Request $request, BrandManager $brand): JsonResponse
    {
        Gate::authorize('brand.update');

        $input = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:120'],
            'primary' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_background' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_foreground' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_accent' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'preset' => ['sometimes', 'string'],
            'font_sans' => ['sometimes', 'string'],
            'font_mono' => ['sometimes', 'string'],
            'radius' => ['sometimes', 'string'],
            'dark_default' => ['sometimes', 'boolean'],
        ]);

        $resolved = $brand->fromInput($input);

        return response()->json([
            'tokens' => $resolved->toArray(),
            'light' => $resolved->cssVariables(false),
            'dark' => $resolved->cssVariables(true),
            'contrast' => $this->contrast($resolved->palette),
        ]);
    }

    public function reset(BrandManager $brand): RedirectResponse
    {
        Gate::authorize('brand.update');

        $settings = app(BrandSettings::class);
        $settings->enabled = false;
        $settings->accent = null;
        $settings->preset = 'zinc';
        $settings->font_sans = 'figtree';
        $settings->font_mono = 'ui-monospace';
        $settings->radius = '0.625rem';
        $settings->dark_default = false;
        $settings->version = $settings->version + 1;
        $settings->save();

        $brand->forget();

        return back()->with('success', __('brand.reset.done'));
    }

    /** @return array<string,array{ratio:float,foreground:string}> */
    private function contrast(BrandPalette $palette): array
    {
        $pairs = [
            'primary' => $palette->primaryHex,
            'sidebar' => $palette->sidebarBackgroundHex ?? $palette->primaryHex,
            'accent' => $palette->accentHex ?? $palette->primaryHex,
        ];

        $out = [];

        foreach ($pairs as $key => $hex) {
            $fg = $key === 'sidebar' && $palette->sidebarForegroundHex !== null
                ? $palette->sidebarForegroundHex
                : $palette->foregroundOn($hex);

            $out[$key] = [
                'ratio' => round(Color::contrastRatio($hex, $fg), 2),
                'foreground' => $fg,
            ];
        }

        return $out;
    }

    private function property(string $slot): string
    {
        return $slot === 'og_image' ? 'og_image_path' : $slot.'_path';
    }

    private function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
