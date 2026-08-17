<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import BlockViewportBar from '@/components/admin/blocks/BlockViewportBar.vue';
import { ExternalLink, Info, RefreshCw } from 'lucide-vue-next';
import { adminPath } from '@/lib/adminPath';
import { useLiveEditHost } from '@/composables/useLiveEditHost';
import type { FieldKind } from '@/pagebuilder/liveEditProtocol';

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

const emit = defineEmits<{
    (e: 'field-change', block: string, path: string, value: string): void;
    (e: 'select', block: string): void;
    (e: 'activate', block: string, path: string, kind: FieldKind): void;
}>();

const { t } = useI18n();

const PREVIEW_ROUTE = 'admin.landing.builder.preview';
const previewPath = () => adminPath('landing/builder/preview');

const token = ref('');
const pending = ref(false);
const failed = ref(false);
const viewport = ref('full');
const dark = ref(false);
const frame = ref<HTMLIFrameElement | null>(null);

const liveEdit = ref(true);

/**
 * With no sections the preview falls back to the settings-driven homepage,
 * which carries no block markers — so there is nothing to write an edit back
 * to. Saying so beats a page that silently ignores every click.
 */
const nothingToEdit = computed(() => liveEdit.value && props.blocks.length === 0);

let timer: ReturnType<typeof setTimeout> | null = null;
let seq = 0;

/**
 * The blocks as they already appear INSIDE the frame.
 *
 * An inline edit mutates the draft, which would ordinarily trip the deep watcher
 * and reload the iframe — destroying the caret mid-word. Recording what the frame
 * already shows lets the republish be skipped for exactly those changes, and only
 * those: a left-panel edit still differs from this snapshot and still republishes.
 */
let frameEcho = '';

const host = useLiveEditHost({
    frame,
    onChange(block, path, value) {
        emit('field-change', block, path, value);

        void nextTick(() => {
            frameEcho = JSON.stringify(props.blocks);
        });
    },
    onSelect(block) {
        emit('select', block);
    },
    onActivate(block, path, kind) {
        emit('activate', block, path, kind);
    },
});

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

    return previewPath();
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

        // Reloading to show what the frame already renders would only throw the
        // caret away mid-sentence.
        if (JSON.stringify(props.blocks) === frameEcho) return;

        void publish();
    }, props.debounceMs);
}

/** Republish now — the editor calls this after a save so the frame is truthful. */
function refresh(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    // An explicit refresh is a request for the truth, so the echo is dropped.
    frameEcho = '';

    void publish();
}

/** Re-arm the frame after every load: a reload creates a fresh, inert document. */
function onFrameLoad(): void {
    applyTheme();
    host.setEnabled(liveEdit.value);
}

watch(() => props.blocks, schedule, { deep: true });
watch(() => props.template, schedule);
watch(dark, applyTheme);
watch(liveEdit, on => host.setEnabled(on));

onMounted(() => {
    dark.value = document.documentElement.classList.contains('dark');
    void publish();
});

onBeforeUnmount(() => {
    if (timer !== null) clearTimeout(timer);
    seq++;
});

defineExpose({ refresh, highlight: host.highlight });
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <BlockViewportBar v-model="viewport" v-model:dark="dark" />

            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2 pr-1">
                    <Switch id="live-edit" v-model="liveEdit" />
                    <Label for="live-edit" class="cursor-pointer text-sm font-normal">
                        {{ t('pageBuilder.liveEdit.toggle') }}
                    </Label>
                </div>

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

        <p
            v-if="nothingToEdit"
            class="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-900 dark:text-amber-200"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>{{ t('pageBuilder.liveEdit.needsSections') }}</span>
        </p>

        <div class="flex min-h-0 flex-1 justify-center overflow-auto rounded-lg border bg-muted/30 p-2">
            <iframe
                v-if="previewUrl"
                ref="frame"
                :src="previewUrl"
                :title="t('blocks.preview.frameTitle', { name: t('landing.title') })"
                :style="frameStyle"
                class="h-full min-h-[36rem] max-w-full rounded-md border bg-background"
                @load="onFrameLoad"
            />

            <p v-else class="flex items-center justify-center p-10 text-sm text-muted-foreground">
                {{ failed ? t('blocks.unavailable.title') : t('common.loading') }}
            </p>
        </div>

        <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ statusText }}</p>
    </div>
</template>
