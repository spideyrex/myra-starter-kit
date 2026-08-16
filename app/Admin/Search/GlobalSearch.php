<?php

namespace App\Admin\Search;

use App\Admin\Tenancy\Tenancy;
use App\Support\Sql;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class GlobalSearch
{
    /** @var array<string,SearchSource> */
    private array $sources = [];

    /** @var array<int,array{title:string,labelKey:?string,url:string,permission:?string,icon:?string}> */
    private array $pages = [];

    private ?\Closure $bootstrapper = null;

    private bool $bootstrapped = false;

    public function bootstrapUsing(\Closure $fn): void
    {
        $this->bootstrapper = $fn;
        $this->bootstrapped = false;
    }

    private function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->bootstrapped = true;

        if ($this->bootstrapper) {
            ($this->bootstrapper)($this);
        }
    }

    public function register(SearchSource ...$sources): void
    {
        foreach ($sources as $source) {
            $this->assertScoped($source);
            $this->sources[$source->key] = $source;
        }
    }

    /**
     * Navigation / settings entries, so Cmd+K is a real command palette
     * rather than a record search.
     *
     * @param  array<int,array<string,string|null>>  $pages
     */
    public function pages(array $pages): void
    {
        foreach ($pages as $page) {
            $this->pages[] = [
                'title' => (string) ($page['title'] ?? ''),
                'labelKey' => $page['labelKey'] ?? null,
                'url' => (string) ($page['url'] ?? ''),
                'permission' => $page['permission'] ?? null,
                'icon' => $page['icon'] ?? null,
            ];
        }
    }

    /** @return array<string,SearchSource> */
    public function sources(): array
    {
        $this->bootstrap();

        return $this->sources;
    }

    public function flush(): void
    {
        $this->sources = [];
        $this->pages = [];
        $this->bootstrapped = true;
    }

    /** @return array<int,array<string,mixed>> */
    public function search(string $term, ?Authenticatable $user): array
    {
        $this->bootstrap();

        $groups = [];

        foreach ($this->sources as $source) {
            if (! $source->visibleTo($user)) {
                continue;
            }

            $items = $this->searchSource($source, $term, $user);
            if ($items === []) {
                continue;
            }

            $groups[] = [
                'key' => $source->key,
                'group' => $source->key,
                'labelKey' => $source->groupKey(),
                'icon' => $source->iconName(),
                'sort' => $source->sortOrder(),
                'best' => $items[0]['score'],
                'items' => $items,
            ];
        }

        $pageItems = $this->searchPages($term, $user);
        if ($pageItems !== []) {
            $groups[] = [
                'key' => 'pages',
                'group' => 'pages',
                'labelKey' => 'search.groups.pages',
                'icon' => 'LayoutGrid',
                'sort' => -100,
                'best' => $pageItems[0]['score'],
                'items' => $pageItems,
            ];
        }

        usort($groups, fn ($a, $b) => [$b['best'], $b['sort']] <=> [$a['best'], $a['sort']]);

        return $this->capTotal($groups);
    }

    /** @return array<int,array<string,mixed>> */
    private function searchSource(SearchSource $source, string $term, ?Authenticatable $user): array
    {
        $model = $source->modelClass();
        $query = $source->applyScope($model::query(), $user);

        if ($source->eagerLoads() !== []) {
            $query->with($source->eagerLoads());
        }

        // The OR set is ALWAYS wrapped. An unwrapped orWhere here lets the
        // match escape any scope added later — a whole-table leak.
        $query->where(function (Builder $q) use ($source, $term) {
            foreach ($source->attributeNames() as $attribute) {
                Sql::orWhereLike($q, $attribute, $term, $source->matchMode());
            }
        });

        $candidates = $query->limit($source->limitCount() * 4)->get();

        $items = [];
        foreach ($candidates as $record) {
            /** @var Model $record */
            $best = 0.0;
            foreach ($source->attributeNames() as $attribute) {
                $value = (string) ($record->getAttribute($attribute) ?? '');
                $best = max($best, $source->weight($attribute) * Scorer::matchKind($value, $term));
            }

            if ($best <= 0.0) {
                continue;
            }

            $recency = $source->recencyColumn();
            $score = $best + Scorer::recencyBoost(
                $recency ? (string) $record->getAttribute($recency) : null,
                $source->recencyWeight(),
            );

            $title = $source->resolveTitle($record);
            $description = $source->resolveDescription($record);

            $items[] = [
                'id' => $record->getKey(),
                'title' => $title,
                'description' => $description,
                'url' => $source->resolveUrl($record),
                'score' => round($score, 6),
                'recency' => $recency ? (string) $record->getAttribute($recency) : null,
                'matches' => array_merge(
                    Scorer::matchRanges('title', $title, $term),
                    Scorer::matchRanges('description', $description, $term),
                ),
            ];
        }

        usort($items, fn ($a, $b) => [$b['score'], (string) $b['recency']] <=> [$a['score'], (string) $a['recency']]);

        return array_slice($items, 0, $source->limitCount());
    }

    /** @return array<int,array<string,mixed>> */
    private function searchPages(string $term, ?Authenticatable $user): array
    {
        $items = [];

        foreach ($this->pages as $i => $page) {
            if ($page['permission'] !== null
                && ! ($user instanceof Authorizable && $user->can($page['permission']))) {
                continue;
            }

            $kind = Scorer::matchKind($page['title'], $term);
            if ($kind <= 0.0) {
                continue;
            }

            $items[] = [
                'id' => 'page-' . $i,
                'title' => $page['title'],
                'description' => '',
                'url' => $page['url'],
                'labelKey' => $page['labelKey'],
                'icon' => $page['icon'],
                'score' => round($kind, 6),
                'recency' => null,
                'matches' => Scorer::matchRanges('title', $page['title'], $term),
            ];
        }

        usort($items, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($items, 0, 5);
    }

    /** Global cap across all sources, so N sources no longer mean N×limit rows. */
    private function capTotal(array $groups): array
    {
        $max = (int) config('myra.search.max_results', 40);
        $remaining = $max;
        $out = [];

        foreach ($groups as $group) {
            if ($remaining <= 0) {
                break;
            }
            $group['items'] = array_slice($group['items'], 0, $remaining);
            $remaining -= count($group['items']);
            unset($group['best'], $group['sort']);
            $out[] = $group;
        }

        return $out;
    }

    /**
     * In non-production, a source whose model carries `created_by` — or, once
     * tenancy is on, the tenant column — but declares no scope() is a boot
     * error. Forgetting the scope must never be a leak.
     */
    private function assertScoped(SearchSource $source): void
    {
        if ($source->hasScope() || app()->environment('production')) {
            return;
        }

        $tenantColumn = Tenancy::enabled() ? Tenancy::column() : null;

        try {
            /** @var Model $model */
            $model = new ($source->modelClass());
            $schema = Schema::connection($model->getConnectionName());
            $hasOwner = $schema->hasColumn($model->getTable(), 'created_by');
            $hasTenant = $tenantColumn !== null && $schema->hasColumn($model->getTable(), $tenantColumn);
        } catch (\Throwable) {
            return; // DB unavailable at boot — nothing to assert against.
        }

        if ($hasOwner) {
            throw new MissingSearchScopeException(
                "Search source [{$source->key}] has an owner column but no scope().",
            );
        }

        if ($hasTenant) {
            throw new MissingSearchScopeException(
                "Search source [{$source->key}] has a {$tenantColumn} column but no scope().",
            );
        }
    }
}
