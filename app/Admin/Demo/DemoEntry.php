<?php

namespace App\Admin\Demo;

/**
 * One catalogue card in the feature gallery.
 *
 * Every user-facing string is an i18n KEY, never copy: the server ships
 * identifiers and the client resolves them, which is what lets the same
 * registry drive the gallery, the command palette and the ms/zh locales.
 *
 * `toClientSchema()` is deliberately free of Laravel calls so the payload is a
 * pure function of the declaration — the committed fixture can be regenerated
 * without booting the framework, and the shape cannot drift with the container.
 */
final class DemoEntry
{
    private ?string $titleKey = null;

    private ?string $descriptionKey = null;

    private string $routeName = '';

    private string $icon = 'LayoutGrid';

    private ?string $badgeKey = null;

    /** @var string[] */
    private array $tags = [];

    private string $since = '1.0.0';

    private ?string $permission = null;

    private ?string $playground = null;

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
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

    public function routeName(string $n): self
    {
        $this->routeName = $n;

        return $this;
    }

    public function icon(string $lucideName): self
    {
        $this->icon = $lucideName;

        return $this;
    }

    public function badgeKey(?string $k): self
    {
        $this->badgeKey = $k;

        return $this;
    }

    /** @param string[] $tags */
    public function tags(array $tags): self
    {
        $this->tags = array_values(array_filter($tags, 'is_string'));

        return $this;
    }

    public function since(string $version): self
    {
        $this->since = $version;

        return $this;
    }

    public function permission(?string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    /** Names a client-side PlaygroundSpec key; null when the page has no playground. */
    public function playground(?string $specKey): self
    {
        $this->playground = $specKey;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function routeNameValue(): string
    {
        return $this->routeName;
    }

    public function permissionAbility(): ?string
    {
        return $this->permission;
    }

    /** Defaults keep 27 declarations from repeating three key paths each. */
    public function resolvedTitleKey(): string
    {
        return $this->titleKey ?? "gallery.demos.{$this->key}.title";
    }

    public function resolvedDescriptionKey(): string
    {
        return $this->descriptionKey ?? "gallery.demos.{$this->key}.description";
    }

    public function resolvedBadgeKey(): ?string
    {
        return $this->badgeKey ?? "gallery.demos.{$this->key}.badge";
    }

    /**
     * @return array{key:string,titleKey:string,descriptionKey:string,route:string,
     *               icon:string,badgeKey:?string,tags:array<int,string>,since:string,
     *               playground:?string}
     */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'titleKey' => $this->resolvedTitleKey(),
            'descriptionKey' => $this->resolvedDescriptionKey(),
            'route' => $this->routeName,
            'icon' => $this->icon,
            'badgeKey' => $this->resolvedBadgeKey(),
            'tags' => $this->tags,
            'since' => $this->since,
            'playground' => $this->playground,
        ];
    }
}
