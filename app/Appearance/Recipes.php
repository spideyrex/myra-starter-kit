<?php

namespace App\Appearance;

/**
 * The allowlisted background recipes. An admin stores a KEY; the CSS value is
 * authored here, in PHP, and never by a user.
 *
 * Every value is written over var(--primary)/var(--accent) so it re-tints with
 * the brand, and every value survives CssValue::safe() unmodified — no `#`,
 * no `:`, no url().
 */
final class Recipes
{
    /** @var array<string,string> key => CSS background-image value */
    public const GRADIENTS = [
        'brand-fade' => 'linear-gradient(180deg,var(--primary) 0%,transparent 70%)',
        'brand-mesh' => 'radial-gradient(at 20% 20%,var(--primary) 0%,transparent 50%),radial-gradient(at 80% 60%,var(--accent) 0%,transparent 50%)',
        'dusk' => 'linear-gradient(160deg,var(--primary) 0%,oklch(0.25 0.05 280) 100%)',
        'dawn' => 'linear-gradient(160deg,var(--accent) 0%,oklch(0.92 0.04 60) 100%)',
        'ink' => 'linear-gradient(180deg,oklch(0.18 0.01 260) 0%,oklch(0.10 0.01 260) 100%)',
        'aurora' => 'linear-gradient(135deg,var(--primary) 0%,var(--accent) 50%,transparent 100%)',
    ];

    /** @var array<string,string> key => CSS background-image value */
    public const PATTERNS = [
        'dots' => 'radial-gradient(currentColor 1px,transparent 1px)',
        'grid' => 'linear-gradient(currentColor 1px,transparent 1px),linear-gradient(90deg,currentColor 1px,transparent 1px)',
        'diagonal' => 'repeating-linear-gradient(45deg,currentColor 0 1px,transparent 1px 12px)',
        'noise' => 'radial-gradient(currentColor 0.5px,transparent 0.5px)',
    ];

    /** @var array<string,string> */
    private const PATTERN_SIZES = [
        'dots' => '16px 16px',
        'noise' => '16px 16px',
        'grid' => '24px 24px',
        'diagonal' => 'auto',
    ];

    public static function value(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::GRADIENTS[$key] ?? self::PATTERNS[$key] ?? null;
    }

    /** @return array<int,string> */
    public static function keys(string $family): array
    {
        return array_keys($family === 'pattern' ? self::PATTERNS : self::GRADIENTS);
    }

    public static function isPattern(?string $key): bool
    {
        return $key !== null && isset(self::PATTERNS[$key]);
    }

    /** background-size for a pattern; 'auto' for anything else. */
    public static function size(?string $key): string
    {
        return $key !== null ? (self::PATTERN_SIZES[$key] ?? 'auto') : 'auto';
    }

    /** The stored key only if it belongs to $family; null otherwise. */
    public static function inFamily(mixed $key, ?string $family): ?string
    {
        if ($family === null || ! is_string($key) || $key === '') {
            return null;
        }

        return in_array($key, self::keys($family), true) ? $key : null;
    }
}
