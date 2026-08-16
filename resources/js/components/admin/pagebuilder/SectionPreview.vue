<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import BlockViewportBar from '@/components/admin/blocks/BlockViewportBar.vue';
import { ExternalLink, RefreshCw } from 'lucide-vue-next';

/**
 * The page-builder preview pane.
 *
 * There is ONE renderer for preview and production: the iframe loads the REAL
 * public `/`. No postMessage bridge, no bespoke preview renderer — what the
 * author sees is the page anonymous visitors will get.
 *
 * The draft never touches storage. It is POSTed to the session preview slot and
 * handed back as an opaque token; the public controller only honours that token
 * for the same session AND an actor who may edit settings.
 *
 * The pane fills its container and is deliberately layout-agnostic: the editor
 * page owns whether it sits in a resizable split or behind a tab.
 */
const props = withDefaults(
    defineProps<{
        /** The draft rows, exactly as the editor holds them. */
        blocks?: unknown[];
        /** Template key, so the preview follows the chooser without saving it. */
        template?: string;
        debounceMs?: number;
    }>(),
    { blocks: () => [], template: '', debounceMs: 700 },
);

const { t } = useI18n();

const PREVIEW_ROUTE = 'admin.landing.builder.preview';
const PREVIEW_PATH = '/admin/landing/builder/preview';

const token = ref('');
const pending = ref(false);
const failed = ref(false);
const viewport = ref('full');
const dark = ref(false);
const frame = ref<HTMLIFrameElement | null>(null);

let timer: ReturnType<typeof setTimeout> | null = null;
let seq = 0;

/** Ziggy knows the route only once bundle C's routes file is merged. */
function endpoint(): string {
    try {
        const router = (globalThis as { route?: (...args: unknown[]) => unknown }).route;

        if (typeof router === 'function') {
            const resolved = router(PREVIEW_ROUTE);

            if (typeof resolved === 'string' && resolved !== '') return resolved;
        }
    } catch {
        // Fall through to the literal path.
    }

    return PREVIEW_PATH;
}

const previewUrl = computed(() => {
    if (token.value === '') return '';

    const params = new URLSearchParams({ preview: token.value });

    if (props.template !== '') params.set('template', props.template);

    return `/?${params.toString()}`;
});

const frameStyle = computed(() =>
    viewport.value === 'full' ? { width: '100%' } : { width: `${viewport.value}px` },
);

const statusText = computed(() => {
    if (failed.value) return t('blocks.unavailable.title');
    if (pending.value) return t('common.loading');

    return t('blocks.preview.frameTitle', { name: t('landing.title') });
});

/**
 * The frame is a separate document on the same origin, so the shell's theme has
 * to be carried across explicitly or the preview lies about dark mode.
 */
function applyTheme(): void {
    try {
        const root = frame.value?.contentDocument?.documentElement;

        root?.classList.toggle('dark', dark.value);
    } catch {
        // A cross-origin or detached frame is not worth failing the editor over.
    }
}

async function publish(): Promise<void> {
    const mine = ++seq;

    pending.value = true;

    try {
        const { data } = await axios.post(endpoint(), { blocks: props.blocks });

        if (mine !== seq) return;

        const next = typeof (data as { token?: unknown } | null)?.token === 'string'
            ? (data as { token: string }).token
            : '';

        if (next === '') {
            failed.value = true;

            return;
        }

        failed.value = false;
        token.value = next;
    } catch {
        if (mine === seq) failed.value = true;
    } finally {
        if (mine === seq) pending.value = false;
    }
}

function schedule(): void {
    if (timer !== null) clearTimeout(timer);

    timer = setTimeout(() => {
        timer = null;
        void publish();
    }, props.debounceMs);
}

/** Republish now — the editor calls this after a save so the frame is truthful. */
function refresh(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    void publish();
}

watch(() => props.blocks, schedule, { deep: true });
watch(() => props.template, schedule);
watch(dark, applyTheme);

onMounted(() => {
    dark.value = document.documentElement.classList.contains('dark');
    void publish();
});

onBeforeUnmount(() => {
    if (timer !== null) clearTimeout(timer);
    seq++;
});

defineExpose({ refresh });
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <BlockViewportBar v-model="viewport" v-model:dark="dark" />

            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="pending"
                    :aria-label="t('landing.preview.label')"
                    @click="refresh"
                >
                    <RefreshCw class="size-4" :class="pending ? 'animate-spin' : ''" aria-hidden="true" />
                </Button>

                <Button v-if="previewUrl" variant="outline" size="sm" as-child>
                    <a :href="previewUrl" target="_blank" rel="noopener">
                        <ExternalLink class="mr-1 size-4" aria-hidden="true" />
                        {{ t('landing.preview.openInNewTab') }}
                    </a>
                </Button>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 justify-center overflow-auto rounded-lg border bg-muted/30 p-2">
            <iframe
                v-if="previewUrl"
                ref="frame"
                :src="previewUrl"
                :title="t('blocks.preview.frameTitle', { name: t('landing.title') })"
                :style="frameStyle"
                class="h-full min-h-[36rem] max-w-full rounded-md border bg-background"
                @load="applyTheme"
            />

            <p v-else class="flex items-center justify-center p-10 text-sm text-muted-foreground">
                {{ failed ? t('blocks.unavailable.title') : t('common.loading') }}
            </p>
        </div>

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ statusText }}</p>
    </div>
</template>
