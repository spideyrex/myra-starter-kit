<?php

namespace App\Admin\Search;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SearchSource
{
    /** @var class-string<Model> */
    private string $model;

    private ?string $permission = null;

    private string $groupLabelKey;

    private ?string $icon = null;

    /** @var array<string,float> */
    private array $attributes = [];

    private ?Closure $scope = null;

    private ?Closure $title = null;

    private ?Closure $description = null;

    private ?Closure $url = null;

    /** @var string[] */
    private array $eagerLoad = [];

    private ?string $recencyColumn = null;

    private float $recencyWeight = 0.0;

    private int $limit = 8;

    private int $sort = 0;

    private string $matchMode = 'starts';

    private function __construct(public readonly string $key)
    {
        $this->groupLabelKey = "search.groups.{$key}";
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    /** @param  class-string<Model>  $class */
    public function model(string $class): self
    {
        $this->model = $class;

        return $this;
    }

    public function permission(?string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    public function groupLabelKey(string $key): self
    {
        $this->groupLabelKey = $key;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /** @param  array<string,float>  $weights */
    public function attributes(array $weights): self
    {
        $this->attributes = $weights;

        return $this;
    }

    public function scope(Closure $fn): self
    {
        $this->scope = $fn;

        return $this;
    }

    public function titleUsing(Closure $fn): self
    {
        $this->title = $fn;

        return $this;
    }

    public function descriptionUsing(Closure $fn): self
    {
        $this->description = $fn;

        return $this;
    }

    public function urlUsing(Closure $fn): self
    {
        $this->url = $fn;

        return $this;
    }

    /** @param  string[]  $relations */
    public function eagerLoad(array $relations): self
    {
        $this->eagerLoad = $relations;

        return $this;
    }

    public function recency(string $column, float $weight = 0.15): self
    {
        $this->recencyColumn = $column;
        $this->recencyWeight = $weight;

        return $this;
    }

    public function limit(int $n): self
    {
        $this->limit = max(1, $n);

        return $this;
    }

    public function sort(int $n): self
    {
        $this->sort = $n;

        return $this;
    }

    /** Opt in to `%term%` candidate fetching; the default `term%` is index-usable. */
    public function contains(bool $v = true): self
    {
        $this->matchMode = $v ? 'contains' : 'starts';

        return $this;
    }

    // --- readers -----------------------------------------------------------

    public function modelClass(): string
    {
        return $this->model;
    }

    public function permissionAbility(): ?string
    {
        return $this->permission;
    }

    public function groupKey(): string
    {
        return $this->groupLabelKey;
    }

    public function iconName(): ?string
    {
        return $this->icon;
    }

    /** @return string[] */
    public function attributeNames(): array
    {
        return array_keys($this->attributes);
    }

    public function weight(string $attribute): float
    {
        return (float) ($this->attributes[$attribute] ?? 1.0);
    }

    public function hasScope(): bool
    {
        return $this->scope !== null;
    }

    public function matchMode(): string
    {
        return $this->matchMode;
    }

    public function limitCount(): int
    {
        return $this->limit;
    }

    public function sortOrder(): int
    {
        return $this->sort;
    }

    public function recencyColumn(): ?string
    {
        return $this->recencyColumn;
    }

    public function recencyWeight(): float
    {
        return $this->recencyWeight;
    }

    /** @return string[] */
    public function eagerLoads(): array
    {
        return $this->eagerLoad;
    }

    public function visibleTo(?Authenticatable $user): bool
    {
        if ($this->permission === null) {
            return true;
        }

        return $user instanceof Authorizable && $user->can($this->permission);
    }

    public function applyScope(Builder $query, ?Authenticatable $user): Builder
    {
        return $this->scope ? (($this->scope)($query, $user) ?? $query) : $query;
    }

    public function resolveTitle(Model $record): string
    {
        return (string) ($this->title ? ($this->title)($record) : ($record->getAttribute('name') ?? ''));
    }

    public function resolveDescription(Model $record): string
    {
        return (string) ($this->description ? ($this->description)($record) : '');
    }

    public function resolveUrl(Model $record): string
    {
        return (string) ($this->url ? ($this->url)($record) : '');
    }
}
