<?php

namespace App\Brand;

/**
 * PHP port of resources/js/composables/useThemeColors.ts.
 *
 * The TS formatting is part of the contract: L and C to 3 decimals, H to 1.
 * tests/Unit/Brand/ColorParityTest asserts both sides agree string-for-string.
 */
final class Color
{
    public static function isValidHex(string $value): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $value);
    }

    /** Falls back to black rather than throwing — brand resolution never throws. */
    public static function normalise(?string $hex, string $fallback = '#000000'): string
    {
        if ($hex === null) {
            return $fallback;
        }

        $hex = trim($hex);

        if (preg_match('/^#?([0-9a-fA-F]{3})$/', $hex, $m)) {
            $hex = '#'.$m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2];
        } elseif (preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m)) {
            $hex = '#'.$m[1];
        } else {
            return $fallback;
        }

        return strtolower($hex);
    }

    public static function hexToOklch(string $hex): string
    {
        [$L, $C, $H] = self::oklch($hex);

        return sprintf('oklch(%s %s %s)', self::fixed($L, 3), self::fixed($C, 3), self::fixed($H, 1));
    }

    public static function isLight(string $hex): bool
    {
        return self::oklch($hex)[0] > 0.6;
    }

    public static function adjustLightness(string $hex, float $delta): string
    {
        [$L, $C, $H] = self::oklch($hex);
        $L = max(0.0, min(1.0, $L + $delta));

        return sprintf('oklch(%s %s %s)', self::fixed($L, 3), self::fixed($C, 3), self::fixed($H, 1));
    }

    public static function rotateHue(string $hex, float $degrees): string
    {
        [$L, $C, $H] = self::oklch($hex);
        $H = fmod($H + $degrees, 360.0);
        if ($H < 0) {
            $H += 360.0;
        }

        return sprintf('oklch(%s %s %s)', self::fixed($L, 3), self::fixed($C, 3), self::fixed($H, 1));
    }

    public static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::toNormalisedRgb($hex);

        $lin = static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }

    public static function contrastRatio(string $a, string $b): float
    {
        $la = self::relativeLuminance($a);
        $lb = self::relativeLuminance($b);

        $hi = max($la, $lb);
        $lo = min($la, $lb);

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /** @return array{0:float,1:float,2:float} 0..1 RGB for PdfDocument/ChartVector */
    public static function toNormalisedRgb(string $hex): array
    {
        $hex = self::normalise($hex);

        return [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];
    }

    /** Same lightness shift as adjustLightness(), but back as #rrggbb. */
    public static function shiftHex(string $hex, float $delta): string
    {
        [$L, $C, $H] = self::oklch($hex);

        return self::oklchToHex(max(0.0, min(1.0, $L + $delta)), $C, $H);
    }

    public static function rotateHueHex(string $hex, float $degrees): string
    {
        [$L, $C, $H] = self::oklch($hex);
        $H = fmod($H + $degrees, 360.0);
        if ($H < 0) {
            $H += 360.0;
        }

        return self::oklchToHex($L, $C, $H);
    }

    /** OKLCH → clamped sRGB hex (the inverse of oklch()). */
    public static function oklchToHex(float $L, float $C, float $H): string
    {
        $rad = $H * (M_PI / 180);
        $a = $C * cos($rad);
        $b = $C * sin($rad);

        $l_ = $L + 0.3963377774 * $a + 0.2158037573 * $b;
        $m_ = $L - 0.1055613458 * $a - 0.0638541728 * $b;
        $s_ = $L - 0.0894841775 * $a - 1.2914855480 * $b;

        $l = $l_ ** 3;
        $m = $m_ ** 3;
        $s = $s_ ** 3;

        $lr = 4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s;
        $lg = -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s;
        $lb = -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s;

        $gamma = static function (float $c): int {
            $c = $c <= 0.0031308 ? 12.92 * $c : 1.055 * (max($c, 0.0) ** (1 / 2.4)) - 0.055;

            return (int) max(0, min(255, (int) round($c * 255)));
        };

        return sprintf('#%02x%02x%02x', $gamma($lr), $gamma($lg), $gamma($lb));
    }

    /** @return array{0:float,1:float,2:float} OKLCH triple, unrounded. */
    public static function oklch(string $hex): array
    {
        [$r, $g, $b] = self::toNormalisedRgb($hex);

        $toLinear = static fn (float $c): float => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $lr = $toLinear($r);
        $lg = $toLinear($g);
        $lb = $toLinear($b);

        $l_ = self::cbrt(0.4122214708 * $lr + 0.5363325363 * $lg + 0.0514459929 * $lb);
        $m_ = self::cbrt(0.2119034982 * $lr + 0.6806995451 * $lg + 0.1073969566 * $lb);
        $s_ = self::cbrt(0.0883024619 * $lr + 0.2817188376 * $lg + 0.6299787005 * $lb);

        $L = 0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_;
        $a = 1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_;
        $bOk = 0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_;

        $C = sqrt($a * $a + $bOk * $bOk);
        $H = atan2($bOk, $a) * (180 / M_PI);
        if ($H < 0) {
            $H += 360;
        }

        return [$L, $C, $H];
    }

    /** JS Number.prototype.toFixed semantics: fixed decimals, no locale grouping. */
    private static function fixed(float $value, int $decimals): string
    {
        $out = sprintf('%.'.$decimals.'F', $value);

        return $out === '-'.sprintf('%.'.$decimals.'F', 0.0) ? sprintf('%.'.$decimals.'F', 0.0) : $out;
    }

    private static function cbrt(float $x): float
    {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }
}
