<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user data ownership.
 *
 * Stamps the owner column with the authenticated user's id on create, and adds
 * an "owned" global scope that limits queries to the current user's rows —
 * unless the user is a super-admin (who sees everything) or the request is
 * unauthenticated (e.g. public blog/page views, so guests are unaffected).
 *
 * Public controllers that must show all rows to logged-in non-super users
 * should call `Model::withoutGlobalScope('owned')`.
 *
 * Not used on the User or EmailTemplate models: a global scope there would
 * break auth session resolution and system email-template lookups. Those are
 * scoped at the query level instead.
 */
trait OwnedByUser
{
    public static function bootOwnedByUser(): void
    {
        static::creating(function (Model $model) {
            $column = $model->ownerColumn();
            if (empty($model->{$column}) && auth()->check()) {
                $model->{$column} = auth()->id();
            }
        });

        static::addGlobalScope('owned', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            $superRole = config('shield.super_admin_role', 'super-admin');
            if (auth()->user()->hasRole($superRole)) {
                return;
            }

            $model = $builder->getModel();
            $builder->where($model->getTable() . '.' . $model->ownerColumn(), auth()->id());
        });
    }

    /** The column holding the owning user id. Override per-model if needed. */
    public function ownerColumn(): string
    {
        return property_exists($this, 'ownerColumn') ? $this->ownerColumn : 'created_by';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, $this->ownerColumn());
    }
}
