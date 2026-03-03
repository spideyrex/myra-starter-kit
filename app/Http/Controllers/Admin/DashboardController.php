<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $pendingVerifications = User::whereNull('email_verified_at')->count();

        // Last month comparisons for trend
        $lastMonthTotal = User::where('created_at', '<', now()->startOfMonth())->count();
        $lastMonthNew = User::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        // Users by status
        $usersByStatus = User::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $usersByRole = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->get();

        $recentActivity = Activity::with('causer')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'description' => $a->description,
                'causer' => $a->causer?->name ?? 'System',
                'causer_avatar' => $a->causer?->avatar,
                'subject' => $a->subject_type ? class_basename($a->subject_type) : null,
                'created_at' => $a->created_at->toISOString(),
            ]);

        $recentUsers = User::with('roles')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar' => $u->avatar,
                'status' => $u->status,
                'roles' => $u->roles->pluck('name')->toArray(),
                'created_at' => $u->created_at->toISOString(),
            ]);

        $userGrowth = User::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month')
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalUsers' => $totalUsers,
                'activeUsers' => $activeUsers,
                'newUsersThisMonth' => $newUsersThisMonth,
                'pendingVerifications' => $pendingVerifications,
                'lastMonthTotal' => $lastMonthTotal,
                'lastMonthNew' => $lastMonthNew,
            ],
            'usersByRole' => $usersByRole,
            'usersByStatus' => $usersByStatus,
            'recentActivity' => $recentActivity,
            'recentUsers' => $recentUsers,
            'userGrowth' => $userGrowth,
        ]);
    }
}
