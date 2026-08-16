<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Blocks\BlockEntry;
use App\Admin\Blocks\BlockRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Read-only reference catalogue for the vendored shadcn blocks.
 *
 * Nothing here touches the filesystem with a URL parameter: {block} is resolved
 * through the registry and the file paths come from the committed manifest.
 */
class BlockController extends Controller
{
    public function index(Request $request)
    {
        $this->guard();

        return Inertia::render('Admin/Blocks/Index', [
            'blocks' => BlockRegistry::forUser($request->user()),
            'categories' => BlockRegistry::categories(),
        ]);
    }

    public function show(Request $request, string $block)
    {
        $this->guard();

        $entry = $this->entry($request, $block);

        return Inertia::render('Admin/Blocks/Show', [
            'block' => $entry->toClientSchema(),
            'source' => BlockRegistry::sourceFor($entry),
            'viewports' => (array) config('myra.blocks.viewport', ['full']),
        ]);
    }

    /**
     * BARE page: no admin shell. Rendered inside a same-origin iframe because a
     * sidebar block mounts its own SidebarProvider, binds Cmd+B and persists a
     * sidebar_state cookie — inline mounting would hijack the live admin shell.
     *
     * The response deliberately touches no cookie. withoutCookie() would not
     * suppress one: it QUEUES a deletion cookie, so every preview would wipe the
     * admin's own sidebar preference. The frame shields that name client-side.
     */
    public function preview(Request $request, string $block)
    {
        $this->guard();

        $entry = $this->entry($request, $block);

        return Inertia::render('Admin/Blocks/Preview', [
            'block' => $entry->toClientSchema(),
            'dark' => $request->query('dark') === '1',
            'reduced' => $request->query('reduced') === '1',
        ]);
    }

    public function source(Request $request, string $block): JsonResponse
    {
        $this->guard();

        $entry = $this->entry($request, $block);

        return response()->json([
            'key' => $entry->key(),
            'files' => BlockRegistry::sourceFor($entry),
        ]);
    }

    private function entry(Request $request, string $block): BlockEntry
    {
        Gate::authorize('blocks.view');

        BlockRegistry::seed();

        $entry = BlockRegistry::get($block);

        abort_if($entry === null, 404);

        $ability = $entry->permissionAbility();

        abort_if($ability !== null && ! $request->user()?->can($ability), 404);

        return $entry;
    }

    private function guard(): void
    {
        abort_unless((bool) config('myra.blocks.enabled', true), 404);

        Gate::authorize('blocks.view');
    }
}
