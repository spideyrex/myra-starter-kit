<?php

namespace App\Appearance;

enum BackgroundType: string
{
    case Brand = 'brand';
    case Solid = 'solid';
    case Gradient = 'gradient';
    case Pattern = 'pattern';
    case Image = 'image';
    case None = 'none';

    /** Total: anything unknown, non-string or absent is the auth default. */
    public static function fromSafe(mixed $v): self
    {
        return (is_string($v) ? self::tryFrom($v) : null) ?? self::Brand;
    }

    public function usesRecipe(): bool
    {
        return $this === self::Gradient || $this === self::Pattern;
    }

    public function usesImage(): bool
    {
        return $this === self::Image;
    }

    /** The recipe family this type draws from, or null. */
    public function recipeFamily(): ?string
    {
        return match ($this) {
            self::Gradient => 'gradient',
            self::Pattern => 'pattern',
            default => null,
        };
    }
}
