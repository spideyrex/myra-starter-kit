<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, onUnmounted, type Component } from 'vue';
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

/**
 * The frame shares a cookie jar with the live admin. A sidebar block mounts its
 * own SidebarProvider, which writes sidebar_state at path=/ the moment it is
 * toggled — that would hand the real admin shell the preview's state. Writes to
 * that one name are dropped while the frame is mounted; reads and every other
 * cookie are untouched. Installed here, synchronously, because the block chunk
 * resolves after setup.
 */
const SHIELDED_COOKIE = 'sidebar_state';
const cookieAccessor = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
const shielded = Boolean(cookieAccessor?.get && cookieAccessor.set);

if (shielded) {
    Object.defineProperty(document, 'cookie', {
        configurable: true,
        get: () => cookieAccessor!.get!.call(document),
        set: (value: unknown) => {
            if (String(value).trimStart().startsWith(`${SHIELDED_COOKIE}=`)) return;
            cookieAccessor!.set!.call(document, value);
        },
    });
}

onUnmounted(() => {
    if (shielded) Reflect.deleteProperty(document, 'cookie');
});

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
