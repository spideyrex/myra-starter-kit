<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $articles = Article::query()
            ->publiclyVisible()
            ->with(['creator', 'category', 'media'])
            ->when($request->category, fn ($q, $slug) => $q->whereHas('category', fn ($q) => $q->where('slug', $slug)))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $articles->getCollection()->transform(fn ($article) => [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'published_at' => $article->published_at?->toISOString(),
            'category' => $article->category ? ['name' => $article->category->name, 'slug' => $article->category->slug] : null,
            'author' => $article->creator ? ['name' => $article->creator->name] : null,
            'featured_image_url' => $article->getFirstMediaUrl('featured_image') ?: null,
        ]);

        return Inertia::render('Public/ArticleIndex', [
            'articles' => $articles,
            'categories' => Category::withCount(['articles' => fn ($q) => $q->publiclyVisible()])->orderBy('name')->get(['id', 'name', 'slug']),
            'currentCategory' => $request->category,
            'authenticated' => Auth::check(),
        ]);
    }

    public function show(string $slug): Response|HttpResponse
    {
        $article = Article::where('slug', $slug)->published()->with(['creator', 'category', 'media'])->firstOrFail();

        if (!$article->is_public && !Auth::check()) {
            return redirect()->route('login');
        }

        $relatedArticles = Article::query()
            ->publiclyVisible()
            ->where('id', '!=', $article->id)
            ->when($article->category_id, fn ($q) => $q->where('category_id', $article->category_id))
            ->with(['category', 'media'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn ($related) => [
                'id' => $related->id,
                'title' => $related->title,
                'slug' => $related->slug,
                'excerpt' => $related->excerpt,
                'published_at' => $related->published_at?->toISOString(),
                'category' => $related->category ? ['name' => $related->category->name, 'slug' => $related->category->slug] : null,
                'featured_image_url' => $related->getFirstMediaUrl('featured_image') ?: null,
            ]);

        return Inertia::render('Public/ArticleShow', [
            'article' => [
                'title' => $article->title,
                'body_html' => $article->body_html,
                'excerpt' => $article->excerpt,
                'meta' => $article->meta,
                'published_at' => $article->published_at?->toISOString(),
                'tags' => $article->tags ?? [],
                'category' => $article->category ? ['name' => $article->category->name, 'slug' => $article->category->slug] : null,
                'author' => $article->creator ? ['name' => $article->creator->name, 'avatar' => $article->creator->avatar] : null,
                'featured_image_url' => $article->getFirstMediaUrl('featured_image') ?: null,
            ],
            'relatedArticles' => $relatedArticles,
            'authenticated' => Auth::check(),
        ]);
    }
}
