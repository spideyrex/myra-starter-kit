<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\AiSettings;
use App\Settings\AppearanceSettings;
use App\Settings\GeneralSettings;
use App\Settings\HomepageSettings;
use App\Settings\MaintenanceSettings;
use App\Settings\SeoSettings;
use App\Settings\SocialSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        $appearance = app(AppearanceSettings::class);
        $homepage = app(HomepageSettings::class);

        return Inertia::render('Admin/Settings/Index', [
            'general' => app(GeneralSettings::class)->toArray(),
            'seo' => app(SeoSettings::class)->toArray(),
            'appearance' => array_merge($appearance->toArray(), [
                'logo_url' => $appearance->logo_path ? Storage::disk('public')->url($appearance->logo_path) : null,
                'favicon_url' => $appearance->favicon_path ? Storage::disk('public')->url($appearance->favicon_path) : null,
            ]),
            'social' => app(SocialSettings::class)->toArray(),
            'maintenance' => app(MaintenanceSettings::class)->toArray(),
            'homepage' => array_merge($homepage->toArray(), [
                'hero_image_url' => $homepage->hero_image_path ? Storage::disk('public')->url($homepage->hero_image_path) : null,
            ]),
            'ai' => array_merge(app(AiSettings::class)->toArray(), [
                'api_key' => app(AiSettings::class)->api_key ? str_repeat('*', 8) : null,
            ]),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $settings = match ($group) {
            'general' => app(GeneralSettings::class),
            'seo' => app(SeoSettings::class),
            'appearance' => app(AppearanceSettings::class),
            'social' => app(SocialSettings::class),
            'maintenance' => app(MaintenanceSettings::class),
            default => abort(404),
        };

        // Explicit whitelist of allowed fields per settings group
        $allowedFields = match ($group) {
            'general' => ['site_name', 'site_description', 'site_email', 'site_phone', 'site_address', 'timezone', 'locale', 'date_format', 'time_format', 'registration_enabled'],
            'seo' => ['meta_title', 'meta_description', 'meta_keywords', 'google_analytics_id', 'robots_txt', 'og_image_path'],
            'appearance' => ['primary_color', 'theme'],
            'social' => ['facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url', 'youtube_url', 'github_url'],
            'maintenance' => ['enabled', 'message', 'allowed_ips', 'secret'],
            default => [],
        };

        foreach ($allowedFields as $key) {
            if ($request->has($key) && property_exists($settings, $key)) {
                $settings->$key = $request->input($key);
            }
        }

        $settings->save();

        cache()->forget('site_settings_shared');

        return back()->with('success', ucfirst($group) . ' settings updated successfully.');
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'theme' => ['nullable', 'string', 'max:20'],
            'logo_position' => ['nullable', 'string', 'in:sidebar,header'],
            'sidebar_background' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_foreground' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sidebar_accent' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $settings = app(AppearanceSettings::class);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $settings->logo_path = $request->file('logo')->store('settings', 'public');
        }

        // Handle logo removal
        if ($request->boolean('remove_logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $settings->logo_path = null;
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            if ($settings->favicon_path) {
                Storage::disk('public')->delete($settings->favicon_path);
            }
            $settings->favicon_path = $request->file('favicon')->store('settings', 'public');
        }

        // Handle favicon removal
        if ($request->boolean('remove_favicon')) {
            if ($settings->favicon_path) {
                Storage::disk('public')->delete($settings->favicon_path);
            }
            $settings->favicon_path = null;
        }

        // Handle primary color
        if ($request->filled('primary_color')) {
            $settings->primary_color = $request->input('primary_color');
        }

        // Handle theme
        if ($request->filled('theme')) {
            $settings->theme = $request->input('theme');
        }

        // Handle logo position
        if ($request->filled('logo_position')) {
            $settings->logo_position = $request->input('logo_position');
        }

        // Handle sidebar colors (empty string = reset to null)
        foreach (['sidebar_background', 'sidebar_foreground', 'sidebar_accent'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $settings->$field = ($value === '' || $value === null) ? null : $value;
            }
        }

        $settings->save();

        cache()->forget('site_settings_shared');

        return back()->with('success', 'Appearance settings updated successfully.');
    }

    public function updateHomepage(Request $request): RedirectResponse
    {
        $request->validate([
            'hero_image' => ['nullable', 'image', 'max:4096'],
        ]);

        $settings = app(HomepageSettings::class);

        // Handle hero image upload
        if ($request->hasFile('hero_image')) {
            if ($settings->hero_image_path) {
                Storage::disk('public')->delete($settings->hero_image_path);
            }
            $settings->hero_image_path = $request->file('hero_image')->store('settings', 'public');
        }

        // Handle hero image removal
        if ($request->boolean('remove_hero_image')) {
            if ($settings->hero_image_path) {
                Storage::disk('public')->delete($settings->hero_image_path);
            }
            $settings->hero_image_path = null;
        }

        // Boolean fields
        $booleanFields = ['enabled', 'features_enabled', 'testimonials_enabled', 'pricing_enabled', 'cta_enabled'];
        foreach ($booleanFields as $field) {
            if ($request->has($field)) {
                $settings->$field = $request->boolean($field);
            }
        }

        // String fields
        $stringFields = [
            'hero_title', 'hero_subtitle', 'hero_cta_text', 'hero_cta_url',
            'features_title', 'features_subtitle',
            'testimonials_title', 'testimonials_subtitle',
            'pricing_title', 'pricing_subtitle',
            'cta_title', 'cta_subtitle', 'cta_button_text', 'cta_button_url',
            'footer_text',
            'navbar_cta_text', 'navbar_cta_url',
        ];
        foreach ($stringFields as $field) {
            if ($request->has($field)) {
                $settings->$field = $request->input($field, '');
            }
        }

        // Array fields (may arrive as JSON strings from FormData)
        $arrayFields = ['features', 'testimonials', 'pricing_plans', 'footer_links', 'navbar_links'];
        foreach ($arrayFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                if (is_string($value)) {
                    $value = json_decode($value, true) ?? [];
                }
                $settings->$field = $value;
            }
        }

        $settings->save();

        cache()->forget('site_settings_shared');

        return back()->with('success', 'Homepage settings updated successfully.');
    }
}
