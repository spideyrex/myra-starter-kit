<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BrandSettings extends Settings
{
    public bool $enabled;
    public string $name;
    public ?string $short_name;
    public string $tagline;
    public string $description;
    public ?string $logo_path;
    public ?string $logo_dark_path;
    public ?string $mark_path;
    public ?string $favicon_path;
    public ?string $og_image_path;
    public string $logo_position;
    public string $primary;
    public ?string $accent;
    public ?string $sidebar_background;
    public ?string $sidebar_foreground;
    public ?string $sidebar_accent;
    public string $preset;
    public string $font_sans;
    public string $font_mono;
    public string $radius;
    public bool $dark_default;
    public int $version;

    public static function group(): string
    {
        return 'brand';
    }
}
