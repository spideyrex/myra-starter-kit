<?php

namespace App\Brand;

/**
 * Self-hosted / system stacks only. Production CSP sets `font-src 'self' data:`,
 * so a CDN family would work in dev and silently fail in production.
 */
final readonly class BrandTypography
{
    public const SANS = ['figtree', 'inter', 'system', 'geist'];

    public const MONO = ['ui-monospace', 'jetbrains-mono', 'system'];

    public const RADII = ['0', '0.3rem', '0.5rem', '0.625rem', '0.75rem', '1rem'];

    /** Families that ship as a woff2 under public/fonts and want a preload link. */
    public const SELF_HOSTED = [
        'figtree' => '/fonts/figtree-variable.woff2',
        'inter' => '/fonts/inter-variable.woff2',
        'geist' => '/fonts/geist-variable.woff2',
        'jetbrains-mono' => '/fonts/jetbrains-mono-variable.woff2',
    ];

    private const STACKS = [
        'figtree' => "Figtree, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'inter' => "Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'geist' => "Geist, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'system' => "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'ui-monospace' => "ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace",
        'jetbrains-mono' => "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace",
    ];

    public function __construct(
        public string $sans = 'figtree',
        public string $mono = 'ui-monospace',
    ) {}

    public static function make(?string $sans, ?string $mono): self
    {
        return new self(
            in_array($sans, self::SANS, true) ? $sans : 'figtree',
            in_array($mono, self::MONO, true) ? $mono : 'ui-monospace',
        );
    }

    public function stack(string $role = 'sans'): string
    {
        $family = $role === 'mono' ? $this->mono : $this->sans;

        if ($family === 'system') {
            return $role === 'mono' ? self::STACKS['ui-monospace'] : self::STACKS['system'];
        }

        return self::STACKS[$family] ?? self::STACKS['system'];
    }

    /** CSS family name for each self-hosted key. */
    public const FACE_NAMES = [
        'figtree' => 'Figtree',
        'inter' => 'Inter',
        'geist' => 'Geist',
        'jetbrains-mono' => 'JetBrains Mono',
    ];

    /**
     * Only families whose woff2 is actually present under public/fonts. A stack
     * whose file is missing degrades to the system tail rather than 404ing.
     *
     * @return array<int,array{href:string,family:string,face:string}>
     */
    public function preloads(): array
    {
        $out = [];

        foreach ([$this->sans, $this->mono] as $family) {
            if (! isset(self::SELF_HOSTED[$family])) {
                continue;
            }

            $href = self::SELF_HOSTED[$family];

            if (is_file(public_path(ltrim($href, '/')))) {
                $out[] = ['href' => $href, 'family' => $family, 'face' => self::FACE_NAMES[$family]];
            }
        }

        return $out;
    }

    public static function radius(?string $value): string
    {
        return in_array($value, self::RADII, true) ? $value : '0.625rem';
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return ['sans' => $this->sans, 'mono' => $this->mono];
    }
}
