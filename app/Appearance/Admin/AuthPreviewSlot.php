<?php

namespace App\Appearance\Admin;

use App\Brand\BrandManager;
use App\Brand\BrandPalette;
use App\Brand\Color;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves UNSAVED editor input into the payload the guest shell consumes, so
 * the preview and the saved page cannot diverge. Nothing is persisted.
 *
 * When the engine bundle is present every value comes from
 * AppearanceManager::fromInput(); the local resolution below is the seam that
 * keeps this editor mountable before the engine merges.
 */
final class AuthPreviewSlot
{
    /** Recipe key => CSS background-image value. Expressible without # or : by design. */
    public const RECIPE_CSS = [
        'brand-fade' => 'linear-gradient(180deg,var(--primary) 0%,transparent 70%)',
        'brand-mesh' => 'radial-gradient(at 20% 20%,var(--primary) 0%,transparent 50%),radial-gradient(at 80% 60%,var(--accent) 0%,transparent 50%)',
        'dusk' => 'linear-gradient(160deg,var(--primary) 0%,oklch(0.25 0.05 280) 100%)',
        'dawn' => 'linear-gradient(160deg,var(--accent) 0%,oklch(0.92 0.04 60) 100%)',
        'ink' => 'linear-gradient(180deg,oklch(0.18 0.01 260) 0%,oklch(0.10 0.01 260) 100%)',
        'aurora' => 'linear-gradient(135deg,var(--primary) 0%,var(--accent) 50%,transparent 100%)',
        'dots' => 'radial-gradient(currentColor 1px,transparent 1px)',
        'grid' => 'linear-gradient(currentColor 1px,transparent 1px),linear-gradient(90deg,currentColor 1px,transparent 1px)',
        'diagonal' => 'repeating-linear-gradient(45deg,currentColor 0 1px,transparent 1px 12px)',
        'noise' => 'radial-gradient(currentColor 0.5px,transparent 0.5px)',
    ];

    public const RECIPE_SIZE = [
        'dots' => '16px 16px',
        'noise' => '16px 16px',
        'grid' => '24px 24px',
    ];

    private const SCRIM_OPACITY = ['none' => 0.0, 'light' => 0.35, 'medium' => 0.55, 'strong' => 0.70];

    /** The four shells, mirrored locally so the picker renders without the engine. */
    private const LAYOUT_SCHEMA = [
        'split' => ['component' => 'SplitLayout', 'flippable' => true, 'supportsMedia' => true],
        'centered' => ['component' => 'CenteredLayout', 'flippable' => false, 'supportsMedia' => false],
        'cover' => ['component' => 'CoverLayout', 'flippable' => false, 'supportsMedia' => true],
        'card' => ['component' => 'CardLayout', 'flippable' => true, 'supportsMedia' => true],
    ];

    /** @return array<int,array<string,mixed>> */
    public static function layouts(): array
    {
        if (class_exists(\App\Appearance\AuthLayoutRegistry::class)) {
            try {
                $schema = \App\Appearance\AuthLayoutRegistry::toClientSchema();

                if ($schema !== []) {
                    return $schema;
                }
            } catch (\Throwable) {
                // fall through to the local list
            }
        }

        $out = [];

        foreach (self::LAYOUT_SCHEMA as $key => $meta) {
            $out[] = [
                'key' => $key,
                'component' => $meta['component'],
                'titleKey' => 'appearanceAdmin.layouts.'.$key.'.title',
                'descriptionKey' => 'appearanceAdmin.layouts.'.$key.'.description',
                'thumbnail' => null,
                'flippable' => $meta['flippable'],
                'supportsMedia' => $meta['supportsMedia'],
                'since' => '2.8.0',
            ];
        }

        return $out;
    }

    /**
     * The AuthAppearancePayload for one set of raw values, plus the advisory
     * contrast fields the editor renders.
     */
    public static function resolve(array $raw): array
    {
        $meta = self::layoutMeta(is_string($raw['auth_layout'] ?? null) ? $raw['auth_layout'] : 'split');
        $surface = self::surface($raw, 'auth');

        return [
            'layout' => $meta['key'],
            'component' => $meta['component'],
            'flip' => (bool) ($raw['auth_flip'] ?? false) && $meta['flippable'],
            'show_tagline' => (bool) ($raw['auth_show_tagline'] ?? true),
            'supports_media' => $meta['supportsMedia'],
            'surface' => $surface,
            'contrast' => $surface['contrast'],
            'warning' => $surface['contrast'] < (float) config('myra.brand.min_contrast', 4.5),
        ];
    }

