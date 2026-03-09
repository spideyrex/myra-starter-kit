<script setup lang="ts">
import { computed, ref } from 'vue';
import InfolistEntry from './InfolistEntry.vue';
import {
    BaseEntry,
    isLayoutItem,
    resolveLayout,
    type InfolistSchemaItem,
    type EntrySchema,
    type LayoutSchema,
} from '@/composables/useInfolistSchema';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps<{
    schema: InfolistSchemaItem[];
    record: Record<string, any>;
}>();

interface ResolvedEntry {
    isLayout: false;
    entry: EntrySchema;
}

interface ResolvedLayout {
    isLayout: true;
    layout: LayoutSchema;
}

type ResolvedItem = ResolvedEntry | ResolvedLayout;

function resolveItems(items: InfolistSchemaItem[]): ResolvedItem[] {
    return items.map(item => {
        if (isLayoutItem(item as any)) {
            const layout = resolveLayout(item as any);
            return { isLayout: true, layout } as ResolvedLayout;
        }
        if (item instanceof BaseEntry) {
            return { isLayout: false, entry: item.toSchema() } as ResolvedEntry;
        }
        return { isLayout: false, entry: item as EntrySchema } as ResolvedEntry;
    });
}

const resolved = computed(() => resolveItems(props.schema));

const collapsedState = ref<Record<string, boolean>>({});

function isCollapsed(label: string, defaultCollapsed: boolean): boolean {
    return collapsedState.value[label] ?? defaultCollapsed;
}

function toggleCollapsed(label: string, defaultCollapsed: boolean) {
    collapsedState.value[label] = !isCollapsed(label, defaultCollapsed);
}
</script>

<template>
    <template v-for="(item, index) in resolved" :key="index">
        <!-- Layout: Section -->
        <template v-if="item.isLayout && item.layout.layoutType === 'section'">
            <Card class="col-span-full">
                <template v-if="item.layout.collapsible">
                    <Collapsible :open="!isCollapsed(item.layout.label || '', item.layout.collapsed ?? false)">
                        <CardHeader class="cursor-pointer" @click="toggleCollapsed(item.layout.label || '', item.layout.collapsed ?? false)">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <component :is="item.layout.icon" v-if="item.layout.icon" class="size-5 text-muted-foreground" />
                                    <div>
                                        <CardTitle v-if="item.layout.label">{{ item.layout.label }}</CardTitle>
                                        <CardDescription v-if="item.layout.description">{{ item.layout.description }}</CardDescription>
                                    </div>
                                </div>
                                <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="{ 'rotate-180': !isCollapsed(item.layout.label || '', item.layout.collapsed ?? false) }" />
                            </div>
                        </CardHeader>
                        <CollapsibleContent>
                            <CardContent>
                                <dl class="grid gap-4" :style="`grid-template-columns: repeat(${item.layout.columns || 2}, minmax(0, 1fr))`">
                                    <InfolistEntries :schema="item.layout.schema as InfolistSchemaItem[]" :record="record" />
                                </dl>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </template>
                <template v-else>
                    <CardHeader v-if="item.layout.label || item.layout.description">
                        <div class="flex items-center gap-2">
                            <component :is="item.layout.icon" v-if="item.layout.icon" class="size-5 text-muted-foreground" />
                            <div>
                                <CardTitle v-if="item.layout.label">{{ item.layout.label }}</CardTitle>
                                <CardDescription v-if="item.layout.description">{{ item.layout.description }}</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid gap-4" :style="`grid-template-columns: repeat(${item.layout.columns || 2}, minmax(0, 1fr))`">
                            <InfolistEntries :schema="item.layout.schema as InfolistSchemaItem[]" :record="record" />
                        </dl>
                    </CardContent>
                </template>
            </Card>
        </template>

        <!-- Layout: Grid -->
        <template v-else-if="item.isLayout && item.layout.layoutType === 'grid'">
            <dl class="col-span-full grid gap-4" :style="`grid-template-columns: repeat(${item.layout.columns || 2}, minmax(0, 1fr))`">
                <InfolistEntries :schema="item.layout.schema as InfolistSchemaItem[]" :record="record" />
            </dl>
        </template>

        <!-- Layout: Tabs -->
        <template v-else-if="item.isLayout && item.layout.layoutType === 'tabs'">
            <Tabs :default-value="(item.layout.schema[0] as any)?.label || '0'" class="col-span-full">
                <TabsList>
                    <TabsTrigger
                        v-for="(tab, tabIndex) in item.layout.schema"
                        :key="tabIndex"
                        :value="(tab as any).label || String(tabIndex)"
                    >
                        <component :is="(tab as any).icon" v-if="(tab as any).icon" class="mr-2 size-4" />
                        {{ (tab as any).label || `Tab ${tabIndex + 1}` }}
                        <Badge v-if="(tab as any).badge" variant="secondary" class="ml-2">{{ (tab as any).badge }}</Badge>
                    </TabsTrigger>
                </TabsList>
                <TabsContent
                    v-for="(tab, tabIndex) in item.layout.schema"
                    :key="tabIndex"
                    :value="(tab as any).label || String(tabIndex)"
                    class="mt-4"
                >
                    <dl class="grid gap-4" :style="`grid-template-columns: repeat(${(tab as any).columns || 2}, minmax(0, 1fr))`">
                        <InfolistEntries :schema="(tab as any).schema || []" :record="record" />
                    </dl>
                </TabsContent>
            </Tabs>
        </template>

        <!-- Layout: Fieldset -->
        <template v-else-if="item.isLayout && item.layout.layoutType === 'fieldset'">
            <fieldset class="col-span-full rounded-lg border p-4">
                <legend v-if="item.layout.label" class="px-2 text-sm font-medium">{{ item.layout.label }}</legend>
                <dl class="grid gap-4" :style="`grid-template-columns: repeat(${item.layout.columns || 2}, minmax(0, 1fr))`">
                    <InfolistEntries :schema="item.layout.schema as InfolistSchemaItem[]" :record="record" />
                </dl>
            </fieldset>
        </template>

        <!-- Entry -->
        <template v-else-if="!item.isLayout">
            <InfolistEntry :entry="item.entry" :record="record" />
        </template>
    </template>
</template>
