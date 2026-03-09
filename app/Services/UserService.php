<?php

namespace App\Services;

use App\Admin\Traits\SearchableQuery;
use App\DTOs\UserData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserService
{
    use SearchableQuery;

    public function list(Request $request): LengthAwarePaginator
    {
        $query = User::query()
            ->with('roles')
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

        return $this->applySearchAndPaginate(
            $query,
            $request,
            searchable: ['name', 'email'],
        );
    }

    public function create(UserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'phone' => $data->phone,
            'avatar' => $data->avatar,
            'status' => $data->status,
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
            $user->syncRoles([$data->role]);
        }

        return $user->fresh();
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
