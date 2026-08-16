<script setup lang="ts">
import { defineAsyncComponent, onMounted, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { Skeleton } from '@/components/ui/skeleton';

const props = defineProps<{
    /** Resolved by Preview.vue, the only file permitted to reach the vendored tree. */
    loader: (() => Promise<{ default: Component }>) | null;
    dark?: boolean;
    reduced?: boolean;
    fallbackKey?: string;
}>();

const { t } = useI18n();

const shell = props.loader
    ? defineAsyncComponent({
        loader: props.loader,
        loadingComponent: Skeleton,
    })
    : null;

// The parent's appearance travels in on the preview URL, applied before mount
// so the frame never flashes the wrong theme.
onMounted(() => {
    document.documentElement.classList.toggle('dark', props.dark === true);

    if (props.reduced) {
        document.documentElement.style.setProperty('--myra-motion', 'none');
    }
});
</script>

<template>
    <component :is="shell" v-if="shell" />

    <div v-else class="flex min-h-svh items-center justify-center p-10">
        <p class="max-w-sm text-center text-sm text-muted-foreground">
            {{ t(fallbackKey ?? 'examples.unavailable.title') }}
        </p>
    </div>
</template>
