<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Mounts its slot once it comes within 200px of the viewport, reserving the
 * declared height so nothing reflows. Falls back to mounting immediately where
 * IntersectionObserver is unavailable (SSR, jsdom).
 */
const props = withDefaults(defineProps<{
    enabled?: boolean;
    minHeight?: number;
}>(), { enabled: true, minHeight: 180 });

const host = ref<HTMLElement | null>(null);
const shown = ref(false);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!props.enabled || typeof IntersectionObserver === 'undefined') {
        shown.value = true;
        return;
    }

    observer = new IntersectionObserver((entries) => {
        if (entries.some(entry => entry.isIntersecting)) {
            shown.value = true;
            observer?.disconnect();
            observer = null;
        }
    }, { rootMargin: '200px' });

    if (host.value) observer.observe(host.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});
</script>

<template>
    <div ref="host">
        <slot v-if="shown" />
        <Skeleton v-else class="w-full rounded-xl" :style="{ height: `${minHeight}px` }" />
    </div>
</template>
