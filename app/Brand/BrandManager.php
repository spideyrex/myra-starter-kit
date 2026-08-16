<?php

namespace App\Brand;

use App\Settings\MaintenanceSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class BrandManager
{
    public const TOKENS_KEY = 'myra.brand.v1';

    public const VERSION_KEY = 'myra.brand.version';

    public const TOKENS_TTL = 3600;

    /** Groups that participate in brand resolution. */
    private const GROUPS = ['brand', 'general', 'appearance', 'seo'];

    private ?Brand $memo = null;

    private ?int $memoVersion = null;

    public function current(): Brand
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        try {
            $version = $this->version();
            $cached = cache()->get(self::TOKENS_KEY);

            if (! is_array($cached) || ($cached['version'] ?? null) !== $version) {
                $cached = ['version' => $version, 'raw' => $this->readRaw()];
                cache()->put(self::TOKENS_KEY, $cached, (int) config('myra.brand.cache_ttl', self::TOKENS_TTL));
            }

            return $this->memo = $this->build($cached['raw'], $version);
        } catch (\Throwable) {
            return $this->memo = Brand::fallback();
        }
    }

    /**
     * A digest over the settings rows brand resolution reads. One indexed
     * lookup per node per probe window, so a foreign writer (tinker, a seeder,
     * a second web node) converges within probe_ttl rather than cache_ttl.
     */
    public function version(): int
    {
        $ttl = (int) config('myra.brand.probe_ttl', 60);

        if ($ttl <= 0) {
            // Probe disabled: the event path is the only invalidation.
            return $this->memoVersion ??= (int) cache()->rememberForever(self::VERSION_KEY, fn () => $this->probe());
        }

        return (int) cache()->remember(self::VERSION_KEY, $ttl, fn () => $this->probe());
    }

    public function hash(): string
    {
        return $this->current()->hash;
    }

    /** THE only invalidation path. */
    public function forget(): void
    {
        $this->memo = null;
        $this->memoVersion = null;

        cache()->forget(self::TOKENS_KEY);
        cache()->forget(self::VERSION_KEY);
        cache()->forget('site_settings_shared');
        cache()->forget('myra_build_id');
    }

    public function styleTag(): HtmlString
    {
        $brand = $this->current();

        if (! $brand->enabled) {
            return new HtmlString('');
        }

        $render = static function (array $vars): string {
            $out = '';
            foreach ($vars as $prop => $value) {
                $out .= $prop.':'.preg_replace('/[^a-zA-Z0-9 ,.%()\/\'\-_]/', '', (string) $value).';';
            }

            return $out;
        };

        $faces = '';
        foreach ($brand->typography->preloads() as $font) {
            $faces .= '@font-face{font-family:"'.$font['face'].'";src:url("'.$font['href'].'") format("woff2");'
                .'font-weight:100 900;font-style:normal;font-display:swap;}';
        }

        return new HtmlString(
            '<style id="myra-brand">'.$faces.':root{'.$render($brand->cssVariables(false)).'}'
            .'.dark{'.$render($brand->cssVariables(true)).'}</style>'
        );
    }

    /**
     * Blocking, pre-paint. A stored preference always wins; with none it honours
     * dark_default and then the OS preference — it never fights the user.
     */
    public function bootScriptTag(): HtmlString
    {
        $brand = $this->current();

        if (! $brand->enabled) {
            return new HtmlString('');
        }

        $default = $brand->darkDefault ? 'true' : 'false';

        return new HtmlString(
            '<script>(function(){var s=null;try{s=localStorage.getItem("theme")}catch(e){}'
            .'var m=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches;'
            .'if(s==="dark"||(!s&&('.$default.'||m))){document.documentElement.classList.add("dark")}'
            .'else if(s==="light"){document.documentElement.classList.remove("dark")}})();</script>'
        );
    }

    /**
     * @return array<string,string> app_name, site_name, site_url, support_email,
     *                              year, logo_url, primary
     */
    public function mailTokens(): array
    {
        $brand = $this->current();

        if (! $brand->enabled) {
            return [];
        }

        $raw = $this->raw();

        return [
            'app_name' => $brand->name,
            'site_name' => $brand->name,
            'site_url' => (string) ($raw['general']['site_url'] ?? config('app.url')),
            'support_email' => (string) ($raw['general']['admin_email'] ?? ''),
            'year' => (string) now()->year,
            'logo_url' => (string) ($brand->logoUrl ?? ''),
            'primary' => $brand->palette->primaryHex,
        ];
    }

    /** CID-embed / PDF raster bytes. Never a remote URL. */
    public function logoBytes(string $variant = 'email'): ?string
    {
        $brand = $this->current();

        if (! $brand->enabled) {
            return null;
        }

        $pipeline = app(BrandAssetPipeline::class);
        $key = $variant === 'pdf' ? 'logo-pdf' : 'logo-email';

        return $pipeline->derivativeBytes($key)
            ?? $pipeline->markPng($variant === 'pdf' ? 480 : 320, $variant === 'pdf' ? 480 : 320);
    }

    /** @return array{producer:string,author:string,footer:string,series:array} */
    public function pdfMeta(): array
    {
        $brand = $this->current();

        if (! $brand->enabled) {
            // The pre-2.6 baseline, byte for byte.
            return [
                'producer' => 'Myra',
                'author' => (string) config('app.name'),
                'footer' => (string) config('app.name'),
                'series' => [],
            ];
        }

        return [
            'producer' => $brand->name,
            'author' => $brand->name,
            'footer' => $brand->name,
            'series' => $brand->palette->chartSeries(8),
        ];
    }

    /** The PWA manifest document. */
    public function manifest(): array
    {
        $brand = $this->current();

        if (! $brand->enabled) {
            $static = @file_get_contents(public_path('manifest.webmanifest'));
            $decoded = $static !== false ? json_decode($static, true) : null;

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $icons = app(BrandAssetPipeline::class)->iconManifest();

        return [
            'name' => $brand->name,
            'short_name' => $brand->shortName !== '' ? $brand->shortName : $brand->name,
            'description' => $brand->description,
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => $brand->palette->hex('background'),
            'theme_color' => $brand->palette->hex('primary'),
            'icons' => [
                ['src' => $icons['icon-192'] ?? route('brand.icon', ['size' => '192']), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icons['icon-512'] ?? route('brand.icon', ['size' => '512']), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icons['icon-maskable-512'] ?? route('brand.icon', ['size' => '512']), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
    }

    public function iconLinks(): HtmlString
    {
        $brand = $this->current();
        $raw = $this->raw();

        if (! $brand->enabled) {
            $legacy = $raw['appearance']['favicon_path'] ?? null;

            return new HtmlString(
                $legacy ? '<link rel="icon" href="'.e(Storage::disk('public')->url($legacy)).'">' : ''
            );
        }

        $icons = app(BrandAssetPipeline::class)->iconManifest();
        $v = '?v='.$brand->hash;
        $out = '';

        if ($brand->faviconUrl !== null) {
            $out .= '<link rel="icon" href="'.e($brand->faviconUrl.$v).'">';
        } else {
            $out .= '<link rel="icon" type="image/svg+xml" href="'.e(route('brand.favicon').$v).'">';
        }

        foreach (['favicon-16' => '16x16', 'favicon-32' => '32x32'] as $key => $sizes) {
            if (isset($icons[$key])) {
                $out .= '<link rel="icon" type="image/png" sizes="'.$sizes.'" href="'.e($icons[$key].$v).'">';
            }
        }

        $out .= '<link rel="apple-touch-icon" sizes="180x180" href="'
            .e(($icons['apple-touch-180'] ?? route('brand.icon', ['size' => '192'])).$v).'">';

        return new HtmlString($out);
    }

    /** @return array<int,array{attr:string,key:string,content:string}> */
    public function metaTags(): array
    {
        $brand = $this->current();

        if (! $brand->enabled) {
            return [];
        }

        $raw = $this->raw();
        $title = (string) ($raw['seo']['meta_title'] ?? '') ?: $brand->name;
        $description = (string) ($raw['seo']['meta_description'] ?? '') ?: $brand->description;
        $image = $brand->ogImageUrl;

        $tags = [
            ['attr' => 'name', 'key' => 'description', 'content' => $description],
            ['attr' => 'property', 'key' => 'og:site_name', 'content' => $brand->name],
            ['attr' => 'property', 'key' => 'og:title', 'content' => $title],
            ['attr' => 'property', 'key' => 'og:description', 'content' => $description],
            ['attr' => 'property', 'key' => 'og:type', 'content' => 'website'],
            ['attr' => 'name', 'key' => 'twitter:card', 'content' => $image !== null ? 'summary_large_image' : 'summary'],
            ['attr' => 'name', 'key' => 'twitter:title', 'content' => $title],
            ['attr' => 'name', 'key' => 'twitter:description', 'content' => $description],
        ];

        if ($image !== null) {
            $tags[] = ['attr' => 'property', 'key' => 'og:image', 'content' => $image];
            $tags[] = ['attr' => 'name', 'key' => 'twitter:image', 'content' => $image];
        }

        return array_values(array_filter($tags, static fn (array $t) => $t['content'] !== ''));
    }

    /** Curated PUBLIC projection — never admin_email, site_url or timezone. */
    public function toInertiaProp(): array
    {
        return $this->current()->toArray();
    }

    /** Maintenance copy, so 503 finally renders what an operator configured. */
    public function maintenanceMessage(): string
    {
        try {
            return (string) app(MaintenanceSettings::class)->message;
        } catch (\Throwable) {
            return '';
        }
    }

    /** Resolve UNSAVED input into real tokens. Never persists, never caches. */
    public function fromInput(array $input): Brand
    {
        $raw = $this->raw();
        $merged = array_merge($raw['brand'] ?? [], array_filter(
            $input,
            static fn ($v, $k) => $v !== null || array_key_exists($k, $input),
            ARRAY_FILTER_USE_BOTH,
        ));
        $merged['enabled'] = (bool) ($input['enabled'] ?? true);

        return $this->build(array_merge($raw, ['brand' => $merged]), $this->version());
    }

    /** @return array<string,array<string,mixed>> */
    public function raw(): array
    {
        $cached = cache()->get(self::TOKENS_KEY);

        if (is_array($cached) && isset($cached['raw'])) {
            return $cached['raw'];
        }

        try {
            return $this->readRaw();
        } catch (\Throwable) {
            return [];
        }
    }

    private function probe(): int
    {
        $rows = DB::table('settings')
            ->whereIn('group', self::GROUPS)
            ->orderBy('group')
            ->orderBy('name')
            ->get(['group', 'name', 'payload']);

        $digest = '';
        foreach ($rows as $row) {
            $digest .= $row->group.'|'.$row->name.'|'.$row->payload."\n";
        }

        return (int) sprintf('%u', crc32($digest));
    }

    /** @return array<string,array<string,mixed>> */
    private function readRaw(): array
    {
        $out = [];

        foreach (DB::table('settings')->whereIn('group', self::GROUPS)->get(['group', 'name', 'payload']) as $row) {
            $out[$row->group][$row->name] = json_decode((string) $row->payload, true);
        }

        return $out;
    }

    private function build(array $raw, int $version): Brand
    {
        $brand = $raw['brand'] ?? [];
        $general = $raw['general'] ?? [];
        $appearance = $raw['appearance'] ?? [];

        $enabled = (bool) ($brand['enabled'] ?? false);

        // Disabled falls through to today's exact source of truth.
        $name = (string) ($enabled ? ($brand['name'] ?? '') : ($general['site_name'] ?? '')) ?: (string) config('app.name', 'Admin');
        $logoPath = $enabled ? ($brand['logo_path'] ?? null) : ($appearance['logo_path'] ?? null);
        $faviconPath = $enabled ? ($brand['favicon_path'] ?? null) : ($appearance['favicon_path'] ?? null);
        $primary = Color::normalise(
            (string) ($enabled ? ($brand['primary'] ?? '') : ($appearance['primary_color'] ?? '')),
            '#18181b',
        );

        $palette = new BrandPalette(
            primaryHex: $primary,
            accentHex: $enabled ? $this->hexOrNull($brand['accent'] ?? null) : null,
            sidebarBackgroundHex: $this->hexOrNull($enabled ? ($brand['sidebar_background'] ?? null) : ($appearance['sidebar_background'] ?? null)),
            sidebarForegroundHex: $this->hexOrNull($enabled ? ($brand['sidebar_foreground'] ?? null) : ($appearance['sidebar_foreground'] ?? null)),
            sidebarAccentHex: $this->hexOrNull($enabled ? ($brand['sidebar_accent'] ?? null) : ($appearance['sidebar_accent'] ?? null)),
            preset: (string) ($enabled ? ($brand['preset'] ?? 'zinc') : ($appearance['theme'] ?? 'zinc')),
        );

        $shortName = (string) ($brand['short_name'] ?? '') ?: mb_substr($name, 0, 24);

        return new Brand(
            enabled: $enabled,
            name: $name,
            shortName: $shortName,
            tagline: (string) ($enabled ? ($brand['tagline'] ?? '') : ($general['login_tagline'] ?? '')),
            description: (string) ($enabled ? ($brand['description'] ?? '') : ($general['site_description'] ?? '')),
            logoUrl: $this->url($logoPath),
            logoDarkUrl: $enabled ? $this->url($brand['logo_dark_path'] ?? null) : null,
            markUrl: $enabled ? $this->url($brand['mark_path'] ?? null) : null,
            faviconUrl: $this->url($faviconPath),
            ogImageUrl: $enabled ? $this->url($brand['og_image_path'] ?? ($raw['seo']['og_image_path'] ?? null)) : null,
            logoPosition: (string) ($enabled ? ($brand['logo_position'] ?? 'header') : ($appearance['logo_position'] ?? 'header')),
            palette: $palette,
            typography: BrandTypography::make($brand['font_sans'] ?? null, $brand['font_mono'] ?? null),
            radius: BrandTypography::radius($brand['radius'] ?? null),
            darkDefault: (bool) ($brand['dark_default'] ?? false),
            hash: substr(md5($version.'|'.json_encode($raw)), 0, 8),
        );
    }

    private function hexOrNull(?string $value): ?string
    {
        return $value !== null && Color::isValidHex($value) ? strtolower($value) : null;
    }

    private function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        try {
            return Storage::disk('public')->url($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
