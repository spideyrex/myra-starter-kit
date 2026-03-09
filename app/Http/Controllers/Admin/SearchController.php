<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];
        $user = $request->user();

        // Search Users
        if ($user->can('users.view')) {
            $users = User::where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name', 'email'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'title' => $u->name,
                    'description' => $u->email,
                    'url' => route('admin.users.edit', $u->id),
                ]);

            if ($users->isNotEmpty()) {
                $results[] = [
                    'group' => 'Users',
                    'items' => $users->toArray(),
                ];
            }
        }

        // Search Roles
        if ($user->can('roles.view')) {
            $roles = Role::where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'name'])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->name,
                    'description' => 'Role',
                    'url' => route('admin.roles.edit', $r->id),
                ]);

            if ($roles->isNotEmpty()) {
                $results[] = [
                    'group' => 'Roles',
                    'items' => $roles->toArray(),
                ];
            }
        }

        // Search Activity Log
        if ($user->can('activity-log.view')) {
            $activities = Activity::where('description', 'like', "%{$query}%")
                ->orWhere('subject_type', 'like', "%{$query}%")
                ->latest()
                ->limit(5)
                ->get(['id', 'description', 'created_at'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->description,
                    'description' => $a->created_at->diffForHumans(),
                    'url' => route('admin.activity-logs.index', ['search' => $query]),
                ]);

            if ($activities->isNotEmpty()) {
                $results[] = [
                    'group' => 'Activity Log',
                    'items' => $activities->toArray(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }
}
