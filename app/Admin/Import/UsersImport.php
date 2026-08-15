<?php

namespace App\Admin\Import;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class UsersImport
{
    public static function definition(): ImportDefinition
    {
        return ImportDefinition::make('users')
            ->model(User::class)
            ->permission('users.create')
            ->columns([
                ImportColumn::make('name')
                    ->label('Name')
                    ->requiredMapping()
                    ->guess(['full name', 'fullname', 'user name'])
                    ->rules(['required', 'string', 'max:255'])
                    ->example('Ada Lovelace'),

                ImportColumn::make('email')
                    ->label('Email')
                    ->requiredMapping()
                    ->guess(['e-mail', 'email address', 'mail'])
                    ->rules(['required', 'email', 'max:255'])
                    ->example('ada@example.com'),

                ImportColumn::make('password')
                    ->label('Password')
                    ->sensitive()
                    ->guess(['pass', 'pwd'])
                    ->rules(['nullable', 'string', 'min:8']),

                ImportColumn::make('phone')
                    ->label('Phone')
                    ->guess(['mobile', 'telephone', 'tel'])
                    ->rules(['nullable', 'string', 'max:32']),

                ImportColumn::make('status')
                    ->label('Status')
                    ->default('pending')
                    ->guess(['state'])
                    ->rules(['nullable', 'in:active,suspended,pending']),

                ImportColumn::make('role')
                    ->label('Role')
                    ->guess(['roles', 'group'])
                    ->rules(['nullable', 'string', 'max:255']),
            ])
            // Per-record authorization. An import must never be a side door around
            // the guards the single-record path applies.
            ->authorizeRow(function (array $row, ?Authenticatable $actor): bool {
                if (! $actor instanceof User || ! $actor->can('users.create')) {
                    return false;
                }

                $existing = User::where('email', $row['email'] ?? null)->first();

                if (! $existing) {
                    return true;
                }

                if (self::isSuperAdmin($actor)) {
                    return true;
                }

                // Non-super-admins may only overwrite users they created, and never a super-admin.
                return ! self::isSuperAdmin($existing) && $existing->created_by === $actor->id;
            })
            ->resolveRecord(function (array $row, ?Authenticatable $actor): Model {
                $user = User::firstOrNew(['email' => $row['email']]);

                $user->name = $row['name'];
                $user->phone = $row['phone'] ?? $user->phone;
                $user->status = in_array($row['status'] ?? '', ['active', 'suspended', 'pending'], true)
                    ? $row['status']
                    : ($user->status ?? 'pending');

                if (! $user->exists) {
                    $user->password = Hash::make($row['password'] ?: Str::random(24));
                    $user->created_by = $actor?->getAuthIdentifier();
                } elseif (! empty($row['password'])) {
                    $user->password = Hash::make($row['password']);
                }

                return $user;
            })
            ->afterSave(function (Model $record, array $row, ?Authenticatable $actor): void {
                if (empty($row['role'])) {
                    return;
                }

                $role = Role::where('name', trim((string) $row['role']))->first();

                // Only assign roles the importer is allowed to grant: must exist, be
                // active, and not be a privileged role unless the actor is super-admin.
                $isSuper = $actor instanceof User && self::isSuperAdmin($actor);
                $privileged = config('shield.privileged_roles', ['super-admin', 'admin']);

                if ($role && $role->is_active && ($isSuper || ! in_array($role->name, $privileged, true))) {
                    $record->assignRole($role->name);
                }
            });
    }

    private static function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(config('shield.super_admin_role', 'super-admin'));
    }
}
