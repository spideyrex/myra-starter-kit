<?php

namespace App\Http\Controllers;

use App\Homepage\HomepageTemplate;
// >>> MYRA v2.7 [A] START
use App\Homepage\Sections\PreviewSlot;
use App\Homepage\Sections\SectionNormalizer;
// <<< MYRA v2.7 [A] END
use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HomepageController extends Controller
{
    public function index(Request $request)
    {
        $settings = app(HomepageSettings::class);

        if (!$settings->enabled) {
            return redirect()->route('login');
        }

        $data = $settings->toArray();
        $data['hero_image_url'] = $settings->hero_image_path
            ? Storage::disk('public')->url($settings->hero_image_path)
            : null;

        // >>> MYRA v2.7 [A] START
        // The raw list never ships inside settings; only the normalised one does.
        unset($data['blocks']);

        $raw = PreviewSlot::pull($request) ?? ($settings->blocks ?? []);
        // <<< MYRA v2.7 [A] END

        // >>> MYRA v2.6 [D] START
        $template = TemplateRegistry::resolve($settings->template ?? null, $request);

        return Inertia::render('Home', [
            'settings' => $data,
            'authenticated' => Auth::check(),
            'template' => $template->key(),
            'templateOptions' => (object) ($settings->template_options[$template->key()] ?? []),
            'sectionOrder' => $this->sectionOrder($settings, $template),
            // >>> MYRA v2.7 [A] START
            'blocks' => SectionNormalizer::normalize($raw),
            // <<< MYRA v2.7 [A] END
        ]);
        // <<< MYRA v2.6 [D] END
    }

    /**
     * Stored order, filtered to what this template renders, with any section
     * the stored order forgot appended — so a template can never lose a section.
     *
     * @return array<int,string>
     */
    private function sectionOrder(HomepageSettings $settings, HomepageTemplate $template): array
    {
        $stored = array_values(array_filter(
            $settings->section_order ?? [],
            fn ($s) => is_string($s) && in_array($s, HomepageTemplate::SECTIONS, true),
        ));

        $order = array_values(array_unique([...$stored, ...HomepageTemplate::SECTIONS]));

        return array_values(array_filter($order, fn (string $s) => $template->supportsSection($s)));
    }
}
