<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BrandMark from '@/components/brand/BrandMark.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useBrand } from '@/composables/useBrand';
import { normalizeAuth, STOCK_AUTH, surfaceStyle, type AuthAppearancePayload } from './authTypes';

/**
 * Full-bleed surface with the card floating over it — what "replace the
 * background" usually means. The base colour is painted by the root, so a
 * missing or 404ing image leaves a legible page rather than a broken one.
 */
const props = withDefaults(defineProps<{ auth?: AuthAppearancePayload }>(), {
    auth: () => STOCK_AUTH,
});

const { brand } = useBrand();
const { t } = useI18n();

const auth = computed(() => normalizeAuth(props.auth));
const surface = computed(() => auth.value.surface);
const failed = ref(false);
const showImage = computed(() => auth.value.supports_media && !!surface.value.image_url && !failed.value);

const style = computed(() => surfaceStyle(surface.value));

const toneClass = computed(() => {
    if (surface.value.type === 'brand') {
        return 'bg-primary text-primary-foreground';
    }

    return surface.value.type === 'none' ? 'bg-background text-foreground' : '';
});
</script>

<template>
    <div
        data-slot="auth-surface"
        class="relative flex min-h-screen flex-col items-center justify-center px-4 py-8"
        :class="toneClass"
        :style="style"
    >
        <div aria-hidden="true" class="absolute inset-0 overflow-hidden">
            <div
                v-if="surface.recipe"
                class="absolute inset-0"
                :class="surface.type === 'pattern' ? 'opacity-[0.12]' : ''"
                style="background-image: var(--myra-auth-image); background-size: var(--myra-auth-image-size, auto)"
            ></div>
            <img
                v-if="showImage"
                :src="surface.image_url ?? ''"
                alt=""
                loading="eager"
                decoding="async"
                class="absolute inset-0 size-full object-cover dark:brightness-[0.25] dark:grayscale"
                @error="failed = true"
            />
            <div
                v-if="showImage"
                class="absolute inset-0 bg-black"
                :style="{ opacity: surface.css_vars['--myra-auth-scrim'] ?? '0.55' }"
            ></div>
        </div>

        <div class="relative flex w-full max-w-md flex-col items-center">
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <BrandMark variant="full" size="lg" />
                <p v-if="auth.show_tagline" class="max-w-sm text-sm opacity-80">
                    {{ brand.tagline || t('auth.defaultTagline') }}
                </p>
            </div>

            <Card class="w-full max-w-md border shadow-lg">
                <CardContent class="p-6">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
