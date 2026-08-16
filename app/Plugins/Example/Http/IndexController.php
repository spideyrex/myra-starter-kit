<?php

namespace App\Plugins\Example\Http;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The nav-facing page. A plugin's nav item must resolve to an Inertia response —
 * pointing it at a JSON route makes Inertia reject the visit and blocks the UI.
 */
class IndexController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Plugins/Example/Index', [
            'pingUrl' => route('admin.myra-example.ping'),
        ]);
    }
}
