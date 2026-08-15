<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Export\ExportColumn;
use App\Admin\Export\ExportDefinition;
use App\Admin\QueryBuilder\FieldSet;
use App\Admin\QueryBuilder\FieldSpec;
use App\Admin\Traits\ExportableQuery;
use App\Admin\Traits\HandlesSoftDeletes;
use App\DTOs\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    use ExportableQuery;
    use HandlesSoftDeletes;

    private const MODULE = 'users';

    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => UserResource::collection($this->userService->list($request)),
            'roles' => $this->assignableRoleNames(),
            'filters' => $request->only(['search', 'status', 'role', 'sort', 'direction', 'trashed']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->assignableRoleNames(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->userService->create(UserData::fromRequest($request->validated()));

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        $this->authorizeManage($user);
        $user->load('roles');

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $user->roles->pluck('name')->toArray(),
            ],
            'roles' => $this->assignableRoleNames(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorizeManage($user);
        $this->userService->update($user, UserData::fromRequest($request->validated()));

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeManage($user);
        $this->userService->delete($user);

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    public function restore(int $id): RedirectResponse
    {
        $this->authorizeAbility(self::MODULE . '.edit');
        $this->authorizeManage(User::withTrashed()->findOrFail($id));
        $this->userService->restore($id);

        return back()->with('success', 'User restored successfully.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $this->authorizeAbility(self::MODULE . '.delete');
        $this->authorizeManage(User::withTrashed()->findOrFail($id));
        $this->userService->forceDelete($id);

        return back()->with('success', 'User permanently deleted.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:delete,suspend,activate,restore,force_delete',
        ]);

        // The route is gated on users.edit; destructive verbs need their own ability.
        $this->authorizeBulkVerb($request->action, self::MODULE);

        $trashedVerb = in_array($request->action, ['restore', 'force_delete'], true);

        // Bulk must honour the same per-user guard as the single-record path.
        $users = User::query()
            ->when($trashedVerb, fn ($q) => $q->withTrashed())
            ->whereIn('id', $request->ids)
            ->get();

        foreach ($users as $user) {
            $this->authorizeManage($user);
        }

        foreach ($users as $user) {
            match ($request->action) {
                'restore' => $this->userService->restore($user->id),
                'force_delete' => $this->userService->forceDelete($user->id),
                'delete' => $this->userService->delete($user),
                'suspend' => $this->userService->suspend($user),
                'activate' => $this->userService->activate($user),
            };
        }

        return back()->with('success', 'Bulk action completed successfully.');
    }

    /**
     * Streams the SAME scoped query the listing uses. The pre-v2.2 version built
     * its own `User::with('roles')->get()`, which bypassed UserService's
     * created_by ownership scope and super-admin exclusion entirely.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize(self::MODULE . '.view');

        return $this->streamExport(
            $this->userService->exportQuery($request, withSearch: true),
            ExportDefinition::make('users')
                ->columns([
                    ExportColumn::make('id')->label('ID'),
                    ExportColumn::make('name')->label('Name'),
                    ExportColumn::make('email')->label('Email'),
                    ExportColumn::make('phone')->label('Phone'),
                    ExportColumn::make('status')->label('Status'),
                    ExportColumn::make('roles')->label('Roles')
                        ->state(fn (User $u) => $u->roles->pluck('name')->join(', ')),
                    ExportColumn::make('created_at')->label('Created At')->date('Y-m-d H:i'),
                ])
                ->eagerLoad(['roles'])
                ->formats(['csv', 'xlsx'])
                ->maxRows((int) config('myra.exports.max_rows', 50000))
                ->filename('users'),
            $request,
        );
    }

    public function impersonate(User $user): RedirectResponse
    {
        $currentUser = Auth::user();

        // Prevent impersonating super-admins (privilege escalation)
        abort_if(
            $user->hasRole('super-admin'),
            403,
            'Cannot impersonate a super-admin user.'
        );

        // Only super-admins can impersonate (not just users.edit permission)
        abort_unless(
            $currentUser->hasRole('super-admin'),
            403,
            'Only super-admins can impersonate other users.'
        );

        // Cannot impersonate yourself
        abort_if(
            $currentUser->id === $user->id,
            403,
            'Cannot impersonate yourself.'
        );

        // Log the impersonation for audit trail
        activity()
            ->causedBy($currentUser)
            ->performedOn($user)
            ->withProperties(['impersonator_id' => $currentUser->id])
            ->log("Started impersonating {$user->name}");

        session()->put('impersonator_id', $currentUser->id);

        // Regenerate session ID to prevent session fixation
        session()->regenerate();

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', "You are now impersonating {$user->name}.");
    }

    public function stopImpersonate(): RedirectResponse
    {
        $originalId = session()->pull('impersonator_id');

        if ($originalId) {
            // Verify the original user still exists and is active
            $originalUser = User::find($originalId);

            abort_unless(
                $originalUser && $originalUser->status === 'active',
                403,
                'Original user account is no longer available.'
            );

            // Log stop impersonation
            activity()
                ->causedBy($originalUser)
                ->log('Stopped impersonating ' . Auth::user()->name);

            // Regenerate session to prevent session fixation
            session()->regenerate();

            Auth::login($originalUser);
        }

        return redirect()->route('admin.users.index')->with('success', 'Stopped impersonating.');
    }

    // >>> MYRA v2.3 [B] START
    /**
     * The server-side field whitelist for user queries. One declaration, two
     * consumers: the users report's filters and any drill-through that lands
     * back on this index.
     */
    public static function filterFieldSet(): FieldSet
    {
        return FieldSet::make([
            FieldSpec::text('name')->labelKey('filters.field.name')->contains(),
            FieldSpec::text('email')->labelKey('filters.field.email')->contains(),
            FieldSpec::text('phone')->labelKey('filters.field.phone')->nullable(),
            FieldSpec::select('status')->labelKey('filters.field.status')->options(['active', 'suspended', 'pending']),
            FieldSpec::date('created_at')->labelKey('filters.field.createdAt'),
            FieldSpec::date('email_verified_at')->labelKey('filters.field.verifiedAt')->nullable(),
            FieldSpec::relation('roles')->labelKey('filters.field.roles')->relationship('roles', 'name'),
        ])->maxRules((int) config('myra.filters.max_rules', 25))
            ->maxDepth((int) config('myra.filters.max_depth', 3));
    }
    // <<< MYRA v2.3 [B] END

    /** Role names the current user is allowed to assign / filter by. */
    private function assignableRoleNames(): \Illuminate\Support\Collection
    {
        $isSuper = Auth::user()?->hasRole(config('shield.super_admin_role', 'super-admin'));

        return Role::query()
            ->when(! $isSuper, fn ($q) => $q
                ->where('is_active', true)
                ->where('visible', true)
                ->whereNotIn('name', config('shield.privileged_roles', ['super-admin', 'admin'])))
            ->orderBy('name')
            ->pluck('name');
    }

    /**
     * Guard direct management of a user: super-admins are unrestricted; everyone
     * else cannot touch super-admins, an admin cannot manage another admin, and
     * non-super-admins may only manage users they created.
     */
    private function authorizeManage(User $target): void
    {
        $current = Auth::user();

        if ($current->hasRole(config('shield.super_admin_role', 'super-admin'))) {
            return;
        }

        abort_if($target->hasRole(config('shield.super_admin_role', 'super-admin')), 403, 'You cannot manage a super-admin account.');

        $adminRole = config('shield.admin_role', 'admin');
        if ($target->id !== $current->id && $target->hasRole($adminRole) && $current->hasRole($adminRole)) {
            abort(403, 'Admins cannot manage other admins.');
        }

        abort_unless($target->created_by === $current->id || $target->id === $current->id, 403, 'You can only manage users you created.');
    }
}
