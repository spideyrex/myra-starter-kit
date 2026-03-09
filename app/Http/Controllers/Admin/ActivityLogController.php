<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $activities = Activity::query()
            ->with('causer')
            ->when($request->search, function ($q, $search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%");
            })
            ->when($request->log_name, fn ($q, $name) => $q->where('log_name', $name))
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
        $activities = Activity::query()
            ->with('causer')
            ->when($request->search, function ($q, $search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%");
            })
            ->when($request->log_name, fn ($q, $name) => $q->where('log_name', $name))
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($activities) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Description', 'User', 'Subject', 'Event', 'Date']);

            foreach ($activities as $activity) {
                fputcsv($file, [
                    $activity->id,
                    $activity->description,
                    $activity->causer?->name ?? 'System',
                    $activity->subject_type,
                    $activity->event,
                    $activity->created_at->toDateTimeString(),
                ]);
            }

            fclose($file);
        }, 'activity-log.csv');
    }
}
