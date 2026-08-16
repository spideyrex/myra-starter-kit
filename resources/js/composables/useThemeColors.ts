import { computed, getCurrentScope, onScopeDispose, watchEffect } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

interface ThemePreset {
    label: string;
    color: string; // preview hex color for the UI
    light: Record<string, string>;
    dark: Record<string, string>;
}

export const themePresets: Record<string, ThemePreset> = {
    zinc: {
        label: 'Zinc',
        color: '#18181b',
        light: {},
        dark: {},
    },
    slate: {
        label: 'Slate',
        color: '#334155',
        light: {
            '--primary': 'oklch(0.279 0.041 260.031)',
            '--primary-foreground': 'oklch(0.985 0.002 247.858)',
            '--ring': 'oklch(0.279 0.041 260.031)',
            '--sidebar-primary': 'oklch(0.279 0.041 260.031)',
            '--sidebar-primary-foreground': 'oklch(0.985 0.002 247.858)',
        },
        dark: {
            '--primary': 'oklch(0.929 0.013 255.508)',
            '--primary-foreground': 'oklch(0.208 0.042 265.755)',
            '--ring': 'oklch(0.929 0.013 255.508)',
            '--sidebar-primary': 'oklch(0.488 0.243 264.376)',
            '--sidebar-primary-foreground': 'oklch(0.985 0.002 247.858)',
        },
    },
    stone: {
        label: 'Stone',
        color: '#44403c',
        light: {
            '--primary': 'oklch(0.268 0.007 34.298)',
            '--primary-foreground': 'oklch(0.985 0.001 106.423)',
            '--ring': 'oklch(0.268 0.007 34.298)',
            '--sidebar-primary': 'oklch(0.268 0.007 34.298)',
            '--sidebar-primary-foreground': 'oklch(0.985 0.001 106.423)',
        },
        dark: {
            '--primary': 'oklch(0.923 0.003 48.717)',
            '--primary-foreground': 'oklch(0.216 0.006 56.043)',
            '--ring': 'oklch(0.923 0.003 48.717)',
            '--sidebar-primary': 'oklch(0.488 0.243 264.376)',
            '--sidebar-primary-foreground': 'oklch(0.985 0.001 106.423)',
        },
    },
    red: {
        label: 'Red',
        color: '#ef4444',
        light: {
            '--primary': 'oklch(0.577 0.245 27.325)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.577 0.245 27.325)',
            '--sidebar-primary': 'oklch(0.577 0.245 27.325)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
        dark: {
            '--primary': 'oklch(0.637 0.237 25.331)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.637 0.237 25.331)',
            '--sidebar-primary': 'oklch(0.637 0.237 25.331)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
    },
    rose: {
        label: 'Rose',
        color: '#e11d48',
        light: {
            '--primary': 'oklch(0.553 0.213 1.279)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.553 0.213 1.279)',
            '--sidebar-primary': 'oklch(0.553 0.213 1.279)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
        dark: {
            '--primary': 'oklch(0.612 0.209 0.541)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.612 0.209 0.541)',
            '--sidebar-primary': 'oklch(0.612 0.209 0.541)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
    },
    orange: {
        label: 'Orange',
        color: '#f97316',
        light: {
            '--primary': 'oklch(0.646 0.222 41.116)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.646 0.222 41.116)',
            '--sidebar-primary': 'oklch(0.646 0.222 41.116)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
        dark: {
            '--primary': 'oklch(0.686 0.222 41.116)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.686 0.222 41.116)',
            '--sidebar-primary': 'oklch(0.686 0.222 41.116)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
    },
    green: {
        label: 'Green',
        color: '#22c55e',
        light: {
            '--primary': 'oklch(0.596 0.145 163.225)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.596 0.145 163.225)',
            '--sidebar-primary': 'oklch(0.596 0.145 163.225)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
        dark: {
            '--primary': 'oklch(0.696 0.17 162.48)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.696 0.17 162.48)',
            '--sidebar-primary': 'oklch(0.696 0.17 162.48)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
    },
    blue: {
        label: 'Blue',
        color: '#3b82f6',
        light: {
            '--primary': 'oklch(0.546 0.245 262.881)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.546 0.245 262.881)',
            '--sidebar-primary': 'oklch(0.546 0.245 262.881)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
        dark: {
            '--primary': 'oklch(0.623 0.214 259.815)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.623 0.214 259.815)',
            '--sidebar-primary': 'oklch(0.623 0.214 259.815)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
    },
    violet: {
        label: 'Violet',
        color: '#8b5cf6',
        light: {
            '--primary': 'oklch(0.511 0.262 276.966)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.511 0.262 276.966)',
            '--sidebar-primary': 'oklch(0.511 0.262 276.966)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
        dark: {
            '--primary': 'oklch(0.591 0.25 276.966)',
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--ring': 'oklch(0.591 0.25 276.966)',
            '--sidebar-primary': 'oklch(0.591 0.25 276.966)',
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        },
    },
    yellow: {
        label: 'Yellow',
        color: '#eab308',
        light: {
            '--primary': 'oklch(0.795 0.184 86.047)',
            '--primary-foreground': 'oklch(0.205 0 0)',
            '--ring': 'oklch(0.795 0.184 86.047)',
            '--sidebar-primary': 'oklch(0.795 0.184 86.047)',
            '--sidebar-primary-foreground': 'oklch(0.205 0 0)',
        },
        dark: {
            '--primary': 'oklch(0.795 0.184 86.047)',
            '--primary-foreground': 'oklch(0.205 0 0)',
            '--ring': 'oklch(0.795 0.184 86.047)',
            '--sidebar-primary': 'oklch(0.795 0.184 86.047)',
            '--sidebar-primary-foreground': 'oklch(0.205 0 0)',
        },
    },
};

// CSS vars that themes may override — used to clear previous theme
const themeVars = [
    '--primary', '--primary-foreground', '--ring',
    '--sidebar-primary', '--sidebar-primary-foreground',
];

// Sidebar CSS vars managed separately from theme presets
const sidebarVars = [
    '--sidebar', '--sidebar-foreground', '--sidebar-accent',
    '--sidebar-accent-foreground', '--sidebar-border',
];

/** Convert a hex color (#RRGGBB) to an oklch() string. */
export function hexToOklch(hex: string): string {
    // Parse hex to sRGB [0-1]
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;

    // sRGB → linear RGB
    const toLinear = (c: number) => c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    const lr = toLinear(r);
    const lg = toLinear(g);
    const lb = toLinear(b);

    // Linear RGB → OKLab
    const l_ = Math.cbrt(0.4122214708 * lr + 0.5363325363 * lg + 0.0514459929 * lb);
    const m_ = Math.cbrt(0.2119034982 * lr + 0.6806995451 * lg + 0.1073969566 * lb);
    const s_ = Math.cbrt(0.0883024619 * lr + 0.2817188376 * lg + 0.6299787005 * lb);

    const L = 0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_;
    const a = 1.9779984951 * l_ - 2.4285922050 * m_ + 0.4505937099 * s_;
    const bOk = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.8086757660 * s_;

    // OKLab → OKLCH
    const C = Math.sqrt(a * a + bOk * bOk);
    let H = Math.atan2(bOk, a) * (180 / Math.PI);
    if (H < 0) H += 360;

    return `oklch(${L.toFixed(3)} ${C.toFixed(3)} ${H.toFixed(1)})`;
}

/** Returns true if the hex color is perceptually light (L > 0.6). */
export function isLightColor(hex: string): boolean {
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;

    const toLinear = (c: number) => c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    const lr = toLinear(r);
    const lg = toLinear(g);
    const lb = toLinear(b);

    const l_ = Math.cbrt(0.4122214708 * lr + 0.5363325363 * lg + 0.0514459929 * lb);
    const m_ = Math.cbrt(0.2119034982 * lr + 0.6806995451 * lg + 0.1073969566 * lb);
    const s_ = Math.cbrt(0.0883024619 * lr + 0.2817188376 * lg + 0.6299787005 * lb);

    const L = 0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_;
    return L > 0.6;
}

/** Darken or lighten a hex color by adjusting OKLab L, returning an oklch() string. */
// >>> MYRA v2.6 [C] START — exported so tests/js/brandColorParity.spec.ts can sweep
// the REAL implementation App\Brand\Color is asserted against.
export function adjustLightness(hex: string, delta: number): string {
// <<< MYRA v2.6 [C] END
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;

    const toLinear = (c: number) => c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    const lr = toLinear(r);
    const lg = toLinear(g);
    const lb = toLinear(b);

    const l_ = Math.cbrt(0.4122214708 * lr + 0.5363325363 * lg + 0.0514459929 * lb);
    const m_ = Math.cbrt(0.2119034982 * lr + 0.6806995451 * lg + 0.1073969566 * lb);
    const s_ = Math.cbrt(0.0883024619 * lr + 0.2817188376 * lg + 0.6299787005 * lb);

    let L = 0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_;
    const a = 1.9779984951 * l_ - 2.4285922050 * m_ + 0.4505937099 * s_;
    const bOk = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.8086757660 * s_;

    L = Math.max(0, Math.min(1, L + delta));
    const C = Math.sqrt(a * a + bOk * bOk);
    let H = Math.atan2(bOk, a) * (180 / Math.PI);
    if (H < 0) H += 360;

    return `oklch(${L.toFixed(3)} ${C.toFixed(3)} ${H.toFixed(1)})`;
}

export function useThemeColors() {
    const page = usePage<PageProps>();
    const siteSettings = computed(() => page.props.siteSettings);

    // >>> MYRA v2.6 [C] START
    // When app.blade.php emitted the tokens server-side there is nothing to do:
    // the brand is already correct before first paint, and re-applying here
    // would overwrite it with the legacy preset.
    function serverEmitted(): boolean {
        return typeof document !== 'undefined' && document.getElementById('myra-brand') !== null;
    }
    // <<< MYRA v2.6 [C] END

    function applyTheme() {
        if (serverEmitted()) return;

        const themeName = siteSettings.value?.theme || 'zinc';
        const preset = themePresets[themeName];
        if (!preset) return;

        const isDark = document.documentElement.classList.contains('dark');
        const vars = isDark ? preset.dark : preset.light;
        const root = document.documentElement;

        // Clear any previous theme overrides
        for (const v of themeVars) {
            root.style.removeProperty(v);
        }

        // Clear any previous sidebar color overrides
        for (const v of sidebarVars) {
            root.style.removeProperty(v);
        }

        // Apply theme preset overrides (empty for zinc = use CSS defaults)
        for (const [prop, value] of Object.entries(vars)) {
            root.style.setProperty(prop, value);
        }

        // Apply custom sidebar colors (layered on top of theme)
        const bg = siteSettings.value?.sidebar_background;
        const fg = siteSettings.value?.sidebar_foreground;
        const accent = siteSettings.value?.sidebar_accent;

        if (bg) {
            root.style.setProperty('--sidebar', hexToOklch(bg));
            // Derive border: slightly lighter/darker than background
            root.style.setProperty('--sidebar-border', adjustLightness(bg, isLightColor(bg) ? -0.08 : 0.08));
        }

        if (fg) {
            root.style.setProperty('--sidebar-foreground', hexToOklch(fg));
        }

        if (accent) {
            root.style.setProperty('--sidebar-accent', hexToOklch(accent));
            // Auto-contrast foreground for accent
            root.style.setProperty('--sidebar-accent-foreground', isLightColor(accent) ? 'oklch(0.205 0 0)' : 'oklch(0.985 0 0)');
        }
    }

    // Apply when siteSettings change
    watchEffect(() => {
        applyTheme();
    });

    // Re-apply when dark mode toggles
    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            if (m.attributeName === 'class') {
                applyTheme();
            }
        }
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    // >>> MYRA v2.6 [C] START — one observer leaked per calling component until now.
    if (getCurrentScope()) {
        onScopeDispose(() => observer.disconnect());
    }
    // <<< MYRA v2.6 [C] END

    return { applyTheme, observer };
}
