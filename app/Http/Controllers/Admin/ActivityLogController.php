<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Export\ExportColumn;
use App\Admin\Export\ExportDefinition;
use App\Admin\Traits\ExportableQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    use ExportableQuery;

    /** One filter definition shared by the listing and the export. */
    private function baseQuery(Request $request): Builder
    {
        return Activity::query()
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('subject_type', 'like', "%{$search}%");
                });
            })
            ->when($request->log_name, fn ($q, $name) => $q->where('log_name', $name));
    }

    public function index(Request $request): Response
    {
        $activities = $this->baseQuery($request)
            ->with('causer')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/ActivityLog/Index', [
            'activities' => ActivityResource::collection($activities),
            'filters' => $request->only(['search', 'log_name']),
            'logNames' => Activity::distinct()->pluck('log_name')->sort()->values(),
        ]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        Activity::whereIn('id', $request->ids)->delete();

        return back()->with('success', 'Selected activity logs deleted.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        Gate::authorize('activity-log.view');

        return $this->streamExport(
            $this->baseQuery($request),
            ExportDefinition::make('activity-log')
                ->columns([
                    ExportColumn::make('id')->label('ID'),
                    ExportColumn::make('description')->label('Description'),
                    ExportColumn::make('causer')->label('User')
                        ->state(fn (Activity $a) => $a->causer?->name ?? 'System'),
                    ExportColumn::make('subject_type')->label('Subject'),
                    ExportColumn::make('event')->label('Event'),
                    ExportColumn::make('created_at')->label('Date')->date('Y-m-d H:i:s'),
                ])
                ->eagerLoad(['causer'])
                ->formats(['csv', 'xlsx'])
                ->maxRows((int) config('myra.exports.max_rows', 50000))
                ->filename('activity-log'),
            $request,
        );
    }
}
