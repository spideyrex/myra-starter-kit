<?php

namespace App\Services;

use App\Admin\Traits\SearchableQuery;
use App\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PageService
{
    use SearchableQuery;

    public function list(Request $request): LengthAwarePaginator
    {
        $query = Page::query()
            ->with('creator')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
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

    public function create(array $data): Page
    {
        return Page::create($data);
    }

    public function update(Page $page, array $data): Page
    {
        $page->update($data);

        return $page->fresh();
    }

    public function delete(Page $page): void
    {
        $page->delete();
    }

    public function restore(int $id): void
    {
        Page::withTrashed()->findOrFail($id)->restore();
    }

    public function forceDelete(int $id): void
    {
        $page = Page::withTrashed()->findOrFail($id);
        $page->clearMediaCollection('featured_image');
        $page->forceDelete();
    }
}
