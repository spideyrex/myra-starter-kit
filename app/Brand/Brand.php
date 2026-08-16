<?php

namespace App\Brand;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final readonly class Brand implements Arrayable, JsonSerializable
{
    public function __construct(
        public bool $enabled,
        public string $name,
        public string $shortName,
        public string $tagline,
        public string $description,
        public ?string $logoUrl,
        public ?string $logoDarkUrl,
        public ?string $markUrl,
        public ?string $faviconUrl,
        public ?string $ogImageUrl,
        public string $logoPosition,
        public BrandPalette $palette,
        public BrandTypography $typography,
        public string $radius,
        public bool $darkDefault,
        public string $hash,
    ) {}

    /** DB-less boot. NEVER throws. */
    public static function fallback(): self
    {
        $name = (string) (config('app.name') ?: 'Admin');

        return new self(
            enabled: false,
            name: $name,
            shortName: mb_substr($name, 0, 24),
            tagline: '',
            description: '',
            logoUrl: null,
            logoDarkUrl: null,
            markUrl: null,
            faviconUrl: null,
            ogImageUrl: null,
            logoPosition: 'header',
            palette: BrandPalette::fromPreset('zinc'),
            typography: new BrandTypography(),
            radius: '0.625rem',
            darkDefault: false,
            hash: substr(md5('fallback|'.$name), 0, 8),
        );
    }

    /** THE single fallback-mark rule for the whole app. */
    public function initial(): string
    {
        $trimmed = trim($this->name);

        return $trimmed === '' ? '?' : mb_strtoupper(mb_substr($trimmed, 0, 1));
    }

    public function logoFor(bool $dark): ?string
    {
        return $dark ? ($this->logoDarkUrl ?? $this->logoUrl) : $this->logoUrl;
    }

    /** @return array<string,string> '--primary' => 'oklch(0.21 0 0)' */
    public function cssVariables(bool $dark = false): array
    {
        return array_merge(
            $dark ? $this->palette->dark() : $this->palette->light(),
            [
                '--radius' => $this->radius,
                '--font-sans' => $this->typography->stack('sans'),
                '--font-mono' => $this->typography->stack('mono'),
            ],
        );
    }

    /** The public Inertia `brand` prop. */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'name' => $this->name,
            'short_name' => $this->shortName,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'logo_url' => $this->logoUrl,
            'logo_dark_url' => $this->logoDarkUrl,
            'mark_url' => $this->markUrl,
            'favicon_url' => $this->faviconUrl,
            'og_image_url' => $this->ogImageUrl,
            'logo_position' => $this->logoPosition,
            'initial' => $this->initial(),
            'palette' => $this->palette->toArray(),
            'typography' => $this->typography->toArray(),
            'radius' => $this->radius,
            'dark_default' => $this->darkDefault,
            'hash' => $this->hash,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
