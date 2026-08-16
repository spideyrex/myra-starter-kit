<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inline (markdown) image uploads.
 *
 * Ownership is encoded in the storage path and re-checked on every read, so no
 * table is needed. The persisted URL is stable, so a saved document never
 * breaks; it is permission-gated instead of expiring.
 */
class InlineUploadController extends Controller
{
    private const EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    private const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('media.create') === true, 403);

        $request->validate([
            'file' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp',
                'max:' . config('myra.uploads.max_kb', 5120),
            ],
        ]);

        $file = $request->file('file');

        // `mimes:` trusts the client-supplied extension. Verify the actual bytes.
        $info = @getimagesize($file->getRealPath());
        abort_if(! $info || ! isset(self::EXTENSIONS[$info[2]]), 422, 'That file is not a valid image.');

        $path = $file->storeAs(
            'inline/' . $request->user()->id,
            (string) Str::ulid() . '.' . self::EXTENSIONS[$info[2]],
            'local', // PRIVATE disk. Nothing lands in public/.
        );

        return response()->json([
            // The route re-adds the 'inline/' prefix, so strip it from the URL.
            'url' => route('admin.uploads.inline.show', [
                'path' => Str::after($path, 'inline/'),
            ]),
        ]);
    }

    public function show(Request $request, string $path): StreamedResponse
    {
        // The route carries the path without its storage prefix; re-add it here.
        abort_unless(
            preg_match('#^(\d+)/[0-9A-HJKMNP-TV-Z]{26}\.(jpg|png|gif|webp)$#', $path, $m) === 1,
            404,
        );

        $path = 'inline/' . $path;

        $user = $request->user();
        abort_unless($user !== null, 404);

        // 404, not 403 — a 403 confirms the file exists.
        abort_unless((int) $m[1] === (int) $user->id || $user->can('media.view'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => self::MIME_TYPES[$m[2]],
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
