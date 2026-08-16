<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { brandScopeStyle } from '@/composables/useBrand';
import type { BrandTokens } from '@/types';

const props = defineProps<{
    tokens: BrandTokens;
    surface: 'admin' | 'login' | 'public' | 'email' | 'pdf' | 'install';
}>();

const { t } = useI18n();

const scope = computed(() => brandScopeStyle(props.tokens));
const label = computed(() => t(`brand.preview.surfaces.${props.surface}`));
const mark = computed(() => props.tokens.initial);
</script>

<template>
    <figure class="m-0 space-y-2">
        <figcaption class="text-xs font-medium text-muted-foreground">{{ label }}</figcaption>

        <div
            :style="scope"
            class="overflow-hidden rounded-lg border bg-background text-foreground"
            :aria-label="label"
            role="img"
        >
            <!-- admin: sidebar + header -->
            <div v-if="surface === 'admin'" class="flex h-32 text-[8px]">
                <div class="flex w-1/3 flex-col gap-1 bg-sidebar p-2 text-sidebar-foreground">
                    <div class="flex items-center gap-1">
                        <img v-if="tokens.logo_url" :src="tokens.logo_url" alt="" class="size-3 object-contain" />
                        <span v-else class="flex size-3 items-center justify-center rounded bg-primary text-primary-foreground">{{ mark }}</span>
                        <span class="truncate font-semibold">{{ tokens.name }}</span>
                    </div>
                    <div v-for="n in 4" :key="n" class="h-2 rounded bg-sidebar-accent/60"></div>
                </div>
                <div class="flex-1 space-y-1 p-2">
                    <div class="h-3 w-2/3 rounded bg-muted"></div>
                    <div class="h-8 rounded border"></div>
                    <div class="h-3 w-16 rounded bg-primary"></div>
                </div>
            </div>

            <!-- login: split panel -->
            <div v-else-if="surface === 'login'" class="flex h-32 text-[8px]">
                <div class="flex w-1/2 flex-col items-center justify-center gap-1 bg-primary p-2 text-primary-foreground">
                    <img v-if="tokens.logo_url" :src="tokens.logo_url" alt="" class="size-5 object-contain" />
                    <span v-else class="flex size-5 items-center justify-center rounded bg-primary-foreground font-bold text-primary">{{ mark }}</span>
                    <span class="truncate font-semibold">{{ tokens.name }}</span>
                </div>
                <div class="flex w-1/2 flex-col justify-center gap-1 p-3">
                    <div class="h-2 rounded bg-muted"></div>
                    <div class="h-2 rounded bg-muted"></div>
                    <div class="h-2 w-12 rounded bg-primary"></div>
                </div>
            </div>

            <!-- public marketing -->
            <div v-else-if="surface === 'public'" class="h-32 text-[8px]">
                <div class="flex items-center gap-1 border-b p-2">
                    <span class="flex size-3 items-center justify-center rounded bg-primary text-primary-foreground">{{ mark }}</span>
                    <span class="font-semibold">{{ tokens.name }}</span>
                </div>
                <div class="space-y-1 p-3 text-center">
                    <div class="mx-auto h-3 w-2/3 rounded bg-muted"></div>
                    <div class="mx-auto h-2 w-1/2 rounded bg-muted"></div>
                    <div class="mx-auto h-3 w-16 rounded bg-primary"></div>
                </div>
            </div>

            <!-- email -->
            <div v-else-if="surface === 'email'" class="h-32 bg-muted/40 p-3 text-[8px]">
                <div class="mx-auto max-w-[80%] rounded border bg-background">
                    <div class="flex items-center gap-1 border-b p-2">
                        <span class="flex size-3 items-center justify-center rounded bg-primary text-primary-foreground">{{ mark }}</span>
                        <span class="font-semibold">{{ tokens.name }}</span>
                    </div>
                    <div class="space-y-1 p-2">
                        <div class="h-2 rounded bg-muted"></div>
                        <div class="h-2 w-2/3 rounded bg-muted"></div>
                        <div class="h-3 w-12 rounded bg-primary"></div>
                    </div>
                </div>
            </div>

            <!-- pdf -->
            <div v-else-if="surface === 'pdf'" class="h-32 bg-muted/40 p-3 text-[8px]">
                <div class="mx-auto h-full w-3/5 rounded border bg-white p-2 text-black">
                    <div class="mb-1 border-b pb-1 font-semibold">{{ tokens.name }}</div>
                    <div class="space-y-1">
                        <div class="h-1.5 rounded bg-neutral-200"></div>
                        <div class="h-1.5 w-2/3 rounded bg-neutral-200"></div>
                        <div class="h-6 rounded" :style="{ backgroundColor: tokens.palette.primary }"></div>
                    </div>
                </div>
            </div>

            <!-- install / PWA -->
            <div v-else class="flex h-32 flex-col items-center justify-center gap-2 text-[8px]">
                <span class="flex size-10 items-center justify-center rounded-2xl bg-primary text-base font-bold text-primary-foreground">{{ mark }}</span>
                <span class="font-semibold">{{ tokens.short_name || tokens.name }}</span>
            </div>
        </div>
    </figure>
</template>
