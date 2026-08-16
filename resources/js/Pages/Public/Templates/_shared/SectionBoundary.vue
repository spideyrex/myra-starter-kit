<script setup lang="ts">
import { onErrorCaptured, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{ type?: string }>();

/** The last line of defence must never itself throw — i18n included. */
const t: (key: string) => string = (() => {
    try {
        const { t: translate } = useI18n();

        return (key: string) => String(translate(key));
    } catch {
        return (key: string) => key;
    }
})();

/** A throwing section removes only itself; the rest of the page still paints. */
const failed = ref(false);

onErrorCaptured(() => {
    failed.value = true;

    return false;
});

const dev = Boolean(import.meta.env?.DEV);
</script>

<template>
    <slot v-if="!failed" />

    <!-- Anonymous visitors get nothing at all; developers get a reason. -->
    <section v-else-if="dev" class="border-t bg-muted/30 py-6 text-center text-sm text-muted-foreground">
        {{ t('pageBuilder.render.unavailable') }}
    </section>
</template>
