<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { safeSrc } from '@/composables/useSafeUrl';
import type { PageSurfacePayload, PageSurfaceScrim, PageSurfaceType } from './surface';

/**
 * What sits BEHIND the whole public page — navbar, sections and footer alike.
 *
 * It wraps the template root, which is above the legacy/page-builder branch
 * inside TemplateBody, so both render paths get the identical surface with no
 * branch logic of their own.
 *
 * With the default `none` it renders today's bare root div and nothing else —
 * byte-identical to the root every template carried before.
 *
 * That note lives here, not above the template's first element: a comment at
 * the top level of <template> makes the component MULTI-ROOT, which silently
 * kills attribute fallthrough and hands the tests a fragment instead of the div.
 */
const props = defineProps<{
    /** Normally read off the page props; a prop is accepted for previews and tests. */
    surface?: unknown;
    navbarTranslucent?: boolean;
}>();

const TYPES: PageSurfaceType[] = ['brand', 'solid', 'gradient', 'pattern', 'image', 'none'];
const SCRIMS: PageSurfaceScrim[] = ['none', 'light', 'medium', 'strong'];

const INERT: PageSurfacePayload = {
    type: 'none',
    recipe: null,
    scrim: 'medium',
    image_url: null,
    base: '',
    foreground: '',
    contrast: 0,
    css_vars: {},
};

/** Only our own custom properties, only characters CSS values may hold. */
const VAR_NAME = /^--myra-page-[a-z0-9-]+$/;
const VAR_VALUE = /[^a-zA-Z0-9 ,.%()\/'\-_]/g;

function safeVars(raw: unknown): Record<string, string> {
    const out: Record<string, string> = {};

    if (!raw || typeof raw !== 'object') return out;

    for (const [name, value] of Object.entries(raw as Record<string, unknown>)) {
        if (!VAR_NAME.test(name)) continue;
        if (typeof value !== 'string' && typeof value !== 'number') continue;

        const clean = String(value).replace(VAR_VALUE, '');
        if (clean !== '') out[name] = clean;
    }

    return out;
}

/** Total: null, a string, a half-saved row and hostile junk all land on `none`. */
function normalise(raw: unknown): PageSurfacePayload {
    if (!raw || typeof raw !== 'object') return INERT;

    const row = raw as Record<string, unknown>;
    const type = TYPES.find(candidate => candidate === row.type) ?? 'none';
    const scrim = SCRIMS.find(candidate => candidate === row.scrim) ?? 'medium';

    return {
        type,
        recipe: typeof row.recipe === 'string' && row.recipe !== '' ? row.recipe : null,
        scrim,
        image_url: safeSrc(row.image_url) || null,
        base: typeof row.base === 'string' ? row.base : '',
        foreground: typeof row.foreground === 'string' ? row.foreground : '',
        contrast: typeof row.contrast === 'number' ? row.contrast : 0,
        css_vars: safeVars(row.css_vars),
    };
}

function pageProps(): Record<string, unknown> {
    try {
        return (usePage()?.props ?? {}) as Record<string, unknown>;
    } catch {
        return {};
    }
}

const shared = pageProps();

const surface = computed<PageSurfacePayload>(() =>
    normalise(props.surface !== undefined ? props.surface : shared.surface),
);

/** A 404 image hides the element; the base colour underneath always survives. */
const failed = ref(false);

const imageUrl = computed(() => (failed.value ? null : surface.value.image_url));

/**
 * Without both custom properties there is no colour to paint, so the theme
 * tokens stay in charge rather than resolving to an invalid `var()`.
 */
const painted = computed(
    () =>
        surface.value.type !== 'none' &&
        surface.value.type !== 'brand' &&
        '--myra-page-bg' in surface.value.css_vars &&
        '--myra-page-fg' in surface.value.css_vars,
);

const style = computed<Record<string, string>>(() => ({
    ...surface.value.css_vars,
    ...(painted.value
        ? { backgroundColor: 'var(--myra-page-bg)', color: 'var(--myra-page-fg)' }
        : {}),
}));

const rootClass = computed(() => {
    if (surface.value.type === 'brand') return 'relative min-h-screen bg-primary text-primary-foreground';
    if (painted.value) return 'relative min-h-screen';

    return 'relative min-h-screen bg-background text-foreground';
});

const scrimOpacity = computed(() => surface.value.css_vars['--myra-page-scrim'] ?? '0.55');

const translucent = computed(() => {
    if (surface.value.type === 'none') return false;

    return props.navbarTranslucent !== undefined
        ? props.navbarTranslucent
        : shared.navbarTranslucent === true;
});
</script>

<template>
    <div
        v-if="surface.type === 'none'"
        class="min-h-screen bg-background text-foreground"
    >
        <slot :translucent="false" :active="false" />
    </div>

    <div v-else :class="rootClass" :style="style">
        <!-- No server-painted base means no measured foreground, so no media. -->
        <div v-if="painted" class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div
                v-if="surface.recipe"
                class="absolute inset-0"
                :class="surface.type === 'pattern' ? 'opacity-[0.12]' : ''"
                style="background-image: var(--myra-page-image); background-size: var(--myra-page-image-size, auto)"
            />
            <img
                v-if="imageUrl"
                :src="imageUrl"
                alt=""
                loading="eager"
                decoding="async"
                class="absolute inset-0 size-full object-cover dark:brightness-[0.25] dark:grayscale"
                @error="failed = true"
            />
            <div
                v-if="imageUrl"
                class="absolute inset-0 bg-black"
                :style="{ opacity: scrimOpacity }"
            />
        </div>

        <div class="relative">
            <slot :translucent="translucent" :active="true" />
        </div>
    </div>
</template>
