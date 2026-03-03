<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name;
    public string $site_description;
    public string $site_url;
    public string $admin_email;
    public string $timezone;

    public static function group(): string
    {
        return 'general';
    }
}
