<?php

namespace App\Http\Controllers\Admin;

use App\Homepage\Sections\LegacyHomepageBlocks;
use App\Homepage\Sections\SectionRegistry;
use App\Homepage\Sections\SectionWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePageBlocksRequest;
use App\Settings\HomepageSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page builder editor.
 *
 * The section model (SectionRegistry / SectionWriter / LegacyHomepageBlocks)
 * belongs to bundle A. Every touch point is resolved through a guarded helper so
 * this surface degrades to "no section types available" rather than fatalling if
 * the model is absent.
 */
class PageBuilderController extends Controller
{
    private const EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    public function index(): Response
    {
        Gate::authorize('settings.edit');

        $settings = app(HomepageSettings::class);

        return Inertia::render('Admin/Landing/Builder', [
            'blocks' => $this->storedBlocks($settings),
            'schemas' => $this->schemas(),
            'template' => is_string($settings->template ?? null) ? $settings->template : 'classic',
            'maxBlocks' => UpdatePageBlocksRequest::MAX_BLOCKS,
            'canConvert' => class_exists(LegacyHomepageBlocks::class),
        ]);
    }

    public function update(UpdatePageBlocksRequest $request): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $raw = $request->validated('blocks') ?? [];

        $settings = app(HomepageSettings::class);
        $settings->blocks = class_exists(SectionWriter::class)
            ? SectionWriter::prepare($raw)
            : array_values((array) $raw);
        $settings->save();

        return back()->with('success', __('Page sections saved.'));
    }

    /**
     * The legacy singletons converted to a block list, returned for review.
     * Deliberately NOT persisted — the author saves it themselves.
     */
    public function convert(): JsonResponse
    {
        Gate::authorize('settings.edit');

        return response()->json([
            'blocks' => class_exists(LegacyHomepageBlocks::class)
                ? LegacyHomepageBlocks::fromSettings(app(HomepageSettings::class))
                : [],
        ]);
    }

    /**
     * Section images land on the PUBLIC disk — anonymous visitors read them — so
     * the magic-byte check InlineUploadController performs is replicated here
     * rather than assumed. The extension comes from IMAGETYPE_*, never from the
     * client filename.
     */
    public function image(Request $request): JsonResponse
    {
        Gate::authorize('settings.edit');

        $request->validate([
            'file' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp',
                'max:' . config('myra.uploads.max_kb', 5120),
            ],
        ]);

        $file = $request->file('file');

        $info = @getimagesize($file->getRealPath());
        abort_if(! $info || ! isset(self::EXTENSIONS[$info[2]]), 422, 'That file is not a valid image.');

        $path = $file->storeAs(
            'homepage',
            (string) Str::ulid() . '.' . self::EXTENSIONS[$info[2]],
            'public',
        );

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function storedBlocks(HomepageSettings $settings): array
    {
        $blocks = $settings->toArray()['blocks'] ?? [];

        return is_array($blocks) ? array_values($blocks) : [];
    }

    /** @return array<int,array<string,mixed>> */
    private function schemas(): array
    {
        if (! class_exists(SectionRegistry::class)) {
            return [];
        }

        SectionRegistry::seed();

        return SectionRegistry::toClientSchema();
    }
}
