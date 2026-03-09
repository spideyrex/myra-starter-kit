<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicPageController extends Controller
{
    public function show(string $slug): Response|HttpResponse
    {
        $page = Page::where('slug', $slug)->published()->firstOrFail();

        if (!$page->is_public && !Auth::check()) {
            return redirect()->route('login');
        }

        return Inertia::render('Public/PageShow', [
            'page' => [
                'title' => $page->title,
                'body_html' => $page->body_html,
                'excerpt' => $page->excerpt,
                'meta' => $page->meta,
                'published_at' => $page->published_at?->toISOString(),
                'featured_image_url' => $page->getFirstMediaUrl('featured_image') ?: null,
            ],
            'authenticated' => Auth::check(),
        ]);
    }
}
