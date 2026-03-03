<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';

const props = defineProps<{
    status: string;
}>();

const variant = computed(() => {
    switch (props.status) {
        case 'active':
        case 'success':
        case 'sent':
            return 'default';
        case 'suspended':
        case 'failed':
        case 'error':
            return 'destructive';
        case 'pending':
        case 'queued':
            return 'secondary';
        default:
            return 'outline';
    }
});
</script>

<template>
    <Badge :variant="variant" class="inline-flex items-center gap-1.5">
        <span
            v-if="status === 'active'"
            class="relative flex size-2"
        >
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-75" />
            <span class="relative inline-flex size-2 rounded-full bg-current" />
        </span>
        {{ status }}
    </Badge>
</template>
