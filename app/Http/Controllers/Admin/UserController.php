<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => UserResource::collection($this->userService->list($request)),
            'roles' => Role::pluck('name'),
            'filters' => $request->only(['search', 'status', 'role', 'sort', 'direction', 'trashed']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::pluck('name'),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->userService->create(UserData::fromRequest($request->validated()));

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
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
            'roles' => Role::pluck('name'),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, UserData::fromRequest($request->validated()));

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->userService->delete($user);

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    public function restore(int $id): RedirectResponse
    {
        $this->userService->restore($id);

        return back()->with('success', 'User restored successfully.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
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

        if (in_array($request->action, ['restore', 'force_delete'])) {
            foreach ($request->ids as $id) {
                match ($request->action) {
                    'restore' => $this->userService->restore($id),
                    'force_delete' => $this->userService->forceDelete($id),
                };
            }
        } else {
            $users = User::whereIn('id', $request->ids)->get();
            foreach ($users as $user) {
                match ($request->action) {
                    'delete' => $this->userService->delete($user),
                    'suspend' => $this->userService->suspend($user),
                    'activate' => $this->userService->activate($user),
                };
            }
        }

        return back()->with('success', 'Bulk action completed successfully.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $users = User::with('roles')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->get();

        return response()->streamDownload(function () use ($users) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Email', 'Phone', 'Status', 'Roles', 'Created At']);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone,
                    $user->status,
                    $user->roles->pluck('name')->join(', '),
                    $user->created_at->toDateTimeString(),
                ]);
            }

            fclose($file);
        }, 'users.csv');
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
}
