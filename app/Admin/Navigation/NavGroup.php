<?php

namespace App\Admin\Navigation;

/** A sidebar group. Merges into a core group with the same RESOLVED label. */
final class NavGroup
{
    private int $sort = 0;

    /** @var NavItem[] */
    private array $items = [];

    private function __construct(private readonly string $labelKey) {}

    public static function make(string $labelKey): self
    {
        return new self($labelKey);
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /** Appends, so a group may be built up from several calls. */
    public function items(array $navItems): self
    {
        foreach ($navItems as $item) {
            if ($item instanceof NavItem) {
                $this->items[] = $item;
            }
        }

        return $this;
    }

    public function labelKey(): string
    {
        return $this->labelKey;
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

    /** @return array{labelKey:string,sort:int,items:array} */
    public function toArray(): array
    {
        return [
            'labelKey' => $this->labelKey,
            'sort' => $this->sort,
            'items' => array_map(fn (NavItem $i) => $i->toArray(), $this->items),
        ];
    }
}
