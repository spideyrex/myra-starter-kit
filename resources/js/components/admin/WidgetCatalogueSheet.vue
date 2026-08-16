<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus, Search } from 'lucide-vue-next';
import {
    Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle,
} from '@/components/ui/sheet';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useWidgetCatalogue, type CatalogueEntry, type CatalogueResult } from '@/composables/useWidgetCatalogue';

const props = withDefaults(defineProps<{
    open: boolean;
    entries?: CatalogueEntry[];
    used?: string[];
    atLimit?: boolean;
}>(), { entries: () => [], used: () => [], atLimit: false });

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'add', catalogueKey: string, binding: Record<string, unknown>): void;
}>();

const { t } = useI18n();

const { query, category, categories, results, atLimit: catalogueAtLimit } = useWidgetCatalogue({
    entries: () => props.entries,
    used: () => props.used,
    translate: (key: string) => t(key),
});

const atLimit = computed(() => props.atLimit === true || catalogueAtLimit.value);

const selected = ref<string | null>(null);
const dimension = ref<string>('');
const measures = ref<string[]>([]);
const chartType = ref<string>('');

const grouped = computed(() => {
    const map = new Map<string, CatalogueResult[]>();

    for (const entry of results.value) {
        const list = map.get(entry.categoryKey) ?? [];
        list.push(entry);
        map.set(entry.categoryKey, list);
    }

    return [...map.entries()].map(([id, items]) => ({ id, items }));
});

const active = computed(() => results.value.find(e => e.key === selected.value) ?? null);

watch(active, entry => {
    if (!entry) return;
    const defaults = (entry.defaults ?? {}) as any;
    dimension.value = defaults.dimension ?? entry.dimensions[0] ?? '';
    measures.value = Array.isArray(defaults.measures) ? [...defaults.measures] : entry.measures.slice(0, 1);
    chartType.value = defaults.chartType ?? entry.chartTypes[0] ?? '';
});

function toggleMeasure(name: string): void {
    measures.value = measures.value.includes(name)
        ? measures.value.filter(m => m !== name)
        : [...measures.value, name];
}

function add(): void {
    const entry = active.value;
    if (!entry || entry.disabled) return;

    const binding: Record<string, unknown> = {};
    if (entry.dimensions.length > 0 && dimension.value) binding.dimension = dimension.value;
    if (measures.value.length > 0) binding.measures = [...measures.value];
    if (entry.chartTypes.length > 0 && chartType.value) binding.chartType = chartType.value;

    emit('add', entry.key, binding);
    selected.value = null;
    emit('update:open', false);
}
</script>

