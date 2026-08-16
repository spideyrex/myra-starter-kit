<?php

namespace App\Brand;

final readonly class BrandPalette
{
    /** The 10 legacy theme names from resources/js/composables/useThemeColors.ts. */
    public const PRESETS = [
        'zinc' => '#18181b',
        'slate' => '#334155',
        'stone' => '#44403c',
        'red' => '#ef4444',
        'rose' => '#e11d48',
        'orange' => '#f97316',
        'green' => '#22c55e',
        'blue' => '#3b82f6',
        'violet' => '#8b5cf6',
        'yellow' => '#eab308',
    ];

    /** Deterministic hue offsets for derived chart series. */
    private const HUE_STEPS = [0, 42, 84, 126, 168, 210, 252, 294];

    public function __construct(
        public string $primaryHex,
        public ?string $accentHex = null,
        public ?string $sidebarBackgroundHex = null,
        public ?string $sidebarForegroundHex = null,
        public ?string $sidebarAccentHex = null,
        public string $preset = 'zinc',
    ) {}

    public static function fromPreset(string $name): self
    {
        $name = isset(self::PRESETS[$name]) ? $name : 'zinc';

        return new self(self::PRESETS[$name], null, null, null, null, $name);
    }

    /** @return array<string,string> token => oklch() */
    public function light(): array
    {
        return $this->tokens(false);
    }

    /** @return array<string,string> token => oklch() */
    public function dark(): array
    {
        return $this->tokens(true);
    }

    /** Resolved hex for a token — used by the manifest, theme-color and email. */
    public function hex(string $token): string
    {
        return match ($token) {
            'primary' => $this->primaryHex,
            'primary-foreground' => $this->foregroundOn($this->primaryHex),
            'accent' => $this->accentHex ?? $this->primaryHex,
            'accent-foreground' => $this->foregroundOn($this->accentHex ?? $this->primaryHex),
            'sidebar' => $this->sidebarBackgroundHex ?? '#fafafa',
            'sidebar-dark' => $this->sidebarBackgroundHex ?? '#18181b',
            'sidebar-foreground' => $this->sidebarForegroundHex ?? '#18181b',
            'background' => '#ffffff',
            'background-dark' => '#09090b',
            default => $this->primaryHex,
        };
    }

    /**
     * @return array<int,array{0:float,1:float,2:float}> normalised RGB for ChartVector
     */
    public function chartSeries(int $n = 8): array
    {
        $out = [];

        for ($i = 0; $i < max(1, $n); $i++) {
            $out[] = Color::toNormalisedRgb($this->seriesHex($i));
        }

        return $out;
    }

    public function seriesHex(int $index): string
    {
        $step = self::HUE_STEPS[$index % count(self::HUE_STEPS)];
        $cycle = intdiv($index, count(self::HUE_STEPS));

        return Color::rotateHueHex(
            $cycle === 0 ? $this->primaryHex : Color::shiftHex($this->primaryHex, $cycle % 2 === 1 ? 0.12 : -0.12),
            (float) $step,
        );
    }

    /** WCAG-checked black/white pick — measured, never a lightness heuristic alone. */
    public function foregroundOn(string $hex): string
    {
        return Color::contrastRatio($hex, '#ffffff') >= Color::contrastRatio($hex, '#000000')
            ? '#ffffff'
            : '#000000';
    }

    /** The achieved ratio for the picked foreground, for the UI warning. */
    public function contrastOn(string $hex): float
    {
        return round(Color::contrastRatio($hex, $this->foregroundOn($hex)), 2);
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return [
            'primary' => $this->primaryHex,
            'accent' => $this->accentHex,
            'sidebar_background' => $this->sidebarBackgroundHex,
            'sidebar_foreground' => $this->sidebarForegroundHex,
            'sidebar_accent' => $this->sidebarAccentHex,
            'preset' => $this->preset,
        ];
    }

    /** @return array<string,string> */
    private function tokens(bool $dark): array
    {
        $primary = $dark && ! Color::isLight($this->primaryHex)
            ? Color::shiftHex($this->primaryHex, 0.08)
            : $this->primaryHex;

        $out = [
            '--primary' => Color::hexToOklch($primary),
            '--primary-foreground' => Color::hexToOklch($this->foregroundOn($primary)),
            '--ring' => Color::hexToOklch($primary),
            '--sidebar-primary' => Color::hexToOklch($primary),
            '--sidebar-primary-foreground' => Color::hexToOklch($this->foregroundOn($primary)),
        ];

        if ($this->accentHex !== null) {
            $accent = $dark && ! Color::isLight($this->accentHex)
                ? Color::shiftHex($this->accentHex, 0.08)
                : $this->accentHex;

            $out['--accent'] = Color::hexToOklch($accent);
            $out['--accent-foreground'] = Color::hexToOklch($this->foregroundOn($accent));
        }

        if ($this->sidebarBackgroundHex !== null) {
            $bg = $this->sidebarBackgroundHex;
            $out['--sidebar'] = Color::hexToOklch($bg);
            $out['--sidebar-border'] = Color::adjustLightness($bg, Color::isLight($bg) ? -0.08 : 0.08);
        }

        if ($this->sidebarForegroundHex !== null) {
            $out['--sidebar-foreground'] = Color::hexToOklch($this->sidebarForegroundHex);
        }

        if ($this->sidebarAccentHex !== null) {
            $out['--sidebar-accent'] = Color::hexToOklch($this->sidebarAccentHex);
            $out['--sidebar-accent-foreground'] = Color::hexToOklch($this->foregroundOn($this->sidebarAccentHex));
        }

        foreach (range(1, 5) as $i) {
            $hex = $this->seriesHex($i - 1);
            $out['--chart-'.$i] = Color::hexToOklch($dark ? Color::shiftHex($hex, 0.06) : $hex);
        }

        return $out;
    }
}
