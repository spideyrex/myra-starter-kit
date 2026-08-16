<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * `aria-live="off"` on purpose: DataSurface already announces the change. Two
 * live regions for one event is two announcements.
 */
const props = withDefaults(defineProps<{
    at?: number | null;
    connected?: boolean;
    now?: number;
}>(), { at: null, connected: false });

const { t } = useI18n();

const flash = ref(false);
let flashTimer: ReturnType<typeof setTimeout> | null = null;

const ago = computed(() => {
    if (!props.at) return t('live.justNow');

    const seconds = Math.max(0, Math.round(((props.now ?? Date.now()) - props.at) / 1000));

    if (seconds < 5) return t('live.justNow');
    if (seconds < 60) return t('live.secondsAgo', { count: seconds });
    if (seconds < 3600) return t('live.minutesAgo', { count: Math.floor(seconds / 60) });

    return t('live.hoursAgo', { count: Math.floor(seconds / 3600) });
});

const title = computed(() => (props.connected
    ? t('live.tooltip.connected')
    : t('live.tooltip.polling')));

watch(() => props.at, () => {
    flash.value = true;
    if (flashTimer) clearTimeout(flashTimer);
    flashTimer = setTimeout(() => { flash.value = false; }, 600);
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs text-muted-foreground transition-colors motion-reduce:transition-none"
        :class="flash ? 'bg-primary/10 motion-reduce:bg-transparent' : 'bg-transparent'"
        :title="title"
        aria-live="off"
        data-live-badge
    >
        <span
            class="size-1.5 rounded-full"
            :class="connected ? 'bg-emerald-500' : 'bg-muted-foreground/50'"
            aria-hidden="true"
        />
        <span>{{ t('live.updatedAgo', { ago }) }}</span>
        <span class="sr-only">{{ connected ? t('live.connected') : t('live.polling') }}</span>
    </span>
</template>
