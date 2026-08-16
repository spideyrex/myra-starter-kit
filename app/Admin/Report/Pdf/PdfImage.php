<?php

namespace App\Admin\Report\Pdf;

use App\Brand\BrandManager;

/**
 * Resolves the raster a PDF may embed. Returns null rather than throwing, so a
 * missing derivative degrades to a text-only header.
 */
final class PdfImage
{
    public static function brandLogo(): ?string
    {
        try {
            return app(BrandManager::class)->logoBytes('pdf');
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0:int,1:int}|null width/height of a PNG, from its IHDR. */
    public static function pngSize(string $binary): ?array
    {
        if (strlen($binary) < 24 || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        $parts = unpack('Nwidth/Nheight', substr($binary, 16, 8));

        return $parts === false ? null : [(int) $parts['width'], (int) $parts['height']];
    }
}
