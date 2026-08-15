<script setup lang="ts">
import { computed, ref } from 'vue';
import FormField from './FormField.vue';
import {
    BaseField,
    isLayoutItem,
    resolveLayout,
    evaluateVisibility,
    collectFieldNames,
    type SchemaItem,
    type LayoutSchema,
    type FieldSchema,
    type FieldType,
    type VisibilityCondition,
    type ValidationRule,
} from '@/composables/useFormSchema';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { ChevronDown, ChevronLeft, ChevronRight, Check, Info, AlertTriangle, CheckCircle2, AlertCircle } from 'lucide-vue-next';

const props = defineProps<{
    schema: SchemaItem[];
    form: Record<string, any> & { errors: Record<string, string> };
}>();

interface ResolvedField extends FieldSchema {
    colStyle?: string;
    visibleWhen?: VisibilityCondition;
    hiddenWhen?: VisibilityCondition;
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

function isFieldVisible(field: ResolvedField): boolean {
    if (field.visibleWhen) {
        return evaluateVisibility(field.visibleWhen, props.form);
    }
    if (field.hiddenWhen) {
        return !evaluateVisibility(field.hiddenWhen, props.form);
    }
    return true;
}

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
const wizardErrors = ref<Record<number, string[]>>({});

function getWizardStep(wizardIndex: number): number {
    return wizardSteps.value[wizardIndex] ?? 0;
}

function setWizardStep(wizardIndex: number, step: number) {
    wizardSteps.value[wizardIndex] = step;
}

function getWizardErrors(wizardIndex: number): string[] {
    return wizardErrors.value[wizardIndex] ?? [];
}

function validateWizardStep(wizardIndex: number, stepLayout: LayoutSchema): boolean {
    const errors: string[] = [];
    const fieldNames = collectFieldNames(stepLayout.schema);
    const rules = stepLayout.validationRules || {};

    // Check required fields
    for (const item of stepLayout.schema) {
        const field = item instanceof BaseField ? item.toProps() : (isLayoutItem(item) ? null : item as unknown as FieldSchema);
        if (field && 'name' in field && 'required' in field && field.required) {
            const val = props.form[field.name];
            if (val === undefined || val === null || val === '' || (Array.isArray(val) && val.length === 0)) {
                const label = field.label || field.name;
                const msg = `${label} is required`;
                errors.push(msg);
                props.form.errors[field.name] = msg;
            }
        }
    }

    // Check nested required fields (within layout items)
    function checkNestedRequired(items: SchemaItem[]) {
        for (const item of items) {
            if (isLayoutItem(item)) {
                const layout = resolveLayout(item as any);
                checkNestedRequired(layout.schema);
            } else {
                const field = item instanceof BaseField ? item.toProps() : item as unknown as FieldSchema;
                if (field && 'name' in field && 'required' in field && field.required) {
                    const val = props.form[field.name];
                    if (val === undefined || val === null || val === '' || (Array.isArray(val) && val.length === 0)) {
                        const label = field.label || field.name;
                        const msg = `${label} is required`;
                        if (!errors.includes(msg)) {
                            errors.push(msg);
                            props.form.errors[field.name] = msg;
                        }
                    }
                }
            }
        }
    }
    checkNestedRequired(stepLayout.schema);

    // Run custom validators
    for (const [fieldName, fieldRules] of Object.entries(rules)) {
        for (const rule of fieldRules) {
            const result = rule(props.form[fieldName], props.form);
            if (result !== true) {
                errors.push(result);
                props.form.errors[fieldName] = result;
            }
        }
    }

    wizardErrors.value[wizardIndex] = errors;
    return errors.length === 0;
}

function handleWizardNext(wizardIndex: number, steps: LayoutSchema[]) {
    const currentStep = getWizardStep(wizardIndex);
    const stepLayout = steps[currentStep];

    // Clear previous errors for this step's fields
    const fieldNames = collectFieldNames(stepLayout.schema);
    for (const name of fieldNames) {
        delete props.form.errors[name];
    }

    if (validateWizardStep(wizardIndex, stepLayout)) {
        wizardErrors.value[wizardIndex] = [];
        setWizardStep(wizardIndex, currentStep + 1);
    }
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
                                <div class="form-grid grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
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
                        <div class="form-grid grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
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
                    <div class="form-grid grid gap-4" :style="`grid-template-columns: repeat(${(tab as any).columns || 2}, minmax(0, 1fr))`">
                        <FormFields :schema="(tab as any).schema || []" :form="form" />
                    </div>
                </TabsContent>
            </Tabs>
        </template>

        <!-- Layout: Fieldset -->
        <template v-else-if="entry.isLayout && entry.layout.layoutType === 'fieldset'">
            <fieldset class="col-span-full rounded-lg border p-4">
                <legend v-if="entry.layout.label" class="px-2 text-sm font-medium">{{ entry.layout.label }}</legend>
                <div class="form-grid grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
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
                <!-- Progress bar -->
                <Progress :model-value="((getWizardStep(index) + 1) / (entry.layout.schema as unknown as LayoutSchema[]).length) * 100" class="h-1.5" />

                <!-- Step indicators -->
                <div class="flex items-center overflow-x-auto pb-2">
                    <template v-for="(step, si) in (entry.layout.schema as unknown as LayoutSchema[])" :key="si">
                        <div class="flex shrink-0 flex-col items-center">
                            <!-- Completed step -->
                            <div
                                v-if="si < getWizardStep(index)"
                                class="flex size-7 items-center justify-center rounded-full border-2 border-success bg-success text-white transition-all duration-300 sm:size-8"
                            >
                                <Check class="size-3.5 sm:size-4" />
                            </div>
                            <!-- Active step -->
                            <div
                                v-else-if="si === getWizardStep(index)"
                                class="relative flex size-7 items-center justify-center rounded-full border-2 text-xs font-bold transition-all duration-300 sm:size-8 sm:text-sm"
                                :class="getWizardErrors(index).length > 0
                                    ? 'border-destructive bg-destructive/10 text-destructive'
                                    : 'border-primary bg-primary text-primary-foreground ring-4 ring-primary/20'"
                            >
                                <span>{{ si + 1 }}</span>
                                <span
                                    v-if="getWizardErrors(index).length > 0"
                                    class="absolute -right-1.5 -top-1.5 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-white"
                                >
                                    {{ getWizardErrors(index).length }}
                                </span>
                            </div>
                            <!-- Upcoming step -->
                            <div
                                v-else
                                class="flex size-7 items-center justify-center rounded-full border-2 border-muted-foreground/25 text-xs font-medium text-muted-foreground transition-all duration-300 sm:size-8 sm:text-sm"
                            >
                                <span>{{ si + 1 }}</span>
                            </div>
                            <span class="mt-1.5 text-[10px] font-medium sm:text-xs" :class="si <= getWizardStep(index) ? 'text-foreground' : 'text-muted-foreground'">
                                {{ step.label }}
                            </span>
                            <span v-if="step.stepDescription" class="hidden text-xs text-muted-foreground sm:block">{{ step.stepDescription }}</span>
                        </div>
                        <!-- Connector line -->
                        <div v-if="si < (entry.layout.schema as unknown as LayoutSchema[]).length - 1" class="mx-1 h-0.5 flex-1 rounded-full transition-all duration-500 sm:mx-2" :class="si < getWizardStep(index) ? 'bg-success' : 'bg-border'" />
                    </template>
                </div>

                <!-- Validation error summary -->
                <div v-if="getWizardErrors(index).length > 0" class="rounded-md border border-destructive/30 bg-destructive/10 p-3">
                    <p class="mb-1 text-sm font-medium text-destructive">Please fix the following errors:</p>
                    <ul class="list-inside list-disc space-y-0.5 text-sm text-destructive">
                        <li v-for="(err, ei) in getWizardErrors(index)" :key="ei">{{ err }}</li>
                    </ul>
                </div>

                <!-- Current step content with transition -->
                <div
                    :key="`wizard-${index}-step-${getWizardStep(index)}`"
                    class="animate-fade-in-up form-grid grid gap-4"
                    :style="`grid-template-columns: repeat(${((entry.layout.schema as unknown as LayoutSchema[])[getWizardStep(index)])?.columns || 2}, minmax(0, 1fr))`"
                >
                    <FormFields :schema="((entry.layout.schema as unknown as LayoutSchema[])[getWizardStep(index)])?.schema || []" :form="form" />
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between">
                    <Button variant="outline" :disabled="getWizardStep(index) === 0" @click="wizardErrors[index] = []; setWizardStep(index, getWizardStep(index) - 1)">
                        <ChevronLeft class="mr-1 size-4" />
                        Previous
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Step {{ getWizardStep(index) + 1 }} of {{ (entry.layout.schema as unknown as LayoutSchema[]).length }}
                    </span>
                    <Button
                        v-if="getWizardStep(index) < (entry.layout.schema as unknown as LayoutSchema[]).length - 1"
                        @click="handleWizardNext(index, entry.layout.schema as unknown as LayoutSchema[])"
                    >
                        Next
                        <ChevronRight class="ml-1 size-4" />
                    </Button>
                    <div v-else />
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
                <div v-if="entry.layout.schema && entry.layout.schema.length > 0" class="form-grid mt-3 grid gap-4" :style="`grid-template-columns: repeat(${entry.layout.columns || 2}, minmax(0, 1fr))`">
                    <FormFields :schema="entry.layout.schema" :form="form" />
                </div>
            </div>
        </template>

        <!-- Regular field -->
        <template v-else-if="!entry.isLayout">
            <FormField
                v-show="isFieldVisible(entry.field)"
                v-bind="entry.field"
                :form="form"
                :model-value="form[entry.field.name]"
                :error="form.errors[entry.field.name]"
                :style="entry.field.colStyle"
                @update:model-value="form[entry.field.name] = $event"
            />
        </template>
    </template>
</template>
