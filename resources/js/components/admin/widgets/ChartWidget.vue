<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-vue-next';
import { Bar, Line, Pie, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, BarElement, PointElement, LineElement,
    ArcElement, Title, Tooltip, Legend, Filler,
} from 'chart.js';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';

ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, ArcElement, Title, Tooltip, Legend, Filler);

const props = defineProps<{
    widget: WidgetSchema;
    pageProps: any;
}>();

const chartData = computed(() => props.widget.dataFn?.(props.pageProps) ?? { labels: [], datasets: [] });

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: props.widget.chartType === 'pie' || props.widget.chartType === 'doughnut' } },
    scales: (props.widget.chartType === 'pie' || props.widget.chartType === 'doughnut') ? {} : {
        y: { beginAtZero: true, grid: { color: 'rgba(128, 128, 128, 0.1)' } },
        x: { grid: { display: false } },
    },
};

const chartComponent = computed(() => {
    switch (props.widget.chartType) {
        case 'line': return Line;
        case 'pie': return Pie;
        case 'doughnut': return Doughnut;
        default: return Bar;
    }
});

// Collapsible state from localStorage
const storageKey = `widget-collapsed-${props.widget.key}`;
const isOpen = ref(true);

onMounted(() => {
    if (props.widget.collapsible) {
        const saved = localStorage.getItem(storageKey);
        if (saved !== null) isOpen.value = saved !== 'true';
    }
});

function toggleCollapse() {
    isOpen.value = !isOpen.value;
    localStorage.setItem(storageKey, String(!isOpen.value));
}
</script>

<template>
    <Card class="animate-fade-in-up">
        <template v-if="widget.collapsible">
            <Collapsible :open="isOpen">
                <CardHeader class="cursor-pointer" @click="toggleCollapse">
                    <div class="flex items-center justify-between">
                        <CardTitle>{{ widget.title }}</CardTitle>
                        <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="{ 'rotate-180': isOpen }" />
                    </div>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent>
                        <div :style="{ height: `${widget.height || 260}px` }">
                            <component :is="chartComponent" :data="chartData" :options="chartOptions" />
                        </div>
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </template>
        <template v-else>
            <CardHeader>
                <CardTitle>{{ widget.title }}</CardTitle>
            </CardHeader>
            <CardContent>
                <div :style="{ height: `${widget.height || 260}px` }">
                    <component :is="chartComponent" :data="chartData" :options="chartOptions" />
                </div>
            </CardContent>
        </template>
    </Card>
</template>
