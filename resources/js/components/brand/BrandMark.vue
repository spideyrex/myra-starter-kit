<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useBrand } from '@/composables/useBrand';

const props = withDefaults(defineProps<{
    variant?: 'full' | 'square' | 'wordmark';
    size?: 'sm' | 'md' | 'lg';
    subtitle?: string | null;
    href?: string | null;
}>(), {
    variant: 'full',
    size: 'md',
    subtitle: null,
    href: null,
});

const { name, initial, logoUrl } = useBrand();

const boxClass = computed(() => ({ sm: 'size-6', md: 'size-8', lg: 'size-12' }[props.size]));
const textClass = computed(() => ({ sm: 'text-sm', md: 'text-base', lg: 'text-2xl' }[props.size]));
const showMark = computed(() => props.variant !== 'wordmark');
const showText = computed(() => props.variant !== 'square');
</script>

<template>
    <component :is="href ? Link : 'span'" v-bind="href ? { href } : {}" class="flex items-center gap-2">
        <!-- Logo path: the image carries the accessible name. -->
        <span v-if="showMark && logoUrl" :class="['flex shrink-0 items-center justify-center overflow-hidden rounded-lg', boxClass]">
            <img :src="logoUrl" :alt="name" class="size-full object-contain" />
        </span>

        <!-- Initial fallback: decorative mark, the name text is the label. -->
        <span
            v-else-if="showMark"
            aria-hidden="true"
            :class="['flex shrink-0 items-center justify-center rounded-lg bg-primary font-bold text-primary-foreground', boxClass, textClass]"
        >{{ initial }}</span>

        <span v-if="showText" class="grid min-w-0 flex-1 text-left leading-tight">
            <span :class="['truncate font-semibold', textClass]">{{ name }}</span>
            <span v-if="subtitle" class="truncate text-xs text-muted-foreground">{{ subtitle }}</span>
        </span>

        <!-- variant="square" has no adjacent text, so the name is provided here. -->
        <span v-else-if="!logoUrl" class="sr-only">{{ name }}</span>
    </component>
</template>
