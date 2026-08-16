<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Examples\ExampleEntry;
use App\Admin\Examples\ExampleRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ExampleController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('examples.view');

        abort_unless(config('myra.examples.enabled', true), 404);

        return Inertia::render('Admin/Examples/Index', [
            'examples' => ExampleRegistry::forUser($request->user()),
        ]);
    }

    public function show(Request $request, string $example)
    {
        Gate::authorize('examples.view');

        $entry = $this->entry($example);

        return Inertia::render('Admin/Examples/Show', [
            'example' => $entry->toClientSchema(),
            'source' => ExampleRegistry::sourceFor($entry),
        ]);
    }

    /**
     * BARE page inside a same-origin iframe. The shell mounts its own layout
     * chrome, so it must never nest inside the admin's. It mounts no
     * SidebarProvider either, so it never touches the live sidebar_state cookie
     * — and the response must not touch it back: withoutCookie() would queue a
     * deletion cookie that EncryptCookies still emits with a value.
     */
    public function preview(Request $request, string $example): SymfonyResponse
    {
        Gate::authorize('examples.view');

        $entry = $this->entry($example);

        abort_unless($entry->isAvailable(), 404);

        return Inertia::render('Admin/Examples/Preview', [
            'example' => $entry->toClientSchema(),
            'dark' => $request->boolean('dark'),
            'reduced' => $request->boolean('reduced'),
        ])->toResponse($request);
    }

    public function source(Request $request, string $example): JsonResponse
    {
        Gate::authorize('examples.view');

        $entry = $this->entry($example);

        return response()->json([
            'key' => $entry->key(),
            'origin' => $entry->originValue(),
            'files' => ExampleRegistry::sourceFor($entry),
        ]);
    }

    /** Resolved through the registry, so an unknown id never touches the disk. */
    private function entry(string $example): ExampleEntry
    {
        abort_unless(config('myra.examples.enabled', true), 404);

        ExampleRegistry::seed();

        $entry = ExampleRegistry::get($example);

        abort_if($entry === null, 404);

        return $entry;
    }
}
