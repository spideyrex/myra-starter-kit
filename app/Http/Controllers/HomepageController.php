<?php

namespace App\Http\Controllers;

use App\Settings\HomepageSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HomepageController extends Controller
{
    public function index()
    {
        $settings = app(HomepageSettings::class);

        if (!$settings->enabled) {
            return redirect()->route('login');
        }

        $data = $settings->toArray();
        $data['hero_image_url'] = $settings->hero_image_path
            ? Storage::disk('public')->url($settings->hero_image_path)
            : null;

        return Inertia::render('Home', [
            'settings' => $data,
            'authenticated' => Auth::check(),
        ]);
    }
}
