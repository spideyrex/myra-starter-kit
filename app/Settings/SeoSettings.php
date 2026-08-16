<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SeoSettings extends Settings
{
    public string $meta_title;
    public string $meta_description;
    public string $meta_keywords;
    public ?string $google_analytics_id;
    // >>> MYRA v2.6 [C] START — whitelisted by SettingController since v2.0 but
    // silently discarded by its property_exists() guard until now.
    public ?string $og_image_path;
    public ?string $robots_txt;
    // <<< MYRA v2.6 [C] END

    public static function group(): string
    {
        return 'seo';
    }
}
