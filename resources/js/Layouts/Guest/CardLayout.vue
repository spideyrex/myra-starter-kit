<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BrandMark from '@/components/brand/BrandMark.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useBrand } from '@/composables/useBrand';
import { normalizeAuth, STOCK_AUTH, surfaceStyle, type AuthAppearancePayload } from './authTypes';

/**
 * The split lives inside the card. The surface is confined to the media half,
 * so the page chrome stays `bg-muted` and the form never leaves `bg-card`.
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

    return surface.value.type === 'none' ? 'bg-muted text-foreground' : '';
});
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-muted px-4 py-8">
        <div class="mb-6 md:hidden">
            <Link :href="route('login')" class="text-foreground">
                <BrandMark variant="full" size="md" />
            </Link>
        </div>

        <Card class="w-full max-w-md overflow-hidden p-0 md:max-w-4xl">
            <CardContent class="grid p-0 md:grid-cols-2">
                <div
                    data-slot="auth-surface"
                    class="relative hidden md:flex md:flex-col md:items-center md:justify-center md:p-8"
                    :class="[toneClass, auth.flip ? 'md:order-2' : '']"
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

                    <div class="relative max-w-xs space-y-4 text-center">
                        <BrandMark variant="full" size="lg" />
                        <p v-if="auth.show_tagline" class="text-sm opacity-80">
                            {{ brand.tagline || t('auth.defaultTagline') }}
                        </p>
                    </div>
                </div>

                <div class="p-6 md:p-8" :class="auth.flip ? 'md:order-1' : ''">
                    <slot />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
