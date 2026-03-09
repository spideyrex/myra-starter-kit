<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $media = Media::query()
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
            'files' => 'required|array',
            'files.*' => 'file|max:10240',
        ]);

        $user = $request->user();

        foreach ($request->file('files') as $file) {
            $user->addMedia($file)
                ->toMediaCollection('uploads', 'public');
        }

        return back()->with('success', 'Files uploaded successfully.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $media->delete();

        return back()->with('success', 'File deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        Media::whereIn('id', $request->ids)->each(fn (Media $media) => $media->delete());

        return back()->with('success', 'Selected files deleted.');
    }
}
