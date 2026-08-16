<?php

namespace App\Admin\Navigation;

use Illuminate\Support\Facades\Route;

/**
 * One sidebar entry. `labelKey` is always an i18n key — the client resolves it,
 * so the server never ships user-facing copy.
 */
final class NavItem
{
    private ?string $href = null;

    private ?string $icon = null;

    private ?string $permission = null;

    private int $sort = 0;

    private ?string $activePrefix = null;

    /** @var NavItem[] */
    private array $items = [];

    private function __construct(private readonly string $labelKey) {}

    public static function make(string $labelKey): self
    {
        return new self($labelKey);
    }

    /**
     * Root-relative path, never absolute: the payload is compared byte-for-byte
     * in tests and must not carry the host. An unregistered route leaves the
     * item hrefless, and forUser() then drops it rather than crashing the sidebar.
     */
    public function route(string $name, array $params = []): self
    {
        $this->href = Route::has($name) ? route($name, $params, false) : null;

        return $this;
    }

    public function url(string $url): self
    {
        $this->href = $url;

        return $this;
    }

    public function icon(string $lucideName): self
    {
        $this->icon = $lucideName;

        return $this;
    }

    public function permission(?string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function activeWhen(string $pathPrefix): self
    {
        $this->activePrefix = $pathPrefix;

        return $this;
    }

    /** One level of children — a cluster. Anything that is not a NavItem is dropped. */
    public function items(array $children): self
    {
        $this->items = array_values(array_filter($children, fn ($c) => $c instanceof self));

        return $this;
    }

    public function labelKey(): string
    {
        return $this->labelKey;
    }

    public function href(): ?string
    {
        return $this->href;
    }

    public function permissionAbility(): ?string
    {
        return $this->permission;
    }

    public function sortOrder(): int
    {
        return $this->sort;
    }

    /** @return NavItem[] */
    public function children(): array
    {
        return $this->items;
    }

    /**
     * @return array{labelKey:string,href:?string,icon:?string,permission:?string,
     *               sort:int,activePrefix:?string,items:array}
     */
    public function toArray(): array
    {
        return [
            'labelKey' => $this->labelKey,
            'href' => $this->href,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'sort' => $this->sort,
            'activePrefix' => $this->activePrefix,
            'items' => array_map(fn (self $i) => $i->toArray(), $this->items),
        ];
    }
}
