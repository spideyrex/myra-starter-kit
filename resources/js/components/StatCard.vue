<script setup lang="ts">
import { computed } from 'vue';
import { TrendingUp, TrendingDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

export type StatCardColor =
    | 'blue' | 'green' | 'violet' | 'orange' | 'rose'
    | 'cyan' | 'purple' | 'red' | 'amber' | 'emerald'
    | 'indigo' | 'teal' | 'pink' | 'sky';

const props = defineProps<{
    title: string;
    value: string | number;
    description?: string;
    icon?: any;
    trend?: {
        value: number;
        isPositive: boolean;
    };
    color?: StatCardColor;
}>();

const colorConfig: Record<StatCardColor, string> = {
    blue: 'from-blue-500 to-blue-600',
    green: 'from-emerald-500 to-emerald-600',
    violet: 'from-violet-500 to-violet-600',
    orange: 'from-amber-500 to-orange-500',
    rose: 'from-rose-500 to-rose-600',
    cyan: 'from-cyan-500 to-cyan-600',
    purple: 'from-purple-500 to-purple-600',
    red: 'from-red-500 to-red-600',
    amber: 'from-amber-400 to-amber-500',
    emerald: 'from-emerald-400 to-emerald-600',
    indigo: 'from-indigo-500 to-indigo-600',
    teal: 'from-teal-500 to-teal-600',
    pink: 'from-pink-500 to-pink-600',
    sky: 'from-sky-500 to-sky-600',
};

const gradient = computed(() => props.color ? colorConfig[props.color] : null);
</script>

<template>
    <!-- Colored card style -->
    <div
        v-if="gradient"
        :class="cn(
            'animate-fade-in-up overflow-hidden rounded-xl bg-gradient-to-br p-5 text-white shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5',
            gradient,
        )"
    >
        <div class="flex items-start justify-between">
            <div
                v-if="icon"
                class="flex size-10 items-center justify-center rounded-lg bg-white/20 backdrop-blur-sm"
            >
                <component :is="icon" class="size-5 text-white" />
            </div>
            <span class="text-3xl font-bold tracking-tight">{{ value }}</span>
        </div>

        <div class="mt-3">
            <p class="text-sm font-semibold text-white/90">{{ title }}</p>
            <p v-if="description || trend" class="mt-0.5 text-xs text-white/70">
                <span
                    v-if="trend"
                    class="inline-flex items-center gap-0.5 rounded-full bg-white/20 px-1.5 py-0.5 text-white mr-1"
                >
                    <TrendingUp v-if="trend.isPositive" class="size-3" />
                    <TrendingDown v-else class="size-3" />
                    {{ trend.isPositive ? '+' : '' }}{{ trend.value }}%
                </span>
                {{ description }}
            </p>
        </div>
    </div>

    <!-- Default card style (no color) -->
    <div
        v-else
        class="animate-fade-in-up rounded-xl border bg-card p-5 shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5"
    >
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-muted-foreground">{{ title }}</p>
                <p class="mt-1 text-2xl font-bold">{{ value }}</p>
            </div>
            <div
                v-if="icon"
                class="flex size-10 items-center justify-center rounded-lg bg-muted"
            >
                <component :is="icon" class="size-5 text-muted-foreground" />
            </div>
        </div>
        <p v-if="description || trend" class="mt-2 text-xs text-muted-foreground">
            <span v-if="trend" class="inline-flex items-center gap-0.5" :class="trend.isPositive ? 'text-success' : 'text-destructive'">
                <TrendingUp v-if="trend.isPositive" class="size-3" />
                <TrendingDown v-else class="size-3" />
                {{ trend.isPositive ? '+' : '' }}{{ trend.value }}%
            </span>
            {{ description }}
        </p>
    </div>
</template>
