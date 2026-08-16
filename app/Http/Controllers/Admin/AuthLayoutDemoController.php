<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MYRA v2.8 [D] — the guest-shell gallery.
 *
 * Every surface preview is a REAL Background payload resolved through
 * AppearanceManager::fromInput(), so the gallery cannot drift from what the
 * login page actually renders. The engine ships in another bundle, so its
 * absence degrades to the stock brand payload and a flag the page can explain,
 * never a 500 on a demo route.
 */
class AuthLayoutDemoController extends Controller
{
    private const MANAGER = 'App\\Appearance\\AppearanceManager';

    private const REGISTRY = 'App\\Appearance\\AuthLayoutRegistry';

    private const RECIPES = 'App\\Appearance\\Recipes';

    /** Mirrors app/Appearance/Layouts/*.php. Used only when the registry is absent. */
    private const FALLBACK_LAYOUTS = [
        ['key' => 'split', 'component' => 'SplitLayout', 'flippable' => true, 'supportsMedia' => true],
        ['key' => 'centered', 'component' => 'CenteredLayout', 'flippable' => false, 'supportsMedia' => false],
        ['key' => 'cover', 'component' => 'CoverLayout', 'flippable' => false, 'supportsMedia' => true],
        ['key' => 'card', 'component' => 'CardLayout', 'flippable' => true, 'supportsMedia' => true],
    ];

    private const FALLBACK_GRADIENTS = ['brand-fade', 'brand-mesh', 'dusk', 'dawn', 'ink', 'aurora'];

    private const FALLBACK_PATTERNS = ['dots', 'grid', 'diagonal', 'noise'];

    public function __invoke(): Response
    {
        Gate::authorize('demo.view');

        return Inertia::render('Admin/Demo/AuthLayouts', [
            'layouts' => $this->layouts(),
            'surfaces' => $this->surfaces(),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function layouts(): array
    {
        /** @var class-string $registry */
        $registry = self::REGISTRY;

        if (class_exists($registry) && method_exists($registry, 'toClientSchema')) {
            try {
                if (method_exists($registry, 'seed')) {
                    $registry::seed();
                }

                $schema = $registry::toClientSchema();

                if (is_array($schema) && $schema !== []) {
                    return array_values($schema);
                }
            } catch (\Throwable) {
                // Fall through to the declared list below.
            }
        }

        return array_map(fn (array $row) => $row + [
            'titleKey' => "appearanceDemo.layouts.{$row['key']}.title",
            'descriptionKey' => "appearanceDemo.layouts.{$row['key']}.description",
            'thumbnail' => null,
            'since' => '2.8.0',
        ], self::FALLBACK_LAYOUTS);
    }

    /** @return array<int,array<string,mixed>> */
    private function surfaces(): array
    {
        $rows = [
            ['key' => 'brand', 'type' => 'brand'],
            ['key' => 'solid', 'type' => 'solid', 'color' => '#1e3a8a'],
            ['key' => 'none', 'type' => 'none'],
        ];

        foreach ($this->recipeKeys('gradient') as $key) {
            $rows[] = ['key' => "gradient-{$key}", 'type' => 'gradient', 'recipe' => $key];
        }

        foreach ($this->recipeKeys('pattern') as $key) {
            $rows[] = ['key' => "pattern-{$key}", 'type' => 'pattern', 'recipe' => $key];
        }

        // No file behind it on purpose: this is the deleted-background case the
        // gallery exists to make visible.
        $rows[] = ['key' => 'image-missing', 'type' => 'image', 'image' => 'appearance/not-here.jpg', 'scrim' => 'none'];

        return array_map(fn (array $row) => [
            'key' => $row['key'],
            'labelKey' => "appearanceDemo.surfaces.{$row['key']}",
            'live' => $this->engineAvailable(),
            'surface' => $this->resolve($row),
        ], $rows);
    }

    /** @return array<int,string> */
    private function recipeKeys(string $family): array
    {
        /** @var class-string $recipes */
        $recipes = self::RECIPES;

        if (class_exists($recipes) && method_exists($recipes, 'keys')) {
            try {
                $keys = $recipes::keys($family);

                if (is_array($keys) && $keys !== []) {
                    return array_values(array_filter($keys, 'is_string'));
                }
            } catch (\Throwable) {
                // Fall through.
            }
        }

        return $family === 'gradient' ? self::FALLBACK_GRADIENTS : self::FALLBACK_PATTERNS;
    }

    private function engineAvailable(): bool
    {
        return class_exists(self::MANAGER) && method_exists(self::MANAGER, 'fromInput');
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function resolve(array $row): array
    {
        if ($this->engineAvailable()) {
            try {
                $background = app(self::MANAGER)->fromInput([
                    'auth_bg_type' => $row['type'],
                    'auth_bg_color' => $row['color'] ?? null,
                    'auth_bg_recipe' => $row['recipe'] ?? null,
                    'auth_bg_image_path' => $row['image'] ?? null,
                    'auth_bg_scrim' => $row['scrim'] ?? 'medium',
                ], 'auth');

                $payload = $background->toArray();

                if (is_array($payload)) {
                    return $payload;
                }
            } catch (\Throwable) {
                // Fall through to the inert payload.
            }
        }

        return self::inert();
    }

    /** @return array<string,mixed> */
    private static function inert(): array
    {
        return [
            'type' => 'brand',
            'recipe' => null,
            'scrim' => 'medium',
            'image_url' => null,
            'base' => '',
            'foreground' => '',
            'contrast' => 0.0,
            'css_vars' => (object) [],
        ];
    }
}
