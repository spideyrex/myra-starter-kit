<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $media = Media::query()
            // Non-super-admins only see media they uploaded (attached to their User model).
            ->when(! $this->isSuperAdmin($user), fn ($q) => $q
                ->where('model_type', User::class)
                ->where('model_id', $user->id))
            ->when($request->search, fn ($q, $s) => $q->where('file_name', 'like', "%{$s}%"))
            ->when($request->type, fn ($q, $type) => $q->where('mime_type', 'like', "{$type}%"))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Admin/Media/Index', [
            'media' => MediaResource::collection($media),
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => 'required|array|max:20',
            'files.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,csv,txt,zip,mp4,mp3,wav',
        ]);

        $user = $request->user();

        foreach ($request->file('files') as $file) {
            $user->addMedia($file)
                ->toMediaCollection('uploads', 'public');
        }

        return back()->with('success', 'Files uploaded successfully.');
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        abort_unless($this->ownsMedia($request->user(), $media), 403);

        $media->delete();

        return back()->with('success', 'File deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $user = $request->user();

        Media::whereIn('id', $request->ids)
            ->when(! $this->isSuperAdmin($user), fn ($q) => $q
                ->where('model_type', User::class)
                ->where('model_id', $user->id))
            ->each(fn (Media $media) => $media->delete());

        return back()->with('success', 'Selected files deleted.');
    }

    private function isSuperAdmin(?User $user): bool
    {
        return $user?->hasRole(config('shield.super_admin_role', 'super-admin')) ?? false;
    }

    private function ownsMedia(?User $user, Media $media): bool
    {
        return $this->isSuperAdmin($user)
            || ($media->model_type === User::class && (int) $media->model_id === (int) $user?->id);
    }
}
