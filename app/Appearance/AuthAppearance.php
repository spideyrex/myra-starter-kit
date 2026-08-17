<?php

namespace App\Appearance;

use App\Brand\BrandPalette;

final readonly class AuthAppearance
{
    public function __construct(
        public AuthLayout $layout,
        public bool $flip,
        public bool $showTagline,
        public Background $background,
    ) {}

    /** NO DB ACCESS. This is what the kill switch returns. */
    public static function stock(): self
    {
        return new self(
            AuthLayout::make(AuthLayoutRegistry::FALLBACK)
                ->component(AuthLayout::FALLBACK_COMPONENT)
                ->flippable()
                ->supportsMedia(),
            false,
            true,
            Background::default('auth'),
        );
    }

    public function toArray(?BrandPalette $palette = null): array
    {
        return [
            'layout' => $this->layout->key(),
            'component' => $this->layout->componentName(),
            'flip' => $this->flip && $this->layout->isFlippable(),
            'show_tagline' => $this->showTagline,
            'supports_media' => $this->layout->supportsMediaValue(),
            'surface' => $this->background->toArray($palette),
        ];
    }
}
