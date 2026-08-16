<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { STAT_HEIGHT } from './geometry';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <!-- Geometry-accurate: exactly StatCard's height, so nothing shifts on load. -->
    <div
        class="rounded-xl border bg-card p-5 shadow-sm"
        :style="{ height: `${STAT_HEIGHT}px` }"
        data-skeleton="stat"
        v-bind="$attrs"
    >
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <Skeleton class="h-5 w-24 motion-reduce:animate-none" />
                <Skeleton class="h-8 w-32 motion-reduce:animate-none" />
            </div>
            <Skeleton class="size-10 shrink-0 rounded-lg motion-reduce:animate-none" />
        </div>
        <Skeleton class="mt-2 h-4 w-20 motion-reduce:animate-none" />
    </div>
</template>