    /** @return array<int,string> the layout keys the form may accept */
    public static function layoutIds(): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $row) => $row['key'] ?? null,
            self::layouts(),
        ), 'is_string'));

        return $ids !== [] ? $ids : AppearanceWriter::LAYOUTS;
    }

    /** Never null: an unknown key always lands on split. */
    private static function layoutMeta(string $key): array
    {
        $rows = self::layouts();
        $fallback = null;

        foreach ($rows as $row) {
            if (($row['key'] ?? null) === $key) {
                return $row;
            }

            if (($row['key'] ?? null) === 'split') {
                $fallback = $row;
            }
        }

        return $fallback ?? $rows[0] ?? [
            'key' => 'split',
            'component' => 'SplitLayout',
            'flippable' => true,
            'supportsMedia' => true,
        ];
    }

    /** The SurfacePayload for one surface. Delegates to the engine when it exists. */
    public static function surface(array $raw, string $surface): array
    {
        if (class_exists(\App\Appearance\AppearanceManager::class)) {
            try {
                return app(\App\Appearance\AppearanceManager::class)->fromInput($raw, $surface)->toArray();
            } catch (\Throwable) {
                // fall through to the local resolution
            }
        }

        return self::localSurface($raw, $surface);
    }

    private static function localSurface(array $raw, string $surface): array
    {
        $palette = self::palette();

        $type = $raw[$surface.'_bg_type'] ?? null;
        $type = is_string($type) && in_array($type, AppearanceWriter::TYPES, true)
            ? $type
            : ($surface === 'auth' ? 'brand' : 'none');

        $recipe = $raw[$surface.'_bg_recipe'] ?? null;
        $recipe = is_string($recipe) && isset(self::RECIPE_CSS[$recipe]) ? $recipe : null;

        if ($recipe !== null) {
            $family = in_array($recipe, AppearanceWriter::GRADIENTS, true) ? 'gradient' : 'pattern';
            if ($type !== $family) {
                $recipe = null;
            }
        }

        $color = $raw[$surface.'_bg_color'] ?? null;
        $color = is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : null;

        $imagePath = $type === 'image' ? self::coerceImage($raw[$surface.'_bg_image_path'] ?? null) : null;
        $imageUrl = $imagePath !== null && self::imageExists($imagePath)
            ? Storage::disk('public')->url($imagePath)
            : null;

        $scrim = $raw[$surface.'_bg_scrim'] ?? null;
        $scrim = is_string($scrim) && isset(self::SCRIM_OPACITY[$scrim]) ? $scrim : 'medium';

        // The floor lives in normalisation: an image-backed surface is never unscrimmed.
        if ($type === 'image' && $scrim === 'none') {
            $scrim = 'light';
        }

        $base = match ($type) {
            'none' => $palette->hex('background'),
            'solid', 'gradient', 'pattern', 'image' => $color ?? $palette->hex('primary'),
            default => $palette->hex('primary'),
        };

        $foreground = $palette->foregroundOn($base);

        return [
            'type' => $type,
            'recipe' => $recipe,
            'scrim' => $scrim,
            'image_url' => $imageUrl,
            'base' => Color::hexToOklch($base),
            'foreground' => Color::hexToOklch($foreground),
            'contrast' => $palette->contrastOn($base),
            // OBJECT, matching Background::toArray(). The preview payload and
            // the saved payload describe the same surface, so they must have
            // the same shape — and `{}` has to survive json_encode either way.
            'css_vars' => (object) self::vars($surface, $type, $recipe, $base, $foreground, $scrim),
        ];
    }

    /** @return array<string,string> */
    private static function vars(string $prefix, string $type, ?string $recipe, string $base, string $foreground, string $scrim): array
    {
        if ($type === 'brand' || $type === 'none') {
            return [];
        }

        $vars = [
            '--myra-'.$prefix.'-bg' => Color::hexToOklch($base),
            '--myra-'.$prefix.'-fg' => Color::hexToOklch($foreground),
        ];

        if ($recipe !== null) {
            $vars['--myra-'.$prefix.'-image'] = self::RECIPE_CSS[$recipe];

            if ($type === 'pattern') {
                $vars['--myra-'.$prefix.'-image-size'] = self::RECIPE_SIZE[$recipe] ?? 'auto';
            }
        }

        $vars['--myra-'.$prefix.'-scrim'] = (string) (self::SCRIM_OPACITY[$scrim] ?? 0.55);

        return array_map(self::safe(...), $vars);
    }

    /** The BrandManager allowlist, verbatim. Strips # and : — no hex, no url() ever reaches CSS. */
    private static function safe(string $value): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9 ,.%()\/\'\-_]/', '', $value);
    }

    private static function coerceImage(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path) === 1) {
            return null;
        }

        return $path;
    }

    private static function imageExists(string $path): bool
    {
        try {
            return Storage::disk('public')->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function palette(): BrandPalette
    {
        try {
            return app(BrandManager::class)->current()->palette;
        } catch (\Throwable) {
            return BrandPalette::fromPreset('zinc');
        }
    }
}
