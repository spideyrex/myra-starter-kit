<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Writes the page-builder draft into the session preview slot.
 *
 * There is no bespoke preview renderer: the editor's iframe loads the REAL
 * public `/` with `?preview=<token>`, and App\Homepage\Sections\PreviewSlot
 * decides whether to hand that request the draft instead of the stored list.
 *
 * SESSION CONTRACT (spec §5) — implemented against the text, not against a
 * shared class, so the reader and the writer can ship independently:
 *   key   : 'myra.pagebuilder.preview'
 *   value : ['token' => string, 'blocks' => array, 'expires_at' => int]
 *   TTL   : 15 minutes, one slot per session, a new POST overwrites it.
 */
class LandingPreviewController extends Controller
{
    public const SESSION_KEY = 'myra.pagebuilder.preview';

    public const TTL_SECONDS = 900;

    public const MAX_BLOCKS = 100;

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('settings.edit');

        $token = (string) Str::ulid();
        $expiresAt = time() + self::TTL_SECONDS;

        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'blocks' => $this->draft($request->input('blocks')),
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * A draft is work in progress, so it is never rejected — a validation
     * failure mid-keystroke would blank the author's preview. It is capped,
     * shape-checked and, when the writer is available, run through the same
     * hardening the save path uses.
     */
    private function draft(mixed $raw): array
    {
        if (! is_array($raw) || ! array_is_list($raw)) {
            return [];
        }

        $rows = array_values(array_filter(
            array_slice($raw, 0, self::MAX_BLOCKS),
            static fn ($row) => is_array($row),
        ));

        $writer = 'App\Homepage\Sections\SectionWriter';

        if (class_exists($writer) && method_exists($writer, 'prepare')) {
            try {
                $prepared = $writer::prepare($rows);

                if (is_array($prepared)) {
                    return $prepared;
                }
            } catch (\Throwable) {
                // Half-typed content is expected here. Fall through to the raw
                // rows: the slot is session-scoped and PreviewSlot re-checks
                // settings.edit, so the only reader is the author, and
                // rich_text is sanitised again client-side on render.
            }
        }

        return $rows;
    }
}
