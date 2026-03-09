<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use App\Services\ArticleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function __construct(private readonly ArticleService $articleService) {}

    public function index(Request $request): Response
    {
        $articles = $this->articleService->list($request);

        return Inertia::render('Admin/Articles/Index', [
            'articles' => ArticleResource::collection($articles),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'is_public', 'category_id', 'sort', 'direction', 'trashed']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Articles/Edit', [
            'article' => null,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:articles',
            'body_html' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'meta' => 'nullable|array',
            'status' => 'required|in:draft,published,archived',
            'is_public' => 'boolean',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'featured_image' => 'nullable|image|max:2048',
        ]);

        $validated['created_by'] = Auth::id();

        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        unset($validated['featured_image']);
        $article = $this->articleService->create($validated);

        if ($request->hasFile('featured_image')) {
            $article->addMediaFromRequest('featured_image')->toMediaCollection('featured_image');
        }

        return redirect()->route('admin.articles.index')->with('success', 'Article created successfully.');
    }

    public function edit(Article $article): Response
    {
        $article->load(['creator', 'category']);

        return Inertia::render('Admin/Articles/Edit', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'body_html' => $article->body_html,
                'excerpt' => $article->excerpt,
                'meta' => $article->meta ?? ['meta_title' => '', 'meta_description' => '', 'meta_keywords' => ''],
                'status' => $article->status,
                'is_public' => $article->is_public,
                'published_at' => $article->published_at?->format('Y-m-d\TH:i'),
                'tags' => $article->tags ?? [],
                'category_id' => $article->category_id,
                'featured_image_url' => $article->getFirstMediaUrl('featured_image') ?: null,
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:articles,slug,' . $article->id,
            'body_html' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'meta' => 'nullable|array',
            'status' => 'required|in:draft,published,archived',
            'is_public' => 'boolean',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'featured_image' => 'nullable|image|max:2048',
            'remove_featured_image' => 'nullable|boolean',
        ]);

        $validated['updated_by'] = Auth::id();

        if ($validated['status'] === 'published' && empty($validated['published_at']) && !$article->published_at) {
            $validated['published_at'] = now();
        }

        unset($validated['featured_image'], $validated['remove_featured_image']);
        $this->articleService->update($article, $validated);

        if ($request->hasFile('featured_image')) {
            $article->addMediaFromRequest('featured_image')->toMediaCollection('featured_image');
        } elseif ($request->boolean('remove_featured_image')) {
            $article->clearMediaCollection('featured_image');
        }

        return redirect()->route('admin.articles.index')->with('success', 'Article updated successfully.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->articleService->delete($article);

        return redirect()->route('admin.articles.index')->with('success', 'Article deleted successfully.');
    }

    public function restore(int $id): RedirectResponse
    {
        $this->articleService->restore($id);

        return back()->with('success', 'Article restored successfully.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $this->articleService->forceDelete($id);

        return back()->with('success', 'Article permanently deleted.');
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
                    'restore' => $this->articleService->restore($id),
                    'force_delete' => $this->articleService->forceDelete($id),
                };
            }
        } else {
            $articles = Article::whereIn('id', $request->ids)->get();
            foreach ($articles as $article) {
                match ($request->action) {
                    'publish' => $article->update(['status' => 'published', 'published_at' => $article->published_at ?? now()]),
                    'archive' => $article->update(['status' => 'archived']),
                    'delete' => $this->articleService->delete($article),
                };
            }
        }

        return back()->with('success', 'Bulk action completed successfully.');
    }
}
