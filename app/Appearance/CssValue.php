<?php

namespace App\Appearance;

/**
 * The ONE character allowlist for anything that reaches a CSS declaration.
 *
 * Lifted verbatim out of BrandManager::styleTag(), which now delegates here.
 * It strips `#` and `:`, so no hex and no URL can ever survive: colours are
 * emitted as oklch() and images are real <img> elements.
 */
final class CssValue
{
    public static function safe(string $value): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9 ,.%()\/\'\-_]/', '', $value);
    }
}
