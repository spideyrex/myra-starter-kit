<script setup lang="ts">
import { computed, ref } from 'vue';
import FormField from './FormField.vue';
import {
    BaseField,
    isLayoutItem,
    resolveLayout,
    type SchemaItem,
    type LayoutSchema,
    type FieldSchema,
    type FieldType,
} from '@/composables/useFormSchema';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { ChevronDown, Info, AlertTriangle, CheckCircle2, AlertCircle } from 'lucide-vue-next';

const props = defineProps<{
    schema: SchemaItem[];
    form: Record<string, any> & { errors: Record<string, string> };
}>();

interface ResolvedField extends FieldSchema {
    colStyle?: string;
}

interface ResolvedItem {
    isLayout: false;
    field: ResolvedField;
}

interface ResolvedLayout {
    isLayout: true;
    layout: LayoutSchema;
}

type ResolvedEntry = ResolvedItem | ResolvedLayout;

function resolveItems(items: SchemaItem[]): ResolvedEntry[] {
    return items.map(item => {
        if (isLayoutItem(item)) {
            const layout = resolveLayout(item as any);
            return { isLayout: true, layout } as ResolvedLayout;
        }
        if (item instanceof BaseField) {
            return { isLayout: false, field: { ...item.toProps(), colStyle: item.colStyle } } as ResolvedItem;
        }
        return { isLayout: false, field: item as unknown as ResolvedField } as ResolvedItem;
    });
}

const resolved = computed(() => resolveItems(props.schema));

// Track collapsed state for collapsible sections
const collapsedState = ref<Record<string, boolean>>({});

function isCollapsed(label: string, defaultCollapsed: boolean): boolean {
    return collapsedState.value[label] ?? defaultCollapsed;
}

function toggleCollapsed(label: string, defaultCollapsed: boolean) {
    collapsedState.value[label] = !isCollapsed(label, defaultCollapsed);
}

// Wizard step tracking (keyed by index in resolved array)
const wizardSteps = ref<Record<number, number>>({});

function getWizardStep(wizardIndex: number): number {
    return wizardSteps.value[wizardIndex] ?? 0;
}

function setWizardStep(wizardIndex: number, step: number) {
    wizardSteps.value[wizardIndex] = step;
}

