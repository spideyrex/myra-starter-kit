<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class HomepageSettings extends Settings
{
    public bool $enabled;
    public string $hero_title;
    public string $hero_subtitle;
    public string $hero_cta_text;
    public string $hero_cta_url;
    public ?string $hero_image_path;

    public bool $features_enabled;
    public string $features_title;
    public string $features_subtitle;
    public array $features;

    public bool $testimonials_enabled;
    public string $testimonials_title;
    public string $testimonials_subtitle;
    public array $testimonials;

    public bool $pricing_enabled;
    public string $pricing_title;
    public string $pricing_subtitle;
    public array $pricing_plans;

    public bool $cta_enabled;
    public string $cta_title;
    public string $cta_subtitle;
    public string $cta_button_text;
    public string $cta_button_url;

    public string $footer_text;
    public array $footer_links;

    public string $navbar_cta_text;
    public string $navbar_cta_url;
    public array $navbar_links;

    public static function group(): string
    {
        return 'homepage';
    }
}
