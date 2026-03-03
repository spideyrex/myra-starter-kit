<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TrendingUp, TrendingDown } from 'lucide-vue-next';

defineProps<{
    title: string;
    value: string | number;
    description?: string;
    icon?: any;
    trend?: {
        value: number;
        isPositive: boolean;
    };
}>();
</script>

<template>
    <Card class="animate-fade-in-up transition-all duration-200 hover:shadow-md hover:-translate-y-0.5">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">{{ title }}</CardTitle>
            <component v-if="icon" :is="icon" class="size-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
            <div class="text-2xl font-bold">{{ value }}</div>
            <p v-if="description || trend" class="text-xs text-muted-foreground">
                <span v-if="trend" class="inline-flex items-center gap-0.5" :class="trend.isPositive ? 'text-success' : 'text-destructive'">
                    <TrendingUp v-if="trend.isPositive" class="size-3" />
                    <TrendingDown v-else class="size-3" />
                    {{ trend.isPositive ? '+' : '' }}{{ trend.value }}%
                </span>
                {{ description }}
            </p>
        </CardContent>
    </Card>
</template>
