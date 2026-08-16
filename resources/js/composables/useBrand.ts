import { computed, getCurrentScope, onScopeDispose, ref, type ComputedRef, type Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { BrandTokens } from '@/types';

/**
 * The brand embedded by app.blade.php. Available before Inertia boots, so the
 * document title and progress bar can be branded on the very first paint.
 */
export function readBrandMeta(): BrandTokens | null {
    if (typeof document === 'undefined') return null;

    const raw = document.querySelector('meta[name="brand"]')?.getAttribute('content');
    if (!raw) return null;

    try {
        return JSON.parse(raw) as BrandTokens;
    } catch {
        return null;
    }
}

const FALLBACK: BrandTokens = {
    enabled: false,
    name: 'Admin',
    short_name: 'Admin',
    tagline: '',
    description: '',
    logo_url: null,
    logo_dark_url: null,
    mark_url: null,
    favicon_url: null,
    og_image_url: null,
    logo_position: 'header',
    initial: 'A',
    palette: { primary: '#18181b', accent: null, sidebar_background: null, sidebar_foreground: null, sidebar_accent: null, preset: 'zinc' },
    typography: { sans: 'figtree', mono: 'ui-monospace' },
    radius: '0.625rem',
    dark_default: false,
    hash: '',
};

/** ONE fallback rule for the whole app: initial-on-primary. */
export function useBrand(): {
    brand: ComputedRef<BrandTokens>;
    name: ComputedRef<string>;
    initial: ComputedRef<string>;
    logoUrl: ComputedRef<string | null>;
    isDark: Ref<boolean>;
} {
    const page = usePage();
    const meta = readBrandMeta();

    const isDark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

    if (typeof MutationObserver !== 'undefined' && typeof document !== 'undefined') {
        const observer = new MutationObserver(() => {
            isDark.value = document.documentElement.classList.contains('dark');
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        if (getCurrentScope()) {
            onScopeDispose(() => observer.disconnect());
        }
    }

    const brand = computed<BrandTokens>(() => {
        const shared = (page.props as any)?.brand as BrandTokens | undefined;
        if (shared) return shared;
        if (meta) return meta;

        const site = ((page.props as any)?.siteSettings ?? {}) as Record<string, any>;
        const name = (site.site_name as string) || FALLBACK.name;

        return {
            ...FALLBACK,
            name,
            short_name: name,
            initial: name.trim().charAt(0).toUpperCase() || '?',
            logo_url: (site.logo_url as string) ?? null,
            favicon_url: (site.favicon_url as string) ?? null,
            logo_position: (site.logo_position as string) || 'header',
        };
    });

    return {
        brand,
        name: computed(() => brand.value.name),
        initial: computed(() => brand.value.initial),
        logoUrl: computed(() => (isDark.value ? brand.value.logo_dark_url ?? brand.value.logo_url : brand.value.logo_url)),
        isDark,
    };
}

/** Inline style block that scopes a token set to one element (preview mockups). */
export function brandScopeStyle(tokens: BrandTokens | null): Record<string, string> {
    if (!tokens) return {};

    const primary = tokens.palette.primary;

    return {
        '--primary': primary,
        '--ring': primary,
        '--sidebar-primary': primary,
        '--radius': tokens.radius,
        '--font-sans': tokens.typography.sans,
    };
}
