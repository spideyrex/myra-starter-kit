<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AiSettings extends Settings
{
    public bool $enabled;
    public ?string $provider;
    public ?string $api_key;
    public ?string $model;
    public ?string $base_url;
    public float $temperature;
    public int $max_tokens;

    public static function group(): string
    {
        return 'ai';
    }
}
