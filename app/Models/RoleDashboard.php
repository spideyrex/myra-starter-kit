<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-authored dashboard default for one role. The payload is the SAME
 * shape as a personal layout and is UNTRUSTED at render: every read goes
 * through LayoutResolver::fromPayload() with the VIEWING user.
 */
class RoleDashboard extends Model
{
    protected $fillable = ['role_id', 'dashboard_key', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeForRole(Builder $q, Role|int $role): Builder
    {
        return $q->where('role_id', $role instanceof Role ? $role->id : $role);
    }

    public function scopeForKey(Builder $q, string $key): Builder
    {
        return $q->where('dashboard_key', $key);
    }
}
