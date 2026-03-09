import { computed, watchEffect } from 'vue';
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

export function useThemeColors() {
    const page = usePage<PageProps>();
    const siteSettings = computed(() => page.props.siteSettings);

    function applyTheme() {
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

        // Apply new overrides (empty for zinc = use CSS defaults)
        for (const [prop, value] of Object.entries(vars)) {
            root.style.setProperty(prop, value);
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
}
