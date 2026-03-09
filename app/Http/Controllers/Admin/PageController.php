<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(private readonly PageService $pageService) {}

    public function index(Request $request): Response
    {
        $pages = $this->pageService->list($request);

        return Inertia::render('Admin/Pages/Index', [
            'pages' => PageResource::collection($pages),
            'filters' => $request->only(['search', 'status', 'is_public', 'sort', 'direction', 'trashed']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Pages/Edit', [
            'page' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages',
            'body_html' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'meta' => 'nullable|array',
            'status' => 'required|in:draft,published,archived',
            'is_public' => 'boolean',
            'published_at' => 'nullable|date',
            'featured_image' => 'nullable|image|max:2048',
        ]);

        $validated['created_by'] = Auth::id();

        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        unset($validated['featured_image']);
        $page = $this->pageService->create($validated);

        if ($request->hasFile('featured_image')) {
            $page->addMediaFromRequest('featured_image')->toMediaCollection('featured_image');
        }

        return redirect()->route('admin.pages.index')->with('success', 'Page created successfully.');
    }

    public function edit(Page $page): Response
    {
        $page->load('creator');

        return Inertia::render('Admin/Pages/Edit', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'body_html' => $page->body_html,
                'excerpt' => $page->excerpt,
                'meta' => $page->meta ?? ['meta_title' => '', 'meta_description' => '', 'meta_keywords' => ''],
                'status' => $page->status,
                'is_public' => $page->is_public,
                'published_at' => $page->published_at?->format('Y-m-d\TH:i'),
                'featured_image_url' => $page->getFirstMediaUrl('featured_image') ?: null,
            ],
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug,' . $page->id,
            'body_html' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'meta' => 'nullable|array',
            'status' => 'required|in:draft,published,archived',
            'is_public' => 'boolean',
            'published_at' => 'nullable|date',
            'featured_image' => 'nullable|image|max:2048',
            'remove_featured_image' => 'nullable|boolean',
        ]);

        $validated['updated_by'] = Auth::id();

        if ($validated['status'] === 'published' && empty($validated['published_at']) && !$page->published_at) {
            $validated['published_at'] = now();
        }

        unset($validated['featured_image'], $validated['remove_featured_image']);
        $this->pageService->update($page, $validated);

        if ($request->hasFile('featured_image')) {
            $page->addMediaFromRequest('featured_image')->toMediaCollection('featured_image');
        } elseif ($request->boolean('remove_featured_image')) {
            $page->clearMediaCollection('featured_image');
        }

        return redirect()->route('admin.pages.index')->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->pageService->delete($page);

        return redirect()->route('admin.pages.index')->with('success', 'Page deleted successfully.');
    }

    public function restore(int $id): RedirectResponse
    {
        $this->pageService->restore($id);

        return back()->with('success', 'Page restored successfully.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $this->pageService->forceDelete($id);

        return back()->with('success', 'Page permanently deleted.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:publish,archive,delete,restore,force_delete',
        ]);

        if (in_array($request->action, ['restore', 'force_delete'])) {
            foreach ($request->ids as $id) {
                match ($request->action) {
                    'restore' => $this->pageService->restore($id),
                    'force_delete' => $this->pageService->forceDelete($id),
                };
            }
        } else {
            $pages = Page::whereIn('id', $request->ids)->get();
            foreach ($pages as $page) {
                match ($request->action) {
                    'publish' => $page->update(['status' => 'published', 'published_at' => $page->published_at ?? now()]),
                    'archive' => $page->update(['status' => 'archived']),
                    'delete' => $this->pageService->delete($page),
                };
            }
        }

        return back()->with('success', 'Bulk action completed successfully.');
    }
}
