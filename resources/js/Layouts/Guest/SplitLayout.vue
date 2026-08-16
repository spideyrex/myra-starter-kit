<script setup lang="ts">
import { computed, defineComponent, onErrorCaptured, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Card, CardContent } from '@/components/ui/card';
import BrandMark from '@/components/brand/BrandMark.vue';
import { useBrand } from '@/composables/useBrand';
import type { AuthAppearancePayload } from '@/composables/useAppearance';

const props = defineProps<{ auth: AuthAppearancePayload }>();

const { brand } = useBrand();
const { t } = useI18n();

const surface = computed(() => props.auth.surface);

/** brand and none emit no CSS at all — the panel keeps today's utilities. */
const plain = computed(() => surface.value.type === 'brand' || surface.value.type === 'none');

const failed = ref(false);

const style = computed(() => ({
    ...surface.value.css_vars,
    ...(plain.value
        ? {}
        : { backgroundColor: 'var(--myra-auth-bg)', color: 'var(--myra-auth-fg)' }),
}));

const panelClass = computed(() =>
    plain.value
        ? 'hidden lg:flex lg:w-1/2 lg:flex-col lg:items-center lg:justify-center bg-primary px-8 text-primary-foreground'
        : 'relative hidden lg:flex lg:w-1/2 lg:flex-col lg:items-center lg:justify-center px-8',
);

const contentClass = computed(() =>
    plain.value ? 'max-w-md space-y-6 text-center' : 'relative z-10 max-w-md space-y-6 text-center',
);

/** currentColor once the panel carries a computed foreground, so it follows it. */
const quoteClass = computed(() =>
    plain.value
        ? 'mt-8 border-l-2 border-primary-foreground/30 pl-4 text-left text-lg italic text-primary-foreground/80'
        : 'mt-8 border-l-2 border-current pl-4 text-left text-lg italic opacity-80',
);

/**
 * A renderless boundary around the DECORATIVE half only. It renders a fragment,
 * so the default DOM is untouched. NEVER placed around <slot /> — a swallowed
 * error there would be a blank page with no way in.
 */
const Decor = defineComponent({
    name: 'GuestDecorBoundary',
    setup(_, { slots }) {
        const broken = ref(false);

        onErrorCaptured(() => {
            broken.value = true;

            return false;
        });

        return () => (broken.value ? null : slots.default?.());
    },
});
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Left branding panel (hidden on mobile) -->
        <div
            :class="[panelClass, auth.flip ? 'lg:order-2' : '']"
            :style="plain ? undefined : style"
        >
            <Decor>
                <div v-if="!plain" class="absolute inset-0 overflow-hidden" :style="style">
                    <div
                        v-if="surface.recipe"
                        aria-hidden="true"
                        class="absolute inset-0"
                        :class="surface.type === 'pattern' ? 'opacity-[0.12]' : ''"
                        style="background-image: var(--myra-auth-image); background-size: var(--myra-auth-image-size, auto)"
                    ></div>
                    <img
                        v-if="surface.image_url && !failed"
                        :src="surface.image_url"
                        alt=""
                        loading="eager"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover dark:brightness-[0.25] dark:grayscale"
                        @error="failed = true"
                    />
                    <div
                        v-if="surface.image_url && !failed"
                        aria-hidden="true"
                        class="absolute inset-0 bg-black"
                        :style="{ opacity: surface.css_vars['--myra-auth-scrim'] ?? '0.55' }"
                    ></div>
                </div>

                <div :class="contentClass">
                    <div class="flex items-center justify-center gap-3 text-3xl font-bold">
                        <BrandMark variant="full" size="lg" />
                    </div>
                    <blockquote v-if="auth.show_tagline" :class="quoteClass">
                        "{{ brand.tagline || t('auth.defaultTagline') }}"
                    </blockquote>
                </div>
            </Decor>
        </div>

        <!-- Right form panel -->
        <div
            class="flex w-full flex-col items-center justify-center px-4 py-8 lg:w-1/2"
            :class="auth.flip ? 'lg:order-1' : ''"
        >
            <!-- Mobile logo (visible on small screens only) -->
            <div class="mb-6 lg:hidden">
                <Link :href="route('login')" class="text-foreground">
                    <BrandMark variant="full" size="md" />
                </Link>
            </div>

            <Card class="w-full max-w-md border-0 shadow-none lg:border lg:shadow-sm">
                <CardContent class="p-6">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
