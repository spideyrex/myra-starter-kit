<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Custom role model adding super-admin-managed state:
 *   - is_active: disabled roles cannot be assigned to users
 *   - visible:   hidden roles are omitted from non-super-admin pickers/lists
 *   - priority:  higher wins when a multi-role user resolves a role dashboard
 *
 * Registered via config/permission.php → models.role.
 */
class Role extends SpatieRole
{
    // >>> MYRA v2.7 [A] START
    protected $casts = [
        'is_active' => 'boolean',
        'visible' => 'boolean',
        'priority' => 'integer',
    ];
    // <<< MYRA v2.7 [A] END

    /** Roles that are always active and visible and may only be assigned by a super-admin. */
    public function isPrivileged(): bool
    {
        return in_array($this->name, config('shield.privileged_roles', ['super-admin', 'admin']), true);
    }

    /** The super-admin role can never be edited, disabled, or hidden. */
    public function isLocked(): bool
    {
        return $this->name === config('shield.super_admin_role', 'super-admin');
    }

    // >>> MYRA v2.7 [A] START
    public function dashboards(): HasMany
    {
        return $this->hasMany(RoleDashboard::class);
    }

    /** Highest priority first; ties broken deterministically by id. */
    public function scopeByPriority(Builder $q): Builder
    {
        return $q->orderByDesc('priority')->orderBy('id');
    }
    // <<< MYRA v2.7 [A] END
}
