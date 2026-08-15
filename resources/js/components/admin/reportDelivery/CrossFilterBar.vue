<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { X } from 'lucide-vue-next';
import type { CrossFilterChip } from '@/composables/useCrossFilter';

const props = defineProps<{ chips: CrossFilterChip[] }>();

const emit = defineEmits<{ remove: [widgetKey: string]; clear: [] }>();

const { t } = useI18n();

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.chips.length > 0) emit('clear');
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="chips.length"
        class="flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-2"
        role="region"
        :aria-label="t('reportDelivery.crossFilter.title')"
    >
        <span class="text-xs font-medium text-muted-foreground">{{ t('reportDelivery.crossFilter.title') }}</span>

        <button
            v-for="chip in chips"
            :key="chip.widget"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-border bg-background px-2.5 py-1 text-xs text-foreground transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :aria-label="t('reportDelivery.crossFilter.remove', { label: chip.label })"
            @click="emit('remove', chip.widget)"
        >
            <span>{{ chip.label }}</span>
            <X class="size-3 shrink-0" aria-hidden="true" />
        </button>

        <Button variant="ghost" size="sm" class="ml-auto h-7 text-xs" @click="emit('clear')">
            {{ t('reportDelivery.crossFilter.clear') }}
        </Button>

        <p class="sr-only">{{ t('reportDelivery.crossFilter.hint') }}</p>
    </div>
</template>
