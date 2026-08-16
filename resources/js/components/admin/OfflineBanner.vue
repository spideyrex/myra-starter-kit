<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { CloudOff } from 'lucide-vue-next';

// role="status", NOT role="alert": being offline is a state, not an error.
// Renders no numbers — nothing cached is ever presented as live data.
const { t } = useI18n();
</script>

<template>
    <div
        role="status"
        aria-live="polite"
        class="fixed inset-x-0 bottom-0 z-50 flex items-center justify-center gap-2 border-t border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
    >
        <CloudOff class="size-4 shrink-0" aria-hidden="true" />
        <span>{{ t('assistant.offline.banner') }}</span>
    </div>
</template>
