<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AppearanceSettings extends Settings
{
    public string $primary_color;
    public string $theme;
    public ?string $logo_path;
    public ?string $favicon_path;
    public string $logo_position;
    public ?string $sidebar_background;
    public ?string $sidebar_foreground;
    public ?string $sidebar_accent;

    public static function group(): string
    {
        return 'appearance';
    }
}
