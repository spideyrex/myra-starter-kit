<script setup lang="ts">
// >>> MYRA v2.7 [C] START
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { LayoutGrid, UserRound } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import type { DashboardLayoutSource } from '@/composables/useDashboardLayout';

const props = defineProps<{ source?: DashboardLayoutSource | null }>();

const { t } = useI18n();

const kind = computed(() => props.source?.source ?? 'none');

// Text AND icon, never colour alone: this is the only signal that the layout on
// screen was not authored by the person looking at it.
const label = computed(() => (kind.value === 'role'
    ? t('roleDashboard.source.role', { role: props.source?.role ?? '' })
    : t('roleDashboard.source.personal')));
// <<< MYRA v2.7 [C] END
</script>

<template>
    <!-- >>> MYRA v2.7 [C] START -->
    <Badge
        v-if="kind !== 'none'"
        role="status"
        :variant="kind === 'role' ? 'outline' : 'secondary'"
        class="gap-1.5 font-normal"
    >
        <component :is="kind === 'role' ? LayoutGrid : UserRound" class="size-3.5" aria-hidden="true" />
        {{ label }}
    </Badge>
    <!-- <<< MYRA v2.7 [C] END -->
</template>
