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

    // >>> MYRA v2.8 [A] START
    // Flat scalars only: spatie's PropertyReflector cannot resolve nested array
    // generics, and flat rows let the admin writer persist by name without
    // depending on these typed properties.
    public string $auth_layout;

    public bool $auth_flip;

    public bool $auth_show_tagline;

    public string $auth_bg_type;

    public ?string $auth_bg_color;

    public ?string $auth_bg_recipe;

    public ?string $auth_bg_image_path;

    public string $auth_bg_scrim;

    public string $page_bg_type;

    public ?string $page_bg_color;

    public ?string $page_bg_recipe;

    public ?string $page_bg_image_path;

    public string $page_bg_scrim;

    public bool $page_navbar_translucent;
    // <<< MYRA v2.8 [A] END

    public static function group(): string
    {
        return 'appearance';
    }
}
