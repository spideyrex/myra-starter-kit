<?php

namespace App\Policies;

use App\Models\TableView;
use App\Models\User;

class TableViewPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Owner, or a teammate of a team-visible view. */
    public function view(User $user, TableView $tableView): bool
    {
        return $tableView->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Team views are readable by teammates and writable by nobody but their author. */
    public function update(User $user, TableView $tableView): bool
    {
        return $tableView->isOwnedBy($user);
    }

    public function delete(User $user, TableView $tableView): bool
    {
        return $tableView->isOwnedBy($user);
    }
}
