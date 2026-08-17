<?php

namespace App\Appearance;

use App\Brand\BrandManager;
use App\Brand\BrandPalette;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/**
 * Resolves the two surfaces — `auth` (the seven guest pages) and `page` (the
 * public homepage) — out of the `appearance` settings group.
 *
 * Every read is wrapped: a throw anywhere returns the stock appearance, so the
 * login page degrades to today's layout instead of 500ing.
 */
class AppearanceManager
{
    private ?AuthAppearance $authMemo = null;

    private ?string $authMemoKey = null;

    private ?Background $pageMemo = null;

    private ?string $pageMemoKey = null;

    public function auth(?Request $request = null): AuthAppearance
    {
        // KILL SWITCH — returns before ANY db or cache access.
        if (config('myra.appearance.enabled', true) !== true) {
            return AuthAppearance::stock();
        }

        $key = $this->stateKey().'|'.((string) ($request?->query('authLayout') ?? ''));

        if ($this->authMemo !== null && $this->authMemoKey === $key) {
            return $this->authMemo;
        }

        try {
            $raw = $this->raw();

            $resolved = new AuthAppearance(
                AuthLayoutRegistry::resolve(
                    is_string($raw['auth_layout'] ?? null) ? $raw['auth_layout'] : null,
                    $request,
                ),
                ($raw['auth_flip'] ?? false) === true,
                ($raw['auth_show_tagline'] ?? true) !== false,
                Background::fromSettings($raw, 'auth'),
            );

            $this->authMemoKey = $key;

            return $this->authMemo = $resolved;
        } catch (\Throwable) {
            $this->authMemo = null;
            $this->authMemoKey = null;

            return AuthAppearance::stock();
        }
    }

    public function page(): Background
    {
        $key = $this->stateKey();

        if ($this->pageMemo !== null && $this->pageMemoKey === $key) {
            return $this->pageMemo;
        }

        try {
            $this->pageMemoKey = $key;

            return $this->pageMemo = Background::fromSettings($this->raw(), 'page');
        } catch (\Throwable) {
            $this->pageMemo = null;
            $this->pageMemoKey = null;

            return Background::default('page');
        }
    }

    public function navbarTranslucent(): bool
    {
        try {
            return ($this->raw()['page_navbar_translucent'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Byte-EMPTY at the defaults: both surfaces return [] from cssVars(), which
     * is the mechanical proof that upgrading changes nothing.
     *
     * Deliberately NOT gated on brand->enabled — an operator who turned the
     * brand off still gets the surface they configured.
     */
    public function styleTag(): HtmlString
    {
        try {
            $palette = $this->palette();

            $vars = array_merge(
                $this->auth()->background->cssVars('auth', $palette),
                $this->page()->cssVars('page', $palette),
            );

            if ($vars === []) {
                return new HtmlString('');
            }

            $out = '';
            foreach ($vars as $prop => $value) {
                $out .= $prop.':'.$value.';';
            }

            return new HtmlString('<style id="myra-surface">:root{'.$out.'}</style>');
        } catch (\Throwable) {
            return new HtmlString('');
        }
    }

    public function toInertiaProp(?Request $request = null): array
    {
        try {
            $palette = $this->palette();

            return [
                'auth' => $this->auth($request)->toArray($palette),
                'page' => [
                    'navbar_translucent' => $this->navbarTranslucent(),
                    'surface' => $this->page()->toArray($palette),
                ],
            ];
        } catch (\Throwable) {
            return [
                'auth' => AuthAppearance::stock()->toArray(),
                'page' => [
                    'navbar_translucent' => false,
                    'surface' => Background::default('page')->toArray(),
                ],
            ];
        }
    }

    /**
     * Real resolution of UNSAVED input. Persists nothing. Preview and save go
     * through this same method so they cannot diverge.
     *
     * Accepts either prefixed (`auth_bg_type`) or bare (`bg_type`) keys.
     */
    public function fromInput(array $input, string $surface): Background
    {
        $prefix = $surface === 'page' ? 'page' : 'auth';

        try {
            $raw = $this->raw();
        } catch (\Throwable) {
            $raw = [];
        }

        foreach ($input as $name => $value) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $raw[str_starts_with($name, $prefix.'_') ? $name : $prefix.'_'.ltrim($name, '_')] = $value;
        }

        return Background::fromSettings($raw, $surface);
    }

    public function forget(): void
    {
        $this->authMemo = null;
        $this->authMemoKey = null;
        $this->pageMemo = null;
        $this->pageMemoKey = null;

        app(BrandManager::class)->forget();
    }

    /**
     * The memo key. Folding the brand hash in means a foreign forget() — B's
     * writer calls BrandManager::forget() directly — invalidates our memo too.
     */
    private function stateKey(): string
    {
        try {
            return app(BrandManager::class)->hash();
        } catch (\Throwable) {
            return '';
        }
    }

    private function palette(): BrandPalette
    {
        try {
            return app(BrandManager::class)->current()->palette;
        } catch (\Throwable) {
            return new BrandPalette(BrandPalette::PRESETS['zinc']);
        }
    }

    /**
     * The `appearance` group, read through BrandManager so it shares the same
     * cache entry and the same version probe. No extra query.
     *
     * @return array<string,mixed>
     */
    private function raw(): array
    {
        $brand = app(BrandManager::class);

        // Warms the shared cache entry so raw() below is a cache hit, not a query.
        $brand->current();

        $group = $brand->raw()['appearance'] ?? [];

        return is_array($group) ? $group : [];
    }
}
