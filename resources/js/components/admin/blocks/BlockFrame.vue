<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { PackageX } from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    entryFile: string;
    dark?: boolean;
    reduced?: boolean;
    fallbackKey?: string;
    fallbackParams?: Record<string, unknown>;
}>(), {
    dark: false,
    reduced: false,
    fallbackKey: 'blocks.unavailable.body',
    fallbackParams: () => ({}),
});

const { t } = useI18n();

/**
 * Dynamic by design: every block stays its own lazy chunk, and nothing outside
 * the preview path can pull one into the admin bundle.
 */
const modules = import.meta.glob('@/blocks/**/*.vue');

const loader = computed(() => {
    const key = Object.keys(modules).find(path => path.endsWith(`/blocks/${props.entryFile}`));

    return key ? (modules[key] as () => Promise<{ default: Component }>) : null;
});

const block = computed(() => (loader.value ? defineAsyncComponent(loader.value) : null));

// The frame owns its own document, so the theme is applied before the block mounts.
onMounted(() => {
    const root = document.documentElement;
    root.classList.toggle('dark', props.dark);
    if (props.reduced) root.setAttribute('data-reduced-motion', 'true');
});
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <component :is="block" v-if="block" />

        <div v-else class="p-6">
            <Empty class="border">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <PackageX class="size-6" aria-hidden="true" />
                    </EmptyMedia>
                    <EmptyTitle>{{ t('blocks.unavailable.title') }}</EmptyTitle>
                    <EmptyDescription>{{ t(fallbackKey, fallbackParams) }}</EmptyDescription>
                </EmptyHeader>
            </Empty>
        </div>
    </div>
</template>
