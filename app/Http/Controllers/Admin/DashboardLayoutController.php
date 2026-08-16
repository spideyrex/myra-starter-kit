<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Dashboard\DashboardKey;
use App\Admin\Dashboard\LayoutShape;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Admin\Dashboard\WidgetInstance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardLayoutRequest;
use App\Models\DashboardLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardLayoutController extends Controller
{
    public function update(DashboardLayoutRequest $request): RedirectResponse
    {
        Gate::authorize('create', DashboardLayout::class);

        $data = $request->validated();
        $user = $request->user();

        abort_unless(DashboardKey::allows($data['dashboard_key']), 404);

        $payload = LayoutShape::assert($data['payload'], 'payload');

        // Write-time rejection: an instance that cannot resolve NOW never persists.
        $payload['instances'] = array_values(array_filter(
            $payload['instances'],
            fn (array $i) => WidgetInstance::resolve($i, $user) !== null,
        ));

        // Ownership is assigned outside mass assignment: the row can never be
        // created unowned, whatever $fillable says.
        DB::transaction(function () use ($user, $data, $payload) {
            $layout = DashboardLayout::firstOrNew([
                'user_id' => $user->id,
                'dashboard_key' => $data['dashboard_key'],
            ]);

            $layout->user_id = $user->id;
            $layout->dashboard_key = $data['dashboard_key'];
            $layout->payload = $payload;
            $layout->team_id = $user->current_team_id;
            $layout->save();
        });

        return back()->with('success', __('dashboardLayout.saved'));
    }

    public function destroy(Request $request, string $dashboard): RedirectResponse
    {
        abort_unless(DashboardKey::allows($dashboard), 404);

        DashboardLayout::forUser($request->user())
            ->where('dashboard_key', $dashboard)
            ->delete();

        return back()->with('success', __('dashboardLayout.reset'));
    }

    public function catalogue(Request $request): JsonResponse
    {
        Gate::authorize('create', DashboardLayout::class);

        return response()->json(['widgets' => WidgetCatalogue::forUser($request->user())]);
    }
}
