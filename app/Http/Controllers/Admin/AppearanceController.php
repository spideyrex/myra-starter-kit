<?php

namespace App\Http\Controllers\Admin;

use App\Appearance\Admin\AppearanceWriter;
use App\Appearance\Admin\AuthPreviewSlot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSurfaceAppearanceRequest;
use App\Rules\SafeImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The auth-surface editor. Reuses brand.view / brand.update rather than minting
 * a permission, and never blocks a save on contrast — it warns.
 */
class AppearanceController extends Controller
{
    private const DISK = 'public';

    public function index(AppearanceWriter $writer): Response
    {
        Gate::authorize('brand.view');

        $values = $writer->read();

        return Inertia::render('Admin/Appearance/Index', [
            'appearanceSettings' => array_merge($values, [
                'auth_bg_image_url' => $this->url($values['auth_bg_image_path'] ?? null),
                'page_bg_image_url' => $this->url($values['page_bg_image_path'] ?? null),
            ]),
            'layouts' => AuthPreviewSlot::layouts(),
            'options' => [
                'types' => AppearanceWriter::TYPES,
                'gradients' => AppearanceWriter::GRADIENTS,
                'patterns' => AppearanceWriter::PATTERNS,
                'scrims' => AppearanceWriter::SCRIMS,
                'recipeCss' => AuthPreviewSlot::RECIPE_CSS,
                'recipeSize' => AuthPreviewSlot::RECIPE_SIZE,
                'minContrast' => (float) config('myra.brand.min_contrast', 4.5),
            ],
            'preview' => AuthPreviewSlot::resolve($values),
        ]);
    }

    public function update(UpdateSurfaceAppearanceRequest $request, AppearanceWriter $writer): RedirectResponse
    {
        Gate::authorize('brand.update');

        $rows = $request->toRows();
        $writer->write($rows);

        $resolved = AuthPreviewSlot::resolve(array_merge($writer->read(), $rows));

        // Advisory only. A rule that can refuse an appearance is a rule that can
        // leave an admin staring at a screen they cannot fix.
        if ($resolved['warning']) {
            return back()->with('warning', __('Appearance saved. The text contrast on this background is below the recommended minimum.'));
        }

        return back()->with('success', __('Appearance saved.'));
    }

    /** Real resolution of UNSAVED input. Persists nothing. */
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('brand.update');

        $input = $request->validate(UpdateSurfaceAppearanceRequest::ruleSet());

        $stored = app(AppearanceWriter::class)->read();

        return response()->json([
            'auth' => AuthPreviewSlot::resolve(array_merge($stored, $input)),
            'minContrast' => (float) config('myra.brand.min_contrast', 4.5),
        ]);
    }

    /** Magic-byte validated, content-addressed, on the PUBLIC disk. */
    public function uploadBackground(Request $request, string $surface, AppearanceWriter $writer): RedirectResponse
    {
        Gate::authorize('brand.update');

        abort_unless(in_array($surface, AppearanceWriter::SURFACES, true), 404);

        $request->validate(['image' => ['required', new SafeImage('surface')]]);

        $bytes = (string) file_get_contents($request->file('image')->getRealPath());
        $ext = SafeImage::extensionFor($bytes) ?? 'png';
        $path = 'appearance/'.substr(sha1($bytes), 0, 8).'/'.$surface.'.'.$ext;

        $previous = $writer->read()[$surface.'_bg_image_path'] ?? null;

        Storage::disk(self::DISK)->put($path, $bytes);

        $writer->write([$surface.'_bg_image_path' => $path]);
        $this->purge($previous, $path);

        return back()->with('success', __('Background image uploaded.'));
    }

    public function destroyBackground(string $surface, AppearanceWriter $writer): RedirectResponse
    {
        Gate::authorize('brand.update');

        abort_unless(in_array($surface, AppearanceWriter::SURFACES, true), 404);

        $previous = $writer->read()[$surface.'_bg_image_path'] ?? null;

        $writer->write([$surface.'_bg_image_path' => null]);
        $this->purge($previous, null);

        return back()->with('success', __('Background image removed.'));
    }

    /** Drops the superseded content-addressed copy; only ever under appearance/. */
    private function purge(mixed $previous, ?string $keep): void
    {
        if (! is_string($previous) || $previous === '' || $previous === $keep) {
            return;
        }

        if (! str_starts_with($previous, 'appearance/') || str_contains($previous, '..')) {
            return;
        }

        try {
            Storage::disk(self::DISK)->delete($previous);
        } catch (\Throwable) {
            // A stale file is harmless; a throw on the admin path is not.
        }
    }

    private function url(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            return Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->url($path) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
