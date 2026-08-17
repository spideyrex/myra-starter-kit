<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, Check } from 'lucide-vue-next';

const props = defineProps<{ ratio: number; minimum: number; imageBacked?: boolean }>();

const { t } = useI18n();

const passes = computed(() => props.ratio >= props.minimum);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Badge :variant="passes ? 'secondary' : 'destructive'">
            <Check v-if="passes" class="mr-1 size-3" aria-hidden="true" />
            <AlertTriangle v-else class="mr-1 size-3" aria-hidden="true" />
            {{ t('appearanceAdmin.contrast.label', { ratio: ratio.toFixed(2) }) }}
        </Badge>
        <span class="text-xs text-muted-foreground">
            {{ passes ? t('appearanceAdmin.contrast.ok') : t('appearanceAdmin.contrast.warning', { minimum: minimum }) }}
        </span>
        <span v-if="imageBacked" class="text-xs text-muted-foreground">
            {{ t('appearanceAdmin.contrast.imageNote') }}
        </span>
    </div>
</template>
