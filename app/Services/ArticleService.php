<?php

namespace App\Services;

use App\Admin\Traits\SearchableQuery;
use App\Models\Article;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ArticleService
{
    use SearchableQuery;

    public function list(Request $request): LengthAwarePaginator
    {
        $query = Article::query()
            ->with(['creator', 'category'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->category_id, fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->has('is_public') && $request->is_public !== null && $request->is_public !== '', fn ($q) => $q->where('is_public', $request->boolean('is_public')));

        if ($request->trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($request->trashed === 'with') {
            $query->withTrashed();
        }

        return $this->applySearchAndPaginate(
            $query,
            $request,
            searchable: ['title', 'slug', 'excerpt'],
        );
    }

    public function create(array $data): Article
    {
        return Article::create($data);
    }

    public function update(Article $article, array $data): Article
    {
        $article->update($data);

        return $article->fresh();
    }

    public function delete(Article $article): void
    {
        $article->delete();
    }

    public function restore(int $id): void
    {
        Article::withTrashed()->findOrFail($id)->restore();
    }

    public function forceDelete(int $id): void
    {
        $article = Article::withTrashed()->findOrFail($id);
        $article->clearMediaCollection('featured_image');
        $article->forceDelete();
    }
}
