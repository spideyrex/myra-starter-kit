<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\AppearanceSettings;
use App\Settings\GeneralSettings;
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

        return Inertia::render('Admin/Settings/Index', [
            'general' => app(GeneralSettings::class)->toArray(),
            'seo' => app(SeoSettings::class)->toArray(),
            'appearance' => array_merge($appearance->toArray(), [
                'logo_url' => $appearance->logo_path ? Storage::disk('public')->url($appearance->logo_path) : null,
                'favicon_url' => $appearance->favicon_path ? Storage::disk('public')->url($appearance->favicon_path) : null,
            ]),
            'social' => app(SocialSettings::class)->toArray(),
            'maintenance' => app(MaintenanceSettings::class)->toArray(),
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

        foreach ($request->all() as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->$key = $value;
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

        $settings->save();

        cache()->forget('site_settings_shared');

        return back()->with('success', 'Appearance settings updated successfully.');
    }
}
