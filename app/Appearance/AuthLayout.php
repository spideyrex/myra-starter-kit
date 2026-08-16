<?php

namespace App\Appearance;

/**
 * One selectable arrangement of the seven guest pages.
 *
 * Metadata only — never markup. Mirrors App\Homepage\HomepageTemplate, and
 * like it toClientSchema() is free of Laravel calls.
 */
final class AuthLayout
{
    public const FALLBACK_COMPONENT = 'SplitLayout';

    private string $component = self::FALLBACK_COMPONENT;

    private ?string $titleKey = null;

    private ?string $descriptionKey = null;

    private ?string $thumbnail = null;

    private bool $flippable = false;

    private bool $supportsMedia = false;

    private string $since = '2.8.0';

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function component(string $c): self
    {
        $this->component = $c;

        return $this;
    }

    public function flippable(bool $b = true): self
    {
        $this->flippable = $b;

        return $this;
    }

    public function supportsMedia(bool $b = true): self
    {
        $this->supportsMedia = $b;

        return $this;
    }

    public function titleKey(?string $k): self
    {
        $this->titleKey = $k;

        return $this;
    }

    public function descriptionKey(?string $k): self
    {
        $this->descriptionKey = $k;

        return $this;
    }

    public function thumbnail(?string $publicPath): self
    {
        $this->thumbnail = $publicPath;

        return $this;
    }

    public function since(string $v): self
    {
        $this->since = $v;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function componentName(): string
    {
        return $this->component;
    }

    public function isFlippable(): bool
    {
        return $this->flippable;
    }

    public function supportsMediaValue(): bool
    {
        return $this->supportsMedia;
    }

    public function resolvedTitleKey(): string
    {
        return $this->titleKey ?? "appearanceAdmin.layouts.{$this->key}.title";
    }

    public function resolvedDescriptionKey(): string
    {
        return $this->descriptionKey ?? "appearanceAdmin.layouts.{$this->key}.description";
    }

    /**
     * @return array{key:string,component:string,titleKey:string,descriptionKey:string,
     *               thumbnail:?string,flippable:bool,supportsMedia:bool,since:string}
     */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'component' => $this->component,
            'titleKey' => $this->resolvedTitleKey(),
            'descriptionKey' => $this->resolvedDescriptionKey(),
            'thumbnail' => $this->thumbnail,
            'flippable' => $this->flippable,
            'supportsMedia' => $this->supportsMedia,
            'since' => $this->since,
        ];
    }
}
