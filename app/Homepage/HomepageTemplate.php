<?php

namespace App\Homepage;

/**
 * One selectable arrangement of the public landing page.
 *
 * Templates are chrome and layout only — never a content fork. Every one of
 * them renders the same HomepageSettings payload, so switching is reversible
 * and no copy is duplicated.
 *
 * `toClientSchema()` is free of Laravel calls, exactly like DemoEntry, so the
 * payload is a pure function of the declaration.
 */
final class HomepageTemplate
{
    public const FALLBACK_COMPONENT = 'Public/Templates/Classic';

    /** The sections a template may declare support for. */
    public const SECTIONS = ['hero', 'features', 'testimonials', 'pricing', 'cta'];

    private string $component = self::FALLBACK_COMPONENT;

    private ?string $titleKey = null;

    private ?string $descriptionKey = null;

    private ?string $thumbnail = null;

    /** @var string[] */
    private array $supports = self::SECTIONS;

    private string $since = '2.6.0';

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function component(string $inertiaPath): self
    {
        $this->component = $inertiaPath;

        return $this;
    }

    public function titleKey(string $k): self
    {
        $this->titleKey = $k;

        return $this;
    }

    public function descriptionKey(string $k): self
    {
        $this->descriptionKey = $k;

        return $this;
    }

    public function thumbnail(string $publicPath): self
    {
        $this->thumbnail = $publicPath;

        return $this;
    }

    public function supports(string ...$sections): self
    {
        $this->supports = array_values(array_intersect(self::SECTIONS, $sections));

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

    public function componentValue(): string
    {
        return $this->component;
    }

    public function thumbnailValue(): ?string
    {
        return $this->thumbnail;
    }

    public function supportsSection(string $s): bool
    {
        return in_array($s, $this->supports, true);
    }

    public function resolvedTitleKey(): string
    {
        return $this->titleKey ?? "landing.templates.{$this->key}.title";
    }

    public function resolvedDescriptionKey(): string
    {
        return $this->descriptionKey ?? "landing.templates.{$this->key}.description";
    }

    /**
     * @return array{key:string,component:string,titleKey:string,descriptionKey:string,
     *               thumbnail:?string,supports:array<int,string>,since:string}
     */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'component' => $this->component,
            'titleKey' => $this->resolvedTitleKey(),
            'descriptionKey' => $this->resolvedDescriptionKey(),
            'thumbnail' => $this->thumbnail,
            'supports' => $this->supports,
            'since' => $this->since,
        ];
    }
}
