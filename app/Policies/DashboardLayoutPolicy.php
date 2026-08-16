<?php

namespace App\Policies;

use App\Models\DashboardLayout;
use App\Models\User;

class DashboardLayoutPolicy
{
    public function view(User $user, DashboardLayout $layout): bool
    {
        return $layout->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can('dashboard.customise');
    }

    public function update(User $user, DashboardLayout $layout): bool
    {
        return $layout->isOwnedBy($user);
    }

    public function delete(User $user, DashboardLayout $layout): bool
    {
        return $layout->isOwnedBy($user);
    }
}
