<?php

namespace App\Appearance;

use App\Brand\BrandPalette;
use App\Brand\Color;
use App\Support\UrlGuard;
use Illuminate\Support\Facades\Storage;

/**
 * "What is behind the content" for one surface. TOTAL — it never throws.
 *
 * Every field falls back INDEPENDENTLY, so a half-saved row, a nested array or
 * an int in the settings table degrades to a working surface rather than a 500
 * on the one page that must never break.
 */
final readonly class Background
{
    private function __construct(
        public BackgroundType $type,
        public ?string $colorHex,
        public ?string $recipe,
        public ?string $imagePath,
        public Scrim $scrim,
        public string $prefix = 'auth',
    ) {}

    public static function default(string $surface): self
    {
        return $surface === 'page'
            ? new self(BackgroundType::None, null, null, null, Scrim::Medium, 'page')
            : new self(BackgroundType::Brand, null, null, null, Scrim::Medium, 'auth');
    }

    /**
     * @param  array<string,mixed>  $raw  the whole `appearance` settings group
     */
    public static function fromSettings(array $raw, string $surface): self
    {
        $prefix = $surface === 'page' ? 'page' : 'auth';
        $default = self::default($surface);

        $storedType = $raw[$prefix.'_bg_type'] ?? null;
        $type = (is_string($storedType) ? BackgroundType::tryFrom($storedType) : null) ?? $default->type;

        $storedColor = $raw[$prefix.'_bg_color'] ?? null;
        $color = is_string($storedColor) && Color::isValidHex(Color::normalise($storedColor, 'x'))
            ? Color::normalise($storedColor)
            : null;

        $recipe = $type->usesRecipe()
            ? Recipes::inFamily($raw[$prefix.'_bg_recipe'] ?? null, $type->recipeFamily())
            : null;

        $image = $type->usesImage() ? self::coerceImage($raw[$prefix.'_bg_image_path'] ?? null) : null;

        return new self(
            $type,
            $color,
            $recipe,
            $image,
            Scrim::fromSafe($raw[$prefix.'_bg_scrim'] ?? null, $type->usesImage()),
            $prefix,
        );
    }

    /**
     * The SINGLE check that turns a deleted background into a colour-only
     * render instead of a 404.
     */
    public function imageUrl(): ?string
    {
        if ($this->imagePath === null || $this->imagePath === '') {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            return $disk->exists($this->imagePath) ? $disk->url($this->imagePath) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** ALWAYS a valid #rrggbb, for every type including image and none. */
    public function baseHex(BrandPalette $palette): string
    {
        return match ($this->type) {
            BackgroundType::Brand => $palette->hex('primary'),
            BackgroundType::None => $palette->hex('background'),
            default => $this->colorHex ?? $palette->hex('primary'),
        };
    }

    /** Never authored, never stored, never client-computed. */
    public function foregroundHex(BrandPalette $palette): string
    {
        return $palette->foregroundOn($this->baseHex($palette));
    }

    public function contrast(BrandPalette $palette): float
    {
        return $palette->contrastOn($this->baseHex($palette));
    }

    /**
     * [] for Brand and None — this is what makes the upgrade a no-op.
     *
     * @return array<string,string>
     */
    public function cssVars(?string $prefix = null, ?BrandPalette $palette = null): array
    {
        if ($this->type === BackgroundType::Brand || $this->type === BackgroundType::None) {
            return [];
        }

        $prefix ??= $this->prefix;
        $palette ??= new BrandPalette(BrandPalette::PRESETS['zinc']);

        $vars = [
            "--myra-{$prefix}-bg" => Color::hexToOklch($this->baseHex($palette)),
            "--myra-{$prefix}-fg" => Color::hexToOklch($this->foregroundHex($palette)),
        ];

        $image = Recipes::value($this->recipe);

        if ($image !== null) {
            $vars["--myra-{$prefix}-image"] = $image;

            if (Recipes::isPattern($this->recipe)) {
                $vars["--myra-{$prefix}-image-size"] = Recipes::size($this->recipe);
            }
        }

        $vars["--myra-{$prefix}-scrim"] = (string) $this->scrim->opacity();

        return array_map(static fn (string $v): string => CssValue::safe($v), $vars);
    }

    /** The client payload. `css_vars` is an OBJECT so {} survives json_encode. */
    public function toArray(?BrandPalette $palette = null): array
    {
        $palette ??= new BrandPalette(BrandPalette::PRESETS['zinc']);

        return [
            'type' => $this->type->value,
            'recipe' => $this->recipe,
            'scrim' => $this->scrim->value,
            'image_url' => $this->imageUrl(),
            'base' => Color::hexToOklch($this->baseHex($palette)),
            'foreground' => Color::hexToOklch($this->foregroundHex($palette)),
            'contrast' => $this->contrast($palette),
            'css_vars' => (object) $this->cssVars($this->prefix, $palette),
        ];
    }

    /** Mirrors SectionField::coerceImage(): disk-relative only. */
    private static function coerceImage(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $raw = trim($value);

        if (str_contains($raw, '\\') || str_starts_with($raw, '/') || UrlGuard::hasScheme($raw)) {
            return null;
        }

        $path = ltrim($raw, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return mb_substr($path, 0, 2048);
    }
}
