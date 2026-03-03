<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AppearanceSettings extends Settings
{
    public string $primary_color;
    public ?string $logo_path;
    public ?string $favicon_path;

    public static function group(): string
    {
        return 'appearance';
    }
}
