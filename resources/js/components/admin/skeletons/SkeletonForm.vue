<script setup lang="ts">
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';

const props = withDefaults(defineProps<{ fields?: number }>(), { fields: 4 });

defineOptions({ inheritAttrs: false });

const count = computed(() => Math.max(1, Math.min(Math.trunc(props.fields) || 1, 24)));
</script>

<template>
    <div class="space-y-4" data-skeleton="form" v-bind="$attrs">
        <div v-for="f in count" :key="f" class="space-y-2">
            <Skeleton class="h-4 w-28 motion-reduce:animate-none" />
            <Skeleton class="h-9 w-full motion-reduce:animate-none" />
        </div>
        <Skeleton class="h-9 w-28 motion-reduce:animate-none" />
    </div>
</template>
