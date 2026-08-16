<script setup lang="ts">
import { computed, h, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { VIEWPORT_WIDTHS, type PlaygroundScheme, type PlaygroundViewport } from '@/composables/usePlayground';

const props = defineProps<{
    component: Component;
    bound: Record<string, any>;
    slots?: Record<string, string>;
    viewport: PlaygroundViewport;
    scheme: PlaygroundScheme;
    labelKey: string;
}>();

const { t } = useI18n();

const width = computed(() => `${VIEWPORT_WIDTHS[props.viewport]}px`);

/** Slot text comes from a `text` control, so it is always a plain string. */
const slotRender = computed(() => {
    const map = props.slots ?? {};
    const out: Record<string, () => any> = {};

    for (const [slotName, controlName] of Object.entries(map)) {
        const value = props.bound[controlName];
        out[slotName] = () => String(value ?? '');
    }

    return out;
});

/** Slot-backed controls are not props — passing them through would leak an attr. */
const forwarded = computed(() => {
    const consumed = new Set(Object.values(props.slots ?? {}));
    const out: Record<string, any> = {};

    for (const [key, value] of Object.entries(props.bound)) {
        if (!consumed.has(key)) out[key] = value;
    }

    return out;
});

/** A functional component so the stage can render the same tree in two frames. */
const frame: any = () => h(props.component as any, forwarded.value, slotRender.value);
</script>

<template>
    <div class="flex flex-wrap gap-4">
        <figure
            v-if="scheme !== 'dark'"
            class="min-w-0 max-w-full overflow-x-auto rounded-lg border bg-background p-6"
            :style="{ width }"
        >
            <figcaption class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {{ t(labelKey) }} · {{ t('gallery.playground.schemeLight') }} · {{ VIEWPORT_WIDTHS[viewport] }}px
            </figcaption>
            <component :is="frame" />
        </figure>

        <figure
            v-if="scheme !== 'light'"
            class="dark min-w-0 max-w-full overflow-x-auto rounded-lg border bg-background p-6 text-foreground"
            :style="{ width }"
        >
            <figcaption class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                {{ t(labelKey) }} · {{ t('gallery.playground.schemeDark') }} · {{ VIEWPORT_WIDTHS[viewport] }}px
            </figcaption>
            <component :is="frame" />
        </figure>
    </div>
</template>
