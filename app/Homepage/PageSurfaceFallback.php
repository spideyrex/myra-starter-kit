<?php

namespace App\Homepage;

/**
 * The inert page surface: what the homepage ships when the appearance engine
 * is absent, disabled or throwing. `none` paints nothing at all.
 */
final class PageSurfaceFallback
{
    /** @return array<string,mixed> */
    public static function inert(): array
    {
        return [
            'type' => 'none',
            'recipe' => null,
            'scrim' => 'medium',
            'image_url' => null,
            'base' => '',
            'foreground' => '',
            'contrast' => 0.0,
            'css_vars' => (object) [],
        ];
    }

    public static function navbarTranslucent(): bool
    {
        return false;
    }
}
