<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { AlertCircle, Inbox, WifiOff } from 'lucide-vue-next';
import WidgetSkeleton from '@/components/admin/skeletons/WidgetSkeleton.vue';
import type { SurfaceState } from '@/composables/useAsyncSurface';

/**
 * The default wrapper for EVERY data surface. It owns the skeleton, the empty
 * state, the error state and — importantly — the single announcement per
 * transition, so a dashboard of twenty widgets does not produce twenty
 * competing live regions saying "loading" at once.
 */
const props = withDefaults(defineProps<{
    state: SurfaceState;
    skeleton?: 'stat' | 'chart' | 'table' | 'form' | 'list' | 'text';
    rows?: number;
    height?: number;
    labelKey?: string;
    /** Already-translated label, for surfaces whose title is not an i18n key. */
    label?: string;
    /** Per-state announcement overrides, so a surface can keep its own wording. */
    keys?: Partial<Record<SurfaceState, string>>;
}>(), { skeleton: 'text', rows: 5 });

const emit = defineEmits<{ (e: 'retry'): void }>();

const { t } = useI18n();

const announcement = ref('');

const label = computed(() => (props.labelKey ? t(props.labelKey) : props.label ?? ''));

// `streaming` already shows real content, so it is not "busy" to AT.
const busy = computed(() => props.state === 'loading');

const ANNOUNCED: Partial<Record<SurfaceState, string>> = {
    loading: 'live.a11y.loading',
    streaming: 'live.a11y.streaming',
    ready: 'live.a11y.ready',
    empty: 'live.a11y.empty',
    error: 'live.a11y.error',
    offline: 'live.a11y.offline',
};

// Exactly one sentence, replaced on each transition — never appended.
watch(() => props.state, (next) => {
    const key = props.keys?.[next] ?? ANNOUNCED[next];
    announcement.value = key ? t(key, { label: label.value }) : '';
}, { immediate: true });

function retry(): void {
    emit('retry');
}
</script>

<template>
    <div :aria-busy="busy ? 'true' : 'false'" data-surface>
        <span class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ announcement }}</span>

        <WidgetSkeleton
            v-if="state === 'loading'"
            :type="skeleton"
            :height="height"
            :rows="rows"
        />

        <div v-else-if="state === 'offline'" class="flex flex-col items-center justify-center gap-2 py-8 text-center">
            <slot name="offline">
                <WifiOff class="size-8 text-muted-foreground/60" aria-hidden="true" />
                <p class="text-sm font-medium">{{ t('live.a11y.offline', { label }) }}</p>
            </slot>
        </div>

        <div v-else-if="state === 'error'" class="flex flex-col items-center justify-center gap-2 py-8 text-center">
            <slot name="error" :retry="retry">
                <AlertCircle class="size-8 text-destructive/70" aria-hidden="true" />
                <p class="text-sm font-medium">{{ t('live.errors.slot') }}</p>
                <Button type="button" variant="outline" size="sm" @click="retry">
                    {{ t('live.retry') }}
                </Button>
            </slot>
        </div>

        <div v-else-if="state === 'empty'" class="flex flex-col items-center justify-center gap-2 py-8 text-center">
            <slot name="empty">
                <Inbox class="size-8 text-muted-foreground/60" aria-hidden="true" />
                <p class="text-sm font-medium">{{ t('live.a11y.empty', { label }) }}</p>
            </slot>
        </div>

        <!-- `streaming` renders the partial content plus a caret, never a skeleton. -->
        <div v-else :data-streaming="state === 'streaming' ? 'true' : undefined">
            <slot />
            <span
                v-if="state === 'streaming'"
                class="ml-0.5 inline-block h-4 w-px animate-pulse bg-foreground align-middle motion-reduce:animate-none"
                aria-hidden="true"
                data-caret
            />
        </div>
    </div>
</template>
