import { computed, type ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Brand data, resolved at runtime with a fallback chain.
 *
 * Bundle C owns app/Brand and @/components/brand; this bundle must not
 * hard-import either, so the `brand` prop is read optionally and falls back to
 * the siteSettings shared prop that exists today.
 */
export function useSiteBrand(): {
    name: ComputedRef<string>;
    initial: ComputedRef<string>;
    logoUrl: ComputedRef<string | null>;
} {
    const page = usePage();

    const props = computed<Record<string, any>>(() => (page.props ?? {}) as Record<string, any>);

    const name = computed<string>(() => {
        const value = props.value.brand?.name ?? props.value.siteSettings?.site_name;

        return typeof value === 'string' && value.trim() !== '' ? value : 'App';
    });

    const initial = computed<string>(() => name.value.trim().charAt(0).toUpperCase() || 'A');

    const logoUrl = computed<string | null>(() => {
        const value = props.value.brand?.logoUrl ?? props.value.siteSettings?.logo_url;

        return typeof value === 'string' && value !== '' ? value : null;
    });

    return { name, initial, logoUrl };
}
