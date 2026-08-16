<?php

namespace App\Http\Controllers\Admin;

use App\Homepage\HomepageTemplate;
use App\Homepage\TemplateRegistry;
use App\Http\Controllers\Controller;
use App\Settings\HomepageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing-page template chooser.
 *
 * A dedicated page rather than an eighth tab on Settings/Index.vue, which is
 * already seven tabs and 500 lines.
 */
class LandingController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('settings.edit');

        $settings = app(HomepageSettings::class);
        $current = TemplateRegistry::resolve($settings->template ?? null);

        return Inertia::render('Admin/Landing/Index', [
            'templates' => TemplateRegistry::toClientSchema(),
            'current' => $current->key(),
            'sectionOrder' => $this->normaliseOrder($settings->section_order ?? []),
            'sections' => HomepageTemplate::SECTIONS,
            'sectionsEnabled' => [
                'hero' => true,
                'features' => $settings->features_enabled,
                'testimonials' => $settings->testimonials_enabled,
                'pricing' => $settings->pricing_enabled,
                'cta' => $settings->cta_enabled,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $validated = $request->validate([
            'template' => ['required', 'string', Rule::in(TemplateRegistry::ids())],
            'section_order' => ['required', 'array', 'min:1'],
            'section_order.*' => ['required', 'string', Rule::in(HomepageTemplate::SECTIONS)],
        ]);

        $settings = app(HomepageSettings::class);
        $settings->template = $validated['template'];
        $settings->section_order = $this->normaliseOrder($validated['section_order']);
        $settings->save();

        return back()->with('success', __('Landing template updated.'));
    }

    /**
     * Deduplicated, restricted to known sections, with any missing section
     * appended — the stored order can never drop a section.
     *
     * @param  array<int,mixed>  $order
     * @return array<int,string>
     */
    private function normaliseOrder(array $order): array
    {
        $clean = array_values(array_filter(
            $order,
            fn ($s) => is_string($s) && in_array($s, HomepageTemplate::SECTIONS, true),
        ));

        return array_values(array_unique([...$clean, ...HomepageTemplate::SECTIONS]));
    }
}
