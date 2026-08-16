<?php

namespace App\Models\Traits;

use App\Admin\Tenancy\Tenancy;
use App\Admin\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping, gated behind BOTH `myra.tenancy.enabled` and the model being
 * listed in `myra.tenancy.models`.
 *
 * When either lock is shut this trait registers NOTHING — no global scope, no
 * creating hook. A scope that is registered and returns early still mutates the
 * builder and still shows up in getGlobalScopes(); that is not good enough for
 * a feature that decides who sees which rows.
 *
 * The tenant column is never added to $fillable.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        if (! Tenancy::scopes(static::class)) {
            return;
        }

        Tenancy::assertConfigured();

        static::creating(function (Model $model) {
            $column = $model->tenantColumn();
            if (empty($model->{$column})) {
                $model->{$column} = Tenancy::id();
            }
        });

        static::addGlobalScope('tenant', new TenantScope);
    }

    public function tenantColumn(): string
    {
        return property_exists($this, 'tenantColumn') ? $this->tenantColumn : Tenancy::column();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenancy::model(), $this->tenantColumn());
    }
}
