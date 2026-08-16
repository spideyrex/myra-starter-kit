<?php

namespace App\Admin\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Registered only when both tenancy locks are open. See BelongsToTenant. */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        Tenancy::apply($builder, $model->getTable());
    }
}
