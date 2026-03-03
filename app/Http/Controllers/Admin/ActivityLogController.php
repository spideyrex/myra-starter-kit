<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

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
}
