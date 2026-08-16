<script setup lang="ts">
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { ROW_HEIGHT, TABLE_HEADER_HEIGHT } from './geometry';

const props = withDefaults(defineProps<{ rows?: number; columns?: number }>(), {
    rows: 5,
    columns: 3,
});

defineOptions({ inheritAttrs: false });

const count = computed(() => Math.max(1, Math.min(Math.trunc(props.rows) || 1, 50)));
</script>

<template>
    <!-- Bars at the REAL row height, so the table does not resize on load. -->
    <div class="w-full" data-skeleton="table" v-bind="$attrs">
        <div class="flex items-center gap-3" :style="{ height: `${TABLE_HEADER_HEIGHT}px` }">
            <Skeleton
                v-for="c in Math.max(1, columns)"
                :key="`h-${c}`"
                class="h-4 flex-1 motion-reduce:animate-none"
            />
        </div>
        <div
            v-for="r in count"
            :key="`r-${r}`"
            class="flex items-center gap-3"
            :style="{ height: `${ROW_HEIGHT}px` }"
            data-skeleton-row
        >
            <Skeleton
                v-for="c in Math.max(1, columns)"
                :key="`r-${r}-c-${c}`"
                class="h-4 flex-1 motion-reduce:animate-none"
            />
        </div>
    </div>
</template>
