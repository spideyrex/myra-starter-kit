<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AuthAppearancePayload } from './types';

const props = defineProps<{ auth: AuthAppearancePayload; dark?: boolean; tagline?: string }>();

const { t } = useI18n();

// The 5.3 surface block, inlined. No shared child component crosses a bundle.
const failed = ref(false);

watch(() => props.auth.surface.image_url, () => { failed.value = false; });

const style = computed(() => ({
    ...props.auth.surface.css_vars,
    ...(props.auth.surface.type === 'brand' || props.auth.surface.type === 'none'
        ? {}
        : { backgroundColor: 'var(--myra-auth-bg)', color: 'var(--myra-auth-fg)' }),
}));

const brandLiteral = computed(() => props.auth.surface.type === 'brand');
const layout = computed(() => props.auth.layout);

/** Where the surface sits for this shell. 'card' paints it inside the card only. */
const geometry = computed(() => {
    if (layout.value === 'split') return props.auth.flip ? 'inset-y-0 right-0 left-1/2' : 'inset-y-0 left-0 right-1/2';
    if (layout.value === 'card') return null;

    return 'inset-0';
});

const showImage = computed(() => props.auth.supports_media && !!props.auth.surface.image_url && !failed.value);
const showTaglinePanel = computed(() => layout.value === 'split' && props.auth.show_tagline);
</script>

<template>
    <div :class="dark ? 'dark' : ''">
        <div class="relative aspect-[16/10] overflow-hidden rounded-lg border bg-background text-foreground">
            <div v-if="layout === 'card'" class="absolute inset-0 bg-muted"></div>

            <!-- SURFACE -->
            <div
                v-if="geometry"
                class="absolute overflow-hidden"
                :class="[geometry, brandLiteral ? 'bg-primary text-primary-foreground' : '']"
                :style="style"
            >
                <div
                    v-if="auth.surface.recipe"
                    aria-hidden="true"
                    class="absolute inset-0"
                    :class="auth.surface.type === 'pattern' ? 'opacity-[0.12]' : ''"
                    style="background-image: var(--myra-auth-image); background-size: var(--myra-auth-image-size, auto)"
                ></div>
                <img
                    v-if="showImage"
                    :src="auth.surface.image_url ?? ''"
                    alt=""
                    loading="eager"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover dark:brightness-[0.25] dark:grayscale"
                    @error="failed = true"
                />
                <div
                    v-if="showImage"
                    aria-hidden="true"
                    class="absolute inset-0 bg-black"
                    :style="{ opacity: auth.surface.css_vars['--myra-auth-scrim'] ?? '0.55' }"
                ></div>

                <div v-if="showTaglinePanel" class="relative flex size-full flex-col items-center justify-center gap-2 px-3 text-center">
                    <div class="size-5 rounded-md border border-current opacity-70"></div>
                    <p class="text-[9px] leading-tight opacity-80">{{ tagline || t('appearanceAdmin.preview.tagline') }}</p>
                </div>
            </div>

            <!-- FORM — always card-isolated, whatever the surface does. -->
            <div
                class="absolute inset-y-0 flex items-center justify-center p-3"
                :class="layout === 'split' ? (auth.flip ? 'left-0 right-1/2' : 'right-0 left-1/2') : 'inset-x-0'"
            >
                <div
                    class="w-full overflow-hidden rounded-md border bg-card text-card-foreground shadow-sm"
                    :class="layout === 'card' ? 'max-w-[13rem]' : 'max-w-[9rem]'"
                >
                    <div v-if="layout === 'card'" class="grid grid-cols-2">
                        <div :class="auth.flip ? 'order-2' : 'order-1'" class="space-y-1.5 p-2.5">
                            <div class="h-1.5 w-10 rounded-full bg-foreground/70"></div>
                            <div class="h-3 rounded-sm border bg-background"></div>
                            <div class="h-3 rounded-sm border bg-background"></div>
                            <div class="h-3 rounded-sm bg-primary"></div>
                        </div>
                        <div
                            class="relative overflow-hidden"
                            :class="[auth.flip ? 'order-1' : 'order-2', brandLiteral ? 'bg-primary' : '']"
                            :style="style"
                        >
                            <div
                                v-if="auth.surface.recipe"
                                aria-hidden="true"
                                class="absolute inset-0"
                                :class="auth.surface.type === 'pattern' ? 'opacity-[0.12]' : ''"
                                style="background-image: var(--myra-auth-image); background-size: var(--myra-auth-image-size, auto)"
                            ></div>
                            <img
                                v-if="showImage"
                                :src="auth.surface.image_url ?? ''"
                                alt=""
                                class="absolute inset-0 size-full object-cover dark:brightness-[0.25] dark:grayscale"
                                @error="failed = true"
                            />
                            <div
                                v-if="showImage"
                                aria-hidden="true"
                                class="absolute inset-0 bg-black"
                                :style="{ opacity: auth.surface.css_vars['--myra-auth-scrim'] ?? '0.55' }"
                            ></div>
                        </div>
                    </div>

                    <div v-else class="space-y-1.5 p-2.5">
                        <div v-if="layout !== 'split'" class="mx-auto size-4 rounded-md border"></div>
                        <div class="h-1.5 w-10 rounded-full bg-foreground/70"></div>
                        <div class="h-3 rounded-sm border bg-background"></div>
                        <div class="h-3 rounded-sm border bg-background"></div>
                        <div class="h-3 rounded-sm bg-primary"></div>
                    </div>
                </div>
            </div>
        </div>

        <p class="mt-2 text-center text-xs text-muted-foreground">
            {{ dark ? t('appearanceAdmin.preview.dark') : t('appearanceAdmin.preview.light') }}
        </p>
    </div>
</template>
