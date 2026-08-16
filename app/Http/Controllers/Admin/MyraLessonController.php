<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resource\ParentResource;
use App\Admin\Traits\ResolvesParentResource;
use App\Admin\Traits\SearchableQuery;
use App\Http\Controllers\Controller;
use App\Models\MyraCourse;
use App\Models\MyraLesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyraLessonController extends Controller
{
    use ResolvesParentResource, SearchableQuery;

    protected function parentResource(): ParentResource
    {
        return ParentResource::make('learning.courses')
            ->model(MyraCourse::class)
            ->relationship('lessons')
            ->inverse('course')
            ->foreignKey('course_id')
            ->permission('demo.view')
            ->titleAttribute('title');
    }

    public function index(Request $request, MyraCourse $course): Response
    {
        $parent = $this->resolveParent($course);

        return Inertia::render('Admin/Learning/Lessons/Index', [
            'parent' => $this->parentPayload($parent),
            'crumbs' => $this->parentBreadcrumbs($parent, 'clusters.learning.lessons.label'),
            'storeUrl' => $this->childRoute($parent, 'store'),
            'lessons' => $this->applySearchAndPaginate(
                $this->childQuery($parent),
                $request,
                searchable: ['title'],
                defaultSort: 'position',
                defaultDir: 'asc',
                sortable: ['id', 'title', 'position', 'created_at'],
            ),
            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    public function store(Request $request, MyraCourse $course): RedirectResponse
    {
        $parent = $this->resolveParent($course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        // A blank Position arrives as null (ConvertEmptyStringsToNull) and the
        // column is NOT NULL; drop the key so the column default applies.
        if (($validated['position'] ?? null) === null) {
            unset($validated['position']);
        }

        // Through the relation: course_id comes from the URL, so a course_id in
        // the request body cannot move the row to another parent.
        $this->childRelation($parent)->create($validated);

        return back()->with('success', 'Lesson created successfully.');
    }

    public function destroy(MyraCourse $course, MyraLesson $lesson): RedirectResponse
    {
        $this->resolveParent($course);

        // scopeBindings() already proved the lesson belongs to this course.
        $lesson->delete();

        return back()->with('success', 'Lesson deleted successfully.');
    }
}
