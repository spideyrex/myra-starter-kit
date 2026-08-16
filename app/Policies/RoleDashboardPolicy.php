<?php

namespace App\Policies;

use App\Models\User;

/** Authoring a dashboard rendered for other people is its own ability. */
class RoleDashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dashboard.manage-roles');
    }

    public function manage(User $user): bool
    {
        return $user->can('dashboard.manage-roles');
    }

    public function delete(User $user): bool
    {
        return $user->can('dashboard.manage-roles');
    }
}
