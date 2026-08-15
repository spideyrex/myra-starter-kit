<?php

namespace App\Services;

use App\Admin\Traits\SearchableQuery;
use App\DTOs\UserData;
use App\Models\Role;
use App\Models\User;
use App\Support\Sql;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserService
{
    use SearchableQuery;

    /** Columns the listing search spans. Mirrored by exportQuery(withSearch: true). */
    private const SEARCHABLE = ['name', 'email'];

    /**
     * Ownership scoping + every filter, without pagination. The export path must
     * reuse this: a hand-rolled export query is how a listing scope gets bypassed.
     *
     * `$withSearch` exists because list() delegates the search clause to
     * applySearchAndPaginate() (owned elsewhere); the export has no paginator to
     * delegate to. C3 unifies the two.
     */
    public function exportQuery(Request $request, bool $withSearch = false): Builder
    {
        $current = Auth::user();

        $query = User::query()
            ->with('roles')
            // Non-super-admins only see users they created (and never super-admins).
            ->when(! $this->isSuperAdmin($current), fn ($q) => $q
                ->where('created_by', $current?->id)
                ->whereDoesntHave('roles', fn ($r) => $r->where('name', config('shield.super_admin_role', 'super-admin'))))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->role, function ($q, $role) {
                $q->whereHas('roles', fn ($q) => $q->where('name', $role));
            });

        // Handle trashed filter
        if ($request->trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($request->trashed === 'with') {
            $query->withTrashed();
        }

        if ($withSearch && $request->search) {
            $query->where(function ($q) use ($request) {
                foreach (self::SEARCHABLE as $column) {
                    Sql::orWhereLike($q, $column, (string) $request->search);
                }
            });
        }

        return $query;
    }

    public function list(Request $request): LengthAwarePaginator
    {
        return $this->applySearchAndPaginate(
            $this->exportQuery($request),
            $request,
            searchable: self::SEARCHABLE,
        );
    }

    public function create(UserData $data): User
    {
        $this->assertAssignable($data->role);

        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'phone' => $data->phone,
            'avatar' => $data->avatar,
            'status' => $data->status,
            'created_by' => Auth::id(),
        ]);

        if ($data->role) {
            $user->assignRole($data->role);
        }

        return $user;
    }

    public function update(User $user, UserData $data): User
    {
        $updateData = [
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'status' => $data->status,
        ];

        if ($data->password) {
            $updateData['password'] = Hash::make($data->password);
        }

        if ($data->avatar) {
            $updateData['avatar'] = $data->avatar;
        }

        $user->update($updateData);

        if ($data->role) {
            $this->assertAssignable($data->role);
            $user->syncRoles([$data->role]);
        }

        return $user->fresh();
    }

    private function isSuperAdmin(?User $user): bool
    {
        return $user?->hasRole(config('shield.super_admin_role', 'super-admin')) ?? false;
    }

    /** Guard role assignment: disabled roles are unassignable; privileged roles need a super-admin. */
    private function assertAssignable(?string $roleName): void
    {
        if (! $roleName) {
            return;
        }

        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            return;
        }

        abort_if(! $role->is_active, 422, "The \"{$roleName}\" role is disabled and cannot be assigned.");

        if ($role->isPrivileged() && ! $this->isSuperAdmin(Auth::user())) {
            abort(403, "Only a super-admin can assign the \"{$roleName}\" role.");
        }
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function suspend(User $user): void
    {
        $user->update(['status' => 'suspended']);
    }

    public function activate(User $user): void
    {
        $user->update(['status' => 'active']);
    }

    public function restore(int $id): void
    {
        User::withTrashed()->findOrFail($id)->restore();
    }

    public function forceDelete(int $id): void
    {
        User::withTrashed()->findOrFail($id)->forceDelete();
    }
}
