<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Traits\SearchableQuery;
use App\Http\Controllers\Controller;
use App\Models\MyraCourse;
use App\Models\TableView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MyraCourseController extends Controller
{
    use SearchableQuery;

    public const TABLE_KEY = 'learning-courses';

    public function index(Request $request): Response
    {
        Gate::authorize('demo.view');

        $this->seedDemoData();

        $user = $request->user();

        return Inertia::render('Admin/Learning/Courses/Index', [
            'courses' => $this->applySearchAndPaginate(
                MyraCourse::query()->withCount('lessons'),
                $request,
                searchable: ['title'],
                defaultSort: 'created_at',
                defaultDir: 'asc',
                sortable: ['id', 'title', 'created_at'],
            ),
            'filters' => $request->only(['search', 'sort', 'direction']),
            'savedViews' => TableView::visibleTo($user)
                ->where('table_key', self::TABLE_KEY)
                ->where('name', '!=', TableView::COLUMNS_NAME)
                ->orderBy('sort')
                ->orderBy('name')
                ->get()
                ->map(fn (TableView $view) => $view->toClientArray($user))
                ->values(),
        ]);
    }

    /** The demo must never be an empty screen; rows are per-owner and created once. */
    private function seedDemoData(): void
    {
        if (MyraCourse::query()->exists()) {
            return;
        }

        foreach (['Getting Started', 'Advanced Patterns'] as $title) {
            $course = MyraCourse::query()->firstOrCreate(
                ['title' => $title],
                ['summary' => 'Sample course generated for the clusters demo.'],
            );

            if ($course->lessons()->exists()) {
                continue;
            }

            foreach ([1, 2, 3] as $position) {
                $course->lessons()->create([
                    'title' => "{$title} — lesson {$position}",
                    'position' => $position,
                ]);
            }
        }
    }
}
