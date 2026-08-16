<?php

namespace App\Appearance;

enum Scrim: string
{
    case None = 'none';
    case Light = 'light';
    case Medium = 'medium';
    case Strong = 'strong';

    /** An image-backed surface is never left unscrimmed. */
    public const FLOOR_FOR_IMAGE = self::Light;

    public function opacity(): float
    {
        return match ($this) {
            self::None => 0.0,
            self::Light => 0.35,
            self::Medium => 0.55,
            self::Strong => 0.70,
        };
    }

    /**
     * The floor lives HERE, in normalisation, not in validation: a hand-edited
     * row, a seeder or a settings import can never produce an unscrimmed image.
     */
    public static function fromSafe(mixed $v, bool $imageBacked): self
    {
        $scrim = (is_string($v) ? self::tryFrom($v) : null) ?? self::Medium;

        return $imageBacked && $scrim === self::None ? self::FLOOR_FOR_IMAGE : $scrim;
    }
}
