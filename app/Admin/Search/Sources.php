<?php

namespace App\Admin\Search;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/** The shipped search sources. */
final class Sources
{
    public static function register(GlobalSearch $search): void
    {
        $search->register(
            SearchSource::make('users')
                ->model(User::class)
                ->permission('users.view')
                ->groupLabelKey('search.groups.users')
                ->icon('Users')
                ->attributes(['name' => 3.0, 'email' => 2.0, 'phone' => 0.5])
                // Ownership scope INLINED on purpose: UserService is being
                // refactored in the same window. Unify in C3.
                ->scope(fn (Builder $q, $actor) => $q->when(
                    ! ($actor?->hasRole(config('shield.super_admin_role', 'super-admin')) ?? false),
                    fn ($q) => $q->where('created_by', $actor?->getAuthIdentifier()),
                ))
                ->titleUsing(fn (User $u) => (string) $u->name)
                ->descriptionUsing(fn (User $u) => (string) $u->email)
                ->urlUsing(fn (User $u) => route('admin.users.edit', $u))
                ->eagerLoad(['roles'])
                ->recency('updated_at', 0.15)
                ->limit(8)
                ->sort(10),

            SearchSource::make('roles')
                ->model(Role::class)
                ->permission('roles.view')
                ->groupLabelKey('search.groups.roles')
                ->icon('Shield')
                ->attributes(['name' => 3.0])
                ->titleUsing(fn (Role $r) => (string) $r->name)
                ->descriptionUsing(fn (Role $r) => (string) $r->guard_name)
                ->urlUsing(fn (Role $r) => route('admin.roles.edit', $r))
                ->recency('updated_at', 0.1)
                ->limit(5)
                ->sort(5),

            SearchSource::make('activity')
                ->model(Activity::class)
                ->permission('activity-log.view')
                ->groupLabelKey('search.groups.activity')
                ->icon('Activity')
                ->attributes(['description' => 2.0, 'subject_type' => 0.5])
                ->contains()
                ->titleUsing(fn (Activity $a) => (string) $a->description)
                ->descriptionUsing(fn (Activity $a) => (string) ($a->subject_type ?? ''))
                ->urlUsing(fn (Activity $a) => route('admin.activity-logs.index', ['search' => $a->description]))
                ->recency('created_at', 0.2)
                ->limit(5)
                ->sort(1),
        );

        $search->pages([
            ['title' => 'Dashboard', 'labelKey' => 'nav.dashboard', 'url' => route('dashboard'), 'permission' => null, 'icon' => 'LayoutDashboard'],
            ['title' => 'Users', 'labelKey' => 'nav.users', 'url' => route('admin.users.index'), 'permission' => 'users.view', 'icon' => 'Users'],
            ['title' => 'Roles & Permissions', 'labelKey' => 'nav.roles', 'url' => route('admin.roles.index'), 'permission' => 'roles.view', 'icon' => 'Shield'],
            ['title' => 'General Settings', 'labelKey' => 'nav.generalSettings', 'url' => route('admin.settings.index'), 'permission' => 'settings.view', 'icon' => 'Settings'],
            ['title' => 'Activity Log', 'labelKey' => 'nav.activityLog', 'url' => route('admin.activity-logs.index'), 'permission' => 'activity-log.view', 'icon' => 'Activity'],
            ['title' => 'Media Manager', 'labelKey' => 'nav.media', 'url' => route('admin.media.index'), 'permission' => 'media.view', 'icon' => 'Image'],
        ]);
    }
}
