<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Custom role model adding super-admin-managed state:
 *   - is_active: disabled roles cannot be assigned to users
 *   - visible:   hidden roles are omitted from non-super-admin pickers/lists
 *
 * Registered via config/permission.php → models.role.
 */
class Role extends SpatieRole
{
    protected $casts = [
        'is_active' => 'boolean',
        'visible' => 'boolean',
    ];

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
}