// Callout variant config
const calloutConfig: Record<string, { classes: string; icon: typeof Info }> = {
    info: { classes: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200', icon: Info },
    warning: { classes: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200', icon: AlertTriangle },
    success: { classes: 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200', icon: CheckCircle2 },
    danger: { classes: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200', icon: AlertCircle },
};
</script>

<template>
    <template v-for="(entry, index) in resolved" :key="index">
        <!-- Layout: Section -->
        <template v-if="entry.isLayout && entry.layout.layoutType === 'section'">
            <Card class="col-span-full">
                <template v-if="entry.layout.collapsible">
                    <Collapsible :open="!isCollapsed(entry.layout.label || '', entry.layout.collapsed ?? false)">
                        <CardHeader class="cursor-pointer" @click="toggleCollapsed(entry.layout.label || '', entry.layout.collapsed ?? false)">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <component :is="entry.layout.icon" v-if="entry.layout.icon" class="size-5 text-muted-foreground" />
                                    <div>
                                        <CardTitle v-if="entry.layout.label">{{ entry.layout.label }}</CardTitle>
                                        <CardDescription v-if="entry.layout.description">{{ entry.layout.description }}</CardDescription>
                                    </div>
                                </div>
                                <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="{ 'rotate-180': !isCollapsed(entry.layout.label || '', entry.layout.collapsed ?? false) }" />
                            </div>
                        </CardHeader>
                        <CollapsibleContent>
                            <CardContent>
                                <div class="grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
                                    <FormFields :schema="entry.layout.schema" :form="form" />
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </template>
                <template v-else>
                    <CardHeader v-if="entry.layout.label || entry.layout.description">
                        <div class="flex items-center gap-2">
                            <component :is="entry.layout.icon" v-if="entry.layout.icon" class="size-5 text-muted-foreground" />
                            <div>
                                <CardTitle v-if="entry.layout.label">{{ entry.layout.label }}</CardTitle>
                                <CardDescription v-if="entry.layout.description">{{ entry.layout.description }}</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
                            <FormFields :schema="entry.layout.schema" :form="form" />
                        </div>
                    </CardContent>
                </template>
            </Card>
        </template>

        <!-- Layout: Grid -->
        <template v-else-if="entry.isLayout && entry.layout.layoutType === 'grid'">
            <div class="col-span-full grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
                <FormFields :schema="entry.layout.schema" :form="form" />
            </div>
        </template>

        <!-- Layout: Tabs -->
        <template v-else-if="entry.isLayout && entry.layout.layoutType === 'tabs'">
            <Tabs :default-value="(entry.layout.schema[0] as any)?.label || '0'" class="col-span-full">
                <TabsList>
                    <TabsTrigger
                        v-for="(tab, tabIndex) in entry.layout.schema"
                        :key="tabIndex"
                        :value="(tab as any).label || String(tabIndex)"
                    >
                        <component :is="(tab as any).icon" v-if="(tab as any).icon" class="mr-2 size-4" />
                        {{ (tab as any).label || `Tab ${tabIndex + 1}` }}
                        <Badge v-if="(tab as any).badge" variant="secondary" class="ml-2">{{ (tab as any).badge }}</Badge>
                    </TabsTrigger>
                </TabsList>
                <TabsContent
                    v-for="(tab, tabIndex) in entry.layout.schema"
                    :key="tabIndex"
                    :value="(tab as any).label || String(tabIndex)"
                    class="mt-4"
                >
                    <div class="grid gap-4" :style="`grid-template-columns: repeat(${(tab as any).columns || 2}, minmax(0, 1fr))`">
                        <FormFields :schema="(tab as any).schema || []" :form="form" />
                    </div>
                </TabsContent>
            </Tabs>
        </template>

        <!-- Layout: Fieldset -->
        <template v-else-if="entry.isLayout && entry.layout.layoutType === 'fieldset'">
            <fieldset class="col-span-full rounded-lg border p-4">
                <legend v-if="entry.layout.label" class="px-2 text-sm font-medium">{{ entry.layout.label }}</legend>
                <div class="grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
                    <FormFields :schema="entry.layout.schema" :form="form" />
                </div>
            </fieldset>
        </template>

        <!-- Layout: Flex -->
        <template v-else-if="entry.isLayout && entry.layout.layoutType === 'flex'">
            <div
                class="col-span-full flex"
                :class="[
                    entry.layout.direction === 'col' ? 'flex-col' : 'flex-row',
                    entry.layout.justify ? `justify-${entry.layout.justify}` : '',
                    entry.layout.align ? `items-${entry.layout.align}` : 'items-start',
                ]"
                :style="`gap: calc(var(--spacing) * ${entry.layout.gap || 4})`"
            >
                <FormFields :schema="entry.layout.schema" :form="form" />
            </div>
        </template>

        <!-- Layout: Wizard -->
        <template v-else-if="entry.isLayout && entry.layout.layoutType === 'wizard'">
            <div class="col-span-full space-y-6">
                <!-- Step indicators -->
                <div class="flex items-center">
                    <template v-for="(step, si) in (entry.layout.schema as unknown as LayoutSchema[])" :key="si">
                        <div class="flex flex-col items-center">
                            <div
                                class="flex size-8 items-center justify-center rounded-full border-2 text-sm font-medium transition-colors"
                                :class="si <= getWizardStep(index)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-muted-foreground/30 text-muted-foreground'"
                            >
                                <component :is="step.icon" v-if="step.icon && si <= getWizardStep(index)" class="size-4" />
                                <span v-else>{{ si + 1 }}</span>
                            </div>
                            <span class="mt-1 text-xs font-medium" :class="si <= getWizardStep(index) ? 'text-foreground' : 'text-muted-foreground'">
                                {{ step.label }}
                            </span>
                            <span v-if="step.stepDescription" class="text-xs text-muted-foreground">{{ step.stepDescription }}</span>
                        </div>
                        <div v-if="si < (entry.layout.schema as unknown as LayoutSchema[]).length - 1" class="mx-2 h-px flex-1" :class="si < getWizardStep(index) ? 'bg-primary' : 'bg-border'" />
                    </template>
                </div>
                <!-- Current step content -->
                <div class="grid gap-4" :style="`grid-template-columns: repeat(${((entry.layout.schema as unknown as LayoutSchema[])[getWizardStep(index)])?.columns || 2}, minmax(0, 1fr))`">
                    <FormFields :schema="((entry.layout.schema as unknown as LayoutSchema[])[getWizardStep(index)])?.schema || []" :form="form" />
                </div>
                <!-- Navigation -->
                <div class="flex justify-between">
                    <Button variant="outline" :disabled="getWizardStep(index) === 0" @click="setWizardStep(index, getWizardStep(index) - 1)">
                        Previous
                    </Button>
                    <Button
                        v-if="getWizardStep(index) < (entry.layout.schema as unknown as LayoutSchema[]).length - 1"
                        @click="setWizardStep(index, getWizardStep(index) + 1)"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </template>

        <!-- Layout: Callout -->
        <template v-else-if="entry.isLayout && entry.layout.layoutType === 'callout'">
            <div class="col-span-full">
                <Alert :class="calloutConfig[entry.layout.variant || 'info']?.classes">
                    <component :is="entry.layout.icon || calloutConfig[entry.layout.variant || 'info']?.icon" />
                    <AlertTitle v-if="entry.layout.label">{{ entry.layout.label }}</AlertTitle>
                    <AlertDescription v-if="entry.layout.description">{{ entry.layout.description }}</AlertDescription>
                </Alert>
                <div v-if="entry.layout.schema && entry.layout.schema.length > 0" class="mt-3 grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
                    <FormFields :schema="entry.layout.schema" :form="form" />
                </div>
            </div>
        </template>

        <!-- Regular field -->
        <template v-else-if="!entry.isLayout">
            <FormField
                v-bind="entry.field"
                :model-value="form[entry.field.name]"
                :error="form.errors[entry.field.name]"
                :style="entry.field.colStyle"
                @update:model-value="form[entry.field.name] = $event"
            />
        </template>
    </template>
</template>
