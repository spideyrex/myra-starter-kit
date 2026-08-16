<?php

namespace App\Http\Controllers\Admin;

use App\Homepage\HomepageTemplate;
use App\Homepage\TemplateRegistry;
use App\Http\Controllers\Controller;
use App\Settings\HomepageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
// >>> MYRA v2.8 [C] START
use Illuminate\Support\Facades\DB;
// <<< MYRA v2.8 [C] END
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
    // >>> MYRA v2.8 [C] START
    /**
     * The page-surface allowlists, held LOCALLY: the appearance engine is
     * another bundle's and may not be installed. These strings are the spec's
     * and are asserted against the live enums once both halves are merged.
     */
    private const BG_TYPES = ['none', 'brand', 'solid', 'gradient', 'pattern', 'image'];

    private const GRADIENTS = ['brand-fade', 'brand-mesh', 'dusk', 'dawn', 'ink', 'aurora'];

    private const PATTERNS = ['dots', 'grid', 'diagonal', 'noise'];

    private const SCRIMS = ['none', 'light', 'medium', 'strong'];

    /** The five rows this page owns. `page_bg_image_path` is NOT one of them. */
    private const SURFACE_DEFAULTS = [
        'page_bg_type' => 'none',
        'page_bg_color' => null,
        'page_bg_recipe' => null,
        'page_bg_scrim' => 'medium',
        'page_navbar_translucent' => false,
    ];
    // <<< MYRA v2.8 [C] END

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
            // >>> MYRA v2.8 [C] START
            'surface' => $this->storedSurface(),
            'surfaceOptions' => [
                'types' => self::BG_TYPES,
                'gradients' => self::GRADIENTS,
                'patterns' => self::PATTERNS,
                'scrims' => self::SCRIMS,
            ],
            // <<< MYRA v2.8 [C] END
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $validated = $request->validate([
            'template' => ['required', 'string', Rule::in(TemplateRegistry::ids())],
            'section_order' => ['required', 'array', 'min:1'],
            'section_order.*' => ['required', 'string', Rule::in(HomepageTemplate::SECTIONS)],
            // >>> MYRA v2.8 [C] START
            // `sometimes` throughout: a client that predates the background
            // card still saves its template without sending these.
            'page_bg_type' => ['sometimes', 'string', Rule::in(self::BG_TYPES)],
            'page_bg_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'page_bg_recipe' => ['sometimes', 'nullable', 'string', Rule::in([...self::GRADIENTS, ...self::PATTERNS])],
            'page_bg_scrim' => ['sometimes', 'string', Rule::in(self::SCRIMS)],
            'page_navbar_translucent' => ['sometimes', 'boolean'],
            // <<< MYRA v2.8 [C] END
        ]);

        $settings = app(HomepageSettings::class);
        $settings->template = $validated['template'];
        $settings->section_order = $this->normaliseOrder($validated['section_order']);
        $settings->save();

        // >>> MYRA v2.8 [C] START
        $this->writeSurface($validated);
        // <<< MYRA v2.8 [C] END

        // >>> MYRA v2.7 [A] START
        // The template choice always applies; the section ORDER only does while
        // the page builder is empty, because a block list carries its own order.
        $blocks = $settings->blocks ?? [];

        if (is_array($blocks) && $blocks !== []) {
            return back()->with(
                'warning',
                __('Template updated. Section order now comes from the page builder — reorder the sections there.'),
            );
        }
        // <<< MYRA v2.7 [A] END

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

    // >>> MYRA v2.8 [C] START
    /**
     * The five rows this page owns, read straight from the settings table.
     *
     * Deliberately NOT read through the typed settings class: the appearance
     * engine ships in another bundle and this page must work without it.
     *
     * @return array<string,mixed>
     */
    private function storedSurface(): array
    {
        $stored = [];

        try {
            $rows = DB::table('settings')
                ->where('group', 'appearance')
                ->whereIn('name', array_keys(self::SURFACE_DEFAULTS))
                ->get(['name', 'payload']);

            foreach ($rows as $row) {
                $stored[$row->name] = json_decode((string) $row->payload, true);
            }
        } catch (\Throwable) {
            $stored = [];
        }

        return $this->normaliseSurface($stored);
    }

    /**
     * Total, field-wise. Anything unknown falls back on its own default; a
     * recipe that does not belong to the chosen type is dropped rather than
     * emitted, so a half-changed choice never renders a mismatched background.
     *
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    private function normaliseSurface(array $raw): array
    {
        $type = $raw['page_bg_type'] ?? null;
        $type = is_string($type) && in_array($type, self::BG_TYPES, true) ? $type : self::SURFACE_DEFAULTS['page_bg_type'];

        $color = $raw['page_bg_color'] ?? null;
        $color = is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : null;

        $scrim = $raw['page_bg_scrim'] ?? null;
        $scrim = is_string($scrim) && in_array($scrim, self::SCRIMS, true) ? $scrim : self::SURFACE_DEFAULTS['page_bg_scrim'];

        return [
            'page_bg_type' => $type,
            'page_bg_color' => $color,
            'page_bg_recipe' => $this->recipeFor($type, $raw['page_bg_recipe'] ?? null),
            'page_bg_scrim' => $scrim,
            'page_navbar_translucent' => (bool) ($raw['page_navbar_translucent'] ?? false),
        ];
    }

    private function recipeFor(string $type, mixed $recipe): ?string
    {
        if (! is_string($recipe) || $recipe === '') {
            return null;
        }

        $allowed = match ($type) {
            'gradient' => self::GRADIENTS,
            'pattern' => self::PATTERNS,
            default => [],
        };

        return in_array($recipe, $allowed, true) ? $recipe : null;
    }

    /**
     * Written by name through the settings table, then the brand cache is
     * dropped — 'appearance' is already one of BrandManager::GROUPS, so the
     * version probe picks the change up with no further wiring.
     *
     * @param  array<string,mixed>  $validated
     */
    private function writeSurface(array $validated): void
    {
        $sent = array_intersect_key($validated, self::SURFACE_DEFAULTS);

        if ($sent === []) {
            return;
        }

        $surface = $this->normaliseSurface([...$this->storedSurface(), ...$sent]);

        foreach ($surface as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'appearance', 'name' => $name],
                ['payload' => json_encode($value), 'locked' => false, 'updated_at' => now()],
            );
        }

        app(\App\Brand\BrandManager::class)->forget();
    }
    // <<< MYRA v2.8 [C] END
}
