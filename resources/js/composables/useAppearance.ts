import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { AppearancePayload, AuthAppearancePayload, SurfacePayload } from '@/types';

export type { AppearancePayload, AuthAppearancePayload, SurfacePayload };

const TYPES = ['brand', 'solid', 'gradient', 'pattern', 'image', 'none'] as const;
const SCRIMS = ['none', 'light', 'medium', 'strong'] as const;

/**
 * The third and last transport. If the shared prop is missing and the meta tag
 * is absent or malformed, the guest shell still mounts on this.
 */
export const DEFAULT_APPEARANCE: AppearancePayload = {
    auth: {
        layout: 'split',
        component: 'SplitLayout',
        flip: false,
        show_tagline: true,
        supports_media: true,
        surface: {
            type: 'brand',
            recipe: null,
            scrim: 'medium',
            image_url: null,
            base: 'var(--primary)',
            foreground: 'var(--primary-foreground)',
            contrast: 0,
            css_vars: {},
        },
    },
    page: {
        navbar_translucent: false,
        surface: {
            type: 'none',
            recipe: null,
            scrim: 'medium',
            image_url: null,
            base: 'var(--background)',
            foreground: 'var(--foreground)',
            contrast: 0,
            css_vars: {},
        },
    },
};

/** Only our own custom properties, only string values. A second guard. */
function safeVars(raw: unknown): Record<string, string> {
    if (!raw || typeof raw !== 'object') return {};

    const out: Record<string, string> = {};

    for (const [key, value] of Object.entries(raw as Record<string, unknown>)) {
        if (key.startsWith('--myra-') && typeof value === 'string') out[key] = value;
    }

    return out;
}

function safeSurface(raw: unknown, fallback: SurfacePayload): SurfacePayload {
    if (!raw || typeof raw !== 'object') return fallback;

    const s = raw as Record<string, unknown>;
    const type = TYPES.includes(s.type as never) ? (s.type as SurfacePayload['type']) : fallback.type;

    return {
        type,
        recipe: typeof s.recipe === 'string' && s.recipe !== '' ? s.recipe : null,
        scrim: SCRIMS.includes(s.scrim as never) ? (s.scrim as SurfacePayload['scrim']) : 'medium',
        image_url: typeof s.image_url === 'string' && s.image_url !== '' ? s.image_url : null,
        base: typeof s.base === 'string' && s.base !== '' ? s.base : fallback.base,
        foreground: typeof s.foreground === 'string' && s.foreground !== '' ? s.foreground : fallback.foreground,
        contrast: typeof s.contrast === 'number' && Number.isFinite(s.contrast) ? s.contrast : 0,
        css_vars: type === 'brand' || type === 'none' ? {} : safeVars(s.css_vars),
    };
}

/** Total: any shape of junk resolves to the stock payload rather than throwing. */
export function normaliseAppearance(raw: unknown): AppearancePayload | null {
    if (!raw || typeof raw !== 'object') return null;

    const root = raw as Record<string, unknown>;
    const auth = (root.auth ?? {}) as Record<string, unknown>;
    const page = (root.page ?? {}) as Record<string, unknown>;

    if (!root.auth || typeof root.auth !== 'object') return null;

    return {
        auth: {
            layout: typeof auth.layout === 'string' && auth.layout !== '' ? auth.layout : DEFAULT_APPEARANCE.auth.layout,
            component:
                typeof auth.component === 'string' && auth.component !== ''
                    ? auth.component
                    : DEFAULT_APPEARANCE.auth.component,
            flip: auth.flip === true,
            show_tagline: auth.show_tagline !== false,
            supports_media: auth.supports_media === true,
            surface: safeSurface(auth.surface, DEFAULT_APPEARANCE.auth.surface),
        },
        page: {
            navbar_translucent: page.navbar_translucent === true,
            surface: safeSurface(page.surface, DEFAULT_APPEARANCE.page.surface),
        },
    };
}

/** The payload embedded by app.blade.php, available before Inertia boots. */
export function readAppearanceMeta(): AppearancePayload | null {
    if (typeof document === 'undefined') return null;

    const raw = document.querySelector('meta[name="myra-appearance"]')?.getAttribute('content');
    if (!raw) return null;

    try {
        return normaliseAppearance(JSON.parse(raw));
    } catch {
        return null;
    }
}

export function useAppearance(): {
    appearance: ComputedRef<AppearancePayload>;
    auth: ComputedRef<AuthAppearancePayload>;
    page: ComputedRef<AppearancePayload['page']>;
} {
    let page: ReturnType<typeof usePage> | null = null;

    try {
        page = usePage();
    } catch {
        page = null;
    }

    const meta = readAppearanceMeta();

    const appearance = computed<AppearancePayload>(() => {
        const shared = normaliseAppearance((page?.props as Record<string, unknown> | undefined)?.appearance);

        return shared ?? meta ?? DEFAULT_APPEARANCE;
    });

    return {
        appearance,
        auth: computed(() => appearance.value.auth),
        page: computed(() => appearance.value.page),
    };
}