<template>
    <Sheet :open="props.open" @update:open="value => emit('update:open', value)">
        <SheetContent side="right" class="flex w-full flex-col gap-0 sm:max-w-md">
            <SheetHeader>
                <SheetTitle>{{ t('dashboardLayout.addWidget') }}</SheetTitle>
                <SheetDescription>{{ t('dashboardLayout.catalogueDescription') }}</SheetDescription>
            </SheetHeader>

            <div class="mt-4 space-y-3">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <Input
                        v-model="query"
                        class="pl-8"
                        :placeholder="t('dashboardLayout.search')"
                        :aria-label="t('dashboardLayout.search')"
                    />
                </div>

                <div v-if="categories.length > 1" class="flex flex-wrap gap-1.5">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-7 px-2 text-xs"
                        :aria-pressed="category === null"
                        :class="category === null ? 'border-primary text-primary' : ''"
                        @click="category = null"
                    >
                        {{ t('dashboardLayout.allCategories') }}
                    </Button>
                    <Button
                        v-for="item in categories"
                        :key="item.id"
                        variant="outline"
                        size="sm"
                        class="h-7 px-2 text-xs"
                        :aria-pressed="category === item.id"
                        :class="category === item.id ? 'border-primary text-primary' : ''"
                        @click="category = item.id"
                    >
                        {{ t(item.id) }} ({{ item.count }})
                    </Button>
                </div>
            </div>

            <div class="mt-4 flex-1 space-y-5 overflow-y-auto pr-1">
                <p v-if="results.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                    {{ t('dashboardLayout.empty') }}
                </p>

                <section v-for="group in grouped" :key="group.id" class="space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ t(group.id) }}
                    </h3>

                    <ul class="space-y-2">
                        <li v-for="entry in group.items" :key="entry.key">
                            <button
                                type="button"
                                class="w-full rounded-lg border p-3 text-left transition-colors hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                :class="selected === entry.key ? 'border-primary bg-primary/5' : ''"
                                :disabled="entry.disabled"
                                :aria-pressed="selected === entry.key"
                                @click="selected = entry.key"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium">{{ t(entry.titleKey) }}</p>
                                        <p v-if="entry.descriptionKey" class="mt-0.5 text-xs text-muted-foreground">
                                            {{ t(entry.descriptionKey) }}
                                        </p>
                                    </div>
                                    <Badge variant="secondary" class="shrink-0 text-[10px]">
                                        {{ entry.used }}/{{ entry.maxInstances }}
                                    </Badge>
                                </div>

                                <!-- Static geometry preview. NEVER a live render: a 30-entry
                                     catalogue must not fire 30 report queries. -->
                                <div class="mt-2 flex gap-1" aria-hidden="true">
                                    <div
                                        v-for="n in (entry.type === 'stat' ? 1 : 4)"
                                        :key="n"
                                        class="h-6 flex-1 rounded bg-muted"
                                    />
                                </div>

                                <div v-if="entry.tags.length > 0" class="mt-2 flex flex-wrap gap-1">
                                    <Badge v-for="tag in entry.tags" :key="tag" variant="outline" class="text-[10px]">
                                        {{ tag }}
                                    </Badge>
                                </div>
                            </button>
                        </li>
                    </ul>
                </section>
            </div>

            <div v-if="active" class="mt-4 space-y-3 border-t pt-4">
                <div v-if="active.dimensions.length > 0" class="space-y-1.5">
                    <Label for="myra-catalogue-dimension">{{ t('dashboardLayout.dimension') }}</Label>
                    <Select v-model="dimension">
                        <SelectTrigger id="myra-catalogue-dimension">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="name in active.dimensions" :key="name" :value="name">
                                {{ name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <fieldset v-if="active.measures.length > 0" class="space-y-1.5">
                    <legend class="text-sm font-medium">{{ t('dashboardLayout.measures') }}</legend>
                    <div class="flex flex-wrap gap-1.5">
                        <Button
                            v-for="name in active.measures"
                            :key="name"
                            variant="outline"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            :aria-pressed="measures.includes(name)"
                            :class="measures.includes(name) ? 'border-primary text-primary' : ''"
                            @click="toggleMeasure(name)"
                        >
                            {{ name }}
                        </Button>
                    </div>
                </fieldset>

                <fieldset v-if="active.chartTypes.length > 0" class="space-y-1.5">
                    <legend class="text-sm font-medium">{{ t('dashboardLayout.chartType') }}</legend>
                    <RadioGroup v-model="chartType" class="flex flex-wrap gap-3">
                        <div v-for="type in active.chartTypes" :key="type" class="flex items-center gap-1.5">
                            <RadioGroupItem :id="`myra-chart-${type}`" :value="type" />
                            <Label :for="`myra-chart-${type}`" class="text-xs font-normal">{{ type }}</Label>
                        </div>
                    </RadioGroup>
                </fieldset>

                <Button class="w-full" :disabled="active.disabled || atLimit" @click="add">
                    <Plus class="mr-1.5 size-3.5" aria-hidden="true" />
                    {{ t('dashboardLayout.addWidget') }}
                </Button>
                <p v-if="atLimit" class="text-xs text-destructive">{{ t('dashboardLayout.limitReached') }}</p>
            </div>
        </SheetContent>
    </Sheet>
</template>
