<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Dashboard\DashboardKey;
use App\Admin\Dashboard\LayoutResolver;
use App\Admin\Dashboard\LayoutSource;
use App\Admin\Dashboard\ResolvedLayout;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(Request $request): Response
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

        // Group registrations by month — driver-agnostic so the dashboard works
        // on MySQL, PostgreSQL, and SQLite alike.
        $monthExpr = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $userGrowth = User::selectRaw("{$monthExpr} as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw($monthExpr)
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

            // >>> MYRA v2.5 [A] START
            // Lazy and fail-soft. With no saved row, an empty catalogue and the
            // editable flag off, these are null / [] / false and the grid renders
            // byte-identically to v2.4.0.
            // >>> MYRA v2.7 [A] START
            // The ONE seam: the row chooser becomes the resolution chain
            // (personal → highest-priority role default → nothing). With
            // role_dashboards empty this returns exactly what v2.6 returned.
            'dashboardLayout' => fn () => $this->safely(
                fn () => LayoutResolver::fromPayload($this->resolvedLayout($request)->payload, $request->user()),
            ),
            'dashboardLayoutSource' => fn () => $this->safely(
                fn () => $this->resolvedLayout($request)->toInertia(),
                ['source' => 'none', 'role' => null, 'hasRoleDefault' => false],
            ),
            // <<< MYRA v2.7 [A] END
            'dashboardCatalogue' => fn () => $this->safely(
                fn () => WidgetCatalogue::forUser($request->user()),
                [],
            ),
            'canCustomiseDashboard' => fn () => (bool) (
                config('myra.dashboard.editable') === true
                && $request->user()?->can('dashboard.customise')
            ),
            // <<< MYRA v2.5 [A] END
        ]);
    }

    // >>> MYRA v2.7 [A] START
    /** Memoised per request so the two lazy props share one execution. */
    private function resolvedLayout(Request $request): ResolvedLayout
    {
        return once(fn () => LayoutSource::resolve($request->user(), DashboardKey::MAIN));
    }
    // <<< MYRA v2.7 [A] END

    // >>> MYRA v2.5 [A] START
    /** A missing table or a corrupt row must never white-screen the dashboard. */
    private function safely(callable $fn, mixed $fallback = null): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);

            return $fallback;
        }
    }
    // <<< MYRA v2.5 [A] END
}
