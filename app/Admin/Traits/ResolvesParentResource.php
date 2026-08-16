<?php

namespace App\Admin\Traits;

use App\Admin\Resource\ParentResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

trait ResolvesParentResource
{
    abstract protected function parentResource(): ParentResource;

    /**
     * SECURITY CORE. Implicit route binding has already run; we re-query through
     * the model's OWN global scopes so an out-of-scope parent becomes a 404 and
     * never a readable id. NEVER call withoutGlobalScope here.
     */
    protected function resolveParent(Model $bound): Model
    {
        $resource = $this->parentResource();

        Gate::authorize($resource->permissionAbility());

        $class = $resource->modelClass();

        return $class::query()->whereKey($bound->getKey())->firstOrFail();
    }

    /** Read side: the children of this parent, as a Builder the query traits accept. */
    protected function childQuery(Model $parent): Builder
    {
        return $this->childRelation($parent)->getQuery();
    }

    /**
     * Write side: the relation itself, so create() stamps the foreign key from
     * the URL and a `course_id` in the request body can never redirect the row.
     */
    protected function childRelation(Model $parent): HasMany
    {
        return $parent->{$this->parentResource()->relationshipName()}();
    }

    protected function parentPayload(Model $parent): array
    {
        $resource = $this->parentResource();

        return [
            'id' => $parent->getKey(),
            'title' => (string) $parent->{$resource->titleColumn()},
        ];
    }

    /**
     * Breadcrumb chain. Static segments travel as i18n keys, the record title as
     * a literal — the client resolves whichever is present.
     *
     * @return array<int,array{labelKey:?string,label:?string,href:?string}>
     */
    protected function parentBreadcrumbs(Model $parent, ?string $leaf = null): array
    {
        $resource = $this->parentResource();
        $indexRoute = "admin.{$resource->routePrefix()}.index";

        $crumbs = [
            [
                'labelKey' => "clusters.{$resource->routePrefix()}.label",
                'label' => null,
                'href' => Route::has($indexRoute) ? route($indexRoute, [], false) : null,
            ],
            [
                'labelKey' => null,
                'label' => (string) $parent->{$resource->titleColumn()},
                'href' => null,
            ],
        ];

        if ($leaf !== null) {
            $crumbs[] = ['labelKey' => $leaf, 'label' => null, 'href' => null];
        }

        return $crumbs;
    }

    protected function childRoute(Model $parent, string $action, array $extra = []): string
    {
        $resource = $this->parentResource();
        $name = "admin.{$resource->routePrefix()}.{$resource->relationshipName()}.{$action}";

        return route($name, array_merge([$resource->inverseName() => $parent->getKey()], $extra), false);
    }
}
