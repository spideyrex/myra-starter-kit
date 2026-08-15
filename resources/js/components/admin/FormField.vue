<script setup lang="ts">
import { ref, computed, defineAsyncComponent, h, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Slider } from '@/components/ui/slider';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { PinInput, PinInputGroup, PinInputSlot } from '@/components/ui/pin-input';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import PasswordInput from '@/components/PasswordInput.vue';
import RepeaterField from '@/components/admin/RepeaterField.vue';
import BuilderField from '@/components/admin/BuilderField.vue';
import SearchableSelect from '@/components/admin/SearchableSelect.vue';
import FieldHint from '@/components/admin/FieldHint.vue';
import type { SelectOption } from '@/types/admin';
import type {
    FieldType, SchemaItem, BuilderBlock, HintAction, SemanticColor, ToggleOption,
    CodeLanguage, MarkdownButton, MarkdownMode,
} from '@/composables/useFormSchema';
import { CalendarDate, type DateValue } from '@internationalized/date';
import { CalendarIcon, Plus, Trash2 } from 'lucide-vue-next';

// Heavy branches load only when their arm actually mounts, so a form page no
// longer ships tiptap / marked / dompurify / cropperjs in the shared chunk.
const paneSkeleton = () => h(Skeleton, { class: 'h-40 w-full rounded-md' });

const LazyTipTapEditor = defineAsyncComponent({ loader: () => import('@/components/TipTapEditor.vue'), loadingComponent: paneSkeleton, delay: 120 });
const LazyImageEditor = defineAsyncComponent(() => import('@/components/ImageEditor.vue'));
const LazyMarkdownEditor = defineAsyncComponent({ loader: () => import('@/components/admin/MarkdownEditorField.vue'), loadingComponent: paneSkeleton, delay: 120 });
const LazyCodeEditor = defineAsyncComponent({ loader: () => import('@/components/admin/CodeEditorField.vue'), loadingComponent: paneSkeleton, delay: 120 });

const page = usePage();
const { t } = useI18n();

const props = withDefaults(defineProps<{
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    hint?: string;
    type?: FieldType;
    placeholder?: string;
    options?: SelectOption[];
    searchable?: boolean;
    optionsUrl?: string;
    disabled?: boolean;
    rows?: number;
    /** Owning form state — needed by hintAction callbacks. */
    form?: Record<string, any>;
    // Date fields
    minDate?: string;
    maxDate?: string;
    // Radio fields
    inline?: boolean;
    // File fields
    accept?: string;
    multiple?: boolean;
    maxSize?: number;
    imageCrop?: boolean;
    imageAspectRatio?: number;
    imageOutputType?: string;
    imageOutputQuality?: number;
    // Rich text fields
    toolbar?: string[];
    editorPlaceholder?: string;
    // Repeater fields
    subSchema?: SchemaItem[];
    minItems?: number;
    maxItems?: number;
    addLabel?: string;
    reorderable?: boolean;
    repeaterCollapsible?: boolean;
    // Builder field (multi-type blocks)
    blocks?: BuilderBlock[];
    // Slider / NumberField
    min?: number;
    max?: number;
    step?: number;
    showValue?: boolean;
    formatOptions?: Intl.NumberFormatOptions;
    // PinInput
    pinLength?: number;
    pinMask?: boolean;
    // TagsInput
    maxTags?: number;
    tagPlaceholder?: string;
    // ToggleGroup / ToggleButtons
    toggleMultiple?: boolean;
    toggleVariant?: 'default' | 'outline';
    toggleOptions?: ToggleOption[];
    toggleColumns?: number;
    toggleSize?: 'sm' | 'default' | 'lg';
    toggleInline?: boolean;
    toggleHideLabels?: boolean;
    toggleMin?: number;
    toggleMax?: number;
    // Calendar date picker
    useCalendar?: boolean;
    dateFormat?: string;
    // TimePicker
    minTime?: string;
    maxTime?: string;
    // CheckboxList
    checkboxSearchable?: boolean;
    checkboxColumns?: number;
    // KeyValue
    keyLabel?: string;
    valueLabel?: string;
    keyPlaceholder?: string;
    valuePlaceholder?: string;
    // Hint
    hintIcon?: Component;
    hintIconTooltip?: string;
    hintColor?: SemanticColor;
    hintAction?: HintAction;
    // Code
    codeLanguage?: CodeLanguage;
    codeLineNumbers?: boolean;
    codeWrap?: boolean;
    codeReadOnly?: boolean;
    codeIndentWithTab?: boolean;
    codeAutocomplete?: boolean;
    codeCopyable?: boolean;
    codeTabSize?: number;
    codeMinHeight?: string;
    codeMaxHeight?: string;
    codeFilename?: string;
    // Markdown
    mdToolbar?: MarkdownButton[];
    mdMode?: MarkdownMode;
    mdModeSwitcher?: boolean;
    mdFullscreen?: boolean;
    mdCounter?: boolean;
    mdMaxLength?: number;
    mdMinHeight?: string;
    mdMaxHeight?: string;
    mdUploadRoute?: string;
    mdMaxUploadKb?: number;
    mdAcceptedTypes?: string[];
}>(), {
    type: 'text',
    pinLength: 6,
    step: 1,
    hintColor: 'muted',
    codeMinHeight: '12rem',
});

const model = defineModel<any>();

const fieldId = computed(() => `field-${props.name}`);

const describedBy = computed(() => [
    props.hint ? `${fieldId.value}-hint` : null,
    props.error ? `${fieldId.value}-error` : null,
].filter(Boolean).join(' ') || undefined);

// Image editor state
const imageEditorOpen = ref(false);
const imageEditorFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    const file = target.files[0];

    if (props.imageCrop && file.type.startsWith('image/')) {
        imageEditorFile.value = file;
        imageEditorOpen.value = true;
        return;
    }

    model.value = props.multiple ? Array.from(target.files) : file;
}

function handleImageSave(file: File, dataUrl: string) {
    model.value = file;
    imagePreview.value = dataUrl;
    imageEditorFile.value = null;
}

function handleImageCancel() {
    imageEditorFile.value = null;
    imagePreview.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

// --- Calendar date conversion helpers ---
const calendarValue = computed<DateValue | undefined>({
    get() {
        if (!model.value) return undefined;
        const str = String(model.value);
        const parts = str.split('-');
        if (parts.length >= 3) {
            return new CalendarDate(Number(parts[0]), Number(parts[1]), Number(parts[2]));
        }
        return undefined;
    },
    set(val: DateValue | undefined) {
        if (!val) {
            model.value = '';
            return;
        }
        const y = String(val.year).padStart(4, '0');
        const m = String(val.month).padStart(2, '0');
        const d = String(val.day).padStart(2, '0');
        model.value = `${y}-${m}-${d}`;
    },
});

const calendarDisplayText = computed(() => {
    if (!model.value) return '';
    const str = String(model.value);
    const parts = str.split('-');
    if (parts.length >= 3) {
        const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }
    return str;
});

// Slider model helper (Slider expects number[])
const sliderModel = computed<number[]>({
    get() {
        const val = model.value;
        if (Array.isArray(val)) return val;
        return [Number(val) || 0];
    },
    set(val: number[]) {
        model.value = val[0] ?? 0;
    },
});

// PinInput model helper (PinInput expects string[])
const pinModel = computed<string[]>({
    get() {
        const val = String(model.value || '');
        return val.split('');
    },
    set(val: string[]) {
        model.value = val.join('');
    },
});

// --- CheckboxList helpers ---
const checkboxSearch = ref('');
const filteredCheckboxOptions = computed(() => {
    if (!props.options) return [];
    if (!checkboxSearch.value) return props.options;
    const q = checkboxSearch.value.toLowerCase();
    return props.options.filter(o => o.label.toLowerCase().includes(q));
});

function toggleCheckboxOption(value: string, checked: boolean | 'indeterminate') {
    const current: string[] = Array.isArray(model.value) ? [...model.value] : [];
    if (checked === true) {
        if (!current.includes(value)) current.push(value);
    } else {
        const idx = current.indexOf(value);
        if (idx >= 0) current.splice(idx, 1);
    }
    model.value = current;
}

// --- KeyValue helpers ---
function addKvPair() {
    const current = Array.isArray(model.value) ? [...model.value] : [];
    current.push({ key: '', value: '' });
    model.value = current;
}

function removeKvPair(index: number) {
    const current = Array.isArray(model.value) ? [...model.value] : [];
    current.splice(index, 1);
    model.value = current;
}

function updateKvPair(index: number, field: 'key' | 'value', value: string) {
    const current = Array.isArray(model.value) ? [...model.value] : [];
    if (current[index]) {
        current[index] = { ...current[index], [field]: value };
        model.value = current;
    }
}

// --- ToggleGroup / ToggleButtons helpers ---
const resolvedToggleOptions = computed<ToggleOption[]>(() => {
    if (props.toggleOptions?.length) return props.toggleOptions;
    return (props.options ?? []).map(o => ({ value: String(o.value), label: o.label }));
});

// Multi-select state is ALWAYS an array — no Eloquent `array` cast needed on the model.
const normalisedToggleModel = computed(() => props.toggleMultiple
    ? (Array.isArray(model.value) ? model.value : model.value == null ? [] : [model.value])
    : model.value);

function onToggleChange(v: any) {
    if (!props.toggleMultiple) {
        model.value = v;
        return;
    }
    const next: string[] = Array.isArray(v) ? v : v == null ? [] : [v];
    if (props.toggleMin != null && next.length < props.toggleMin) return;
    model.value = next;
}

// Advisory only — the rule that binds is the server's (BaseField.rules()).
const selectionMsgId = computed(() => `${fieldId.value}-selection`);

const selectionCount = computed(() => {
    if (props.type === 'checkbox-list') return Array.isArray(model.value) ? model.value.length : 0;
    return Array.isArray(normalisedToggleModel.value) ? normalisedToggleModel.value.length : 0;
});

const hasSelectionBounds = computed(() => props.toggleMin != null || props.toggleMax != null);

const selectionOutOfRange = computed(() => {
    if (!hasSelectionBounds.value) return false;
    const n = selectionCount.value;
    return (props.toggleMin != null && n < props.toggleMin) || (props.toggleMax != null && n > props.toggleMax);
});

const selectionDescribedBy = computed(() => (selectionOutOfRange.value
    ? [describedBy.value, selectionMsgId.value].filter(Boolean).join(' ')
    : describedBy.value));

const selectionMessage = computed(() => t('validation.selectBetween', {
    min: props.toggleMin ?? 0,
    max: props.toggleMax ?? (props.options?.length ?? 0),
}));

function atToggleMax(value: string): boolean {
    if (!props.toggleMultiple || props.toggleMax == null) return false;
    const cur = (normalisedToggleModel.value ?? []) as string[];
    return cur.length >= props.toggleMax && !cur.includes(value);
}

const TOGGLE_COLOR_CLASS: Record<SemanticColor, string> = {
    muted: '',
    primary: 'data-[state=on]:bg-primary data-[state=on]:text-primary-foreground',
    info: 'data-[state=on]:bg-info data-[state=on]:text-info-foreground',
    success: 'data-[state=on]:bg-success data-[state=on]:text-success-foreground',
    warning: 'data-[state=on]:bg-warning data-[state=on]:text-warning-foreground',
    danger: 'data-[state=on]:bg-destructive data-[state=on]:text-white',
};

function toggleColorClass(color?: SemanticColor): string {
    return color ? TOGGLE_COLOR_CLASS[color] ?? '' : '';
}

function toggleAriaLabel(opt: ToggleOption): string | undefined {
    if (!props.toggleHideLabels) return undefined;
    return opt.tooltip ? `${opt.label}. ${opt.tooltip}` : opt.label;
}
</script>

<template>
    <!-- Hidden field — no visible element -->
    <input v-if="type === 'hidden'" type="hidden" :name="name" :value="model" />

    <div v-else class="space-y-2">
        <template v-if="type === 'switch'">
            <div class="flex items-center gap-3">
                <Switch
                    :id="fieldId"
                    v-model="model"
                    :disabled="disabled"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                />
                <Label :id="`${fieldId}-label`" :for="fieldId">{{ label }}</Label>
            </div>
        </template>
        <template v-else-if="type === 'checkbox'">
            <div class="flex items-center gap-3">
                <Checkbox
                    :id="fieldId"
                    :model-value="model"
                    :disabled="disabled"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                    @update:model-value="model = $event"
                />
                <Label :id="`${fieldId}-label`" :for="fieldId">{{ label }}</Label>
            </div>
        </template>
        <template v-else>
            <Label :id="`${fieldId}-label`" :for="fieldId">
                {{ label }}
                <span v-if="required" class="text-destructive">*</span>
            </Label>

            <slot>
                <LazyTipTapEditor
                    v-if="type === 'richtext'"
                    v-model="model"
                    :placeholder="editorPlaceholder || placeholder || 'Start writing...'"
                    :toolbar="toolbar as any"
                    :disabled="disabled"
                    :ai-enabled="(page.props as any).aiEnabled"
                />
                <Textarea
                    v-else-if="type === 'textarea'"
                    :id="fieldId"
                    v-model="model"
                    :placeholder="placeholder"
                    :required="required"
                    :disabled="disabled"
                    :rows="rows"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                />
                <SearchableSelect
                    v-else-if="type === 'select' && searchable"
                    :id="fieldId"
                    v-model="model"
                    :options="options || []"
                    :options-url="optionsUrl"
                    :placeholder="placeholder"
                    :disabled="disabled"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                />
                <Select
                    v-else-if="type === 'select'"
                    v-model="model"
                    :disabled="disabled"
                >
                    <SelectTrigger :id="fieldId" :aria-describedby="describedBy" :aria-invalid="!!error">
                        <SelectValue :placeholder="placeholder" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="opt in options"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <PasswordInput
                    v-else-if="type === 'password'"
                    :id="fieldId"
                    v-model="model"
                    :placeholder="placeholder"
                    :required="required"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                />

                <!-- Code editor (lazy: CodeMirror 6) -->
                <LazyCodeEditor
                    v-else-if="type === 'code'"
                    :field-id="fieldId"
                    :label="label"
                    :error="error"
                    :described-by="describedBy"
                    :disabled="disabled"
                    :model-value="model"
                    :code-language="codeLanguage"
                    :code-line-numbers="codeLineNumbers"
                    :code-wrap="codeWrap"
                    :code-read-only="codeReadOnly"
                    :code-indent-with-tab="codeIndentWithTab"
                    :code-autocomplete="codeAutocomplete"
                    :code-copyable="codeCopyable"
                    :code-tab-size="codeTabSize"
                    :code-min-height="codeMinHeight"
                    :code-max-height="codeMaxHeight"
                    :code-filename="codeFilename"
                    @update:model-value="model = $event"
                />

                <!-- Radio group (shadcn RadioGroup) -->
                <RadioGroup
                    v-else-if="type === 'radio'"
                    :model-value="model"
                    :disabled="disabled"
                    :class="inline ? 'flex flex-wrap gap-4' : 'space-y-2'"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                    @update:model-value="model = $event"
                >
                    <div
                        v-for="opt in options"
                        :key="opt.value"
                        class="flex items-center gap-2"
                    >
                        <RadioGroupItem :id="`${fieldId}-${opt.value}`" :value="opt.value" />
                        <Label :for="`${fieldId}-${opt.value}`" class="font-normal cursor-pointer">
                            {{ opt.label }}
                        </Label>
                    </div>
                </RadioGroup>

                <!-- Color picker -->
                <div v-else-if="type === 'color'" class="flex items-center gap-3">
                    <input
                        :id="fieldId"
                        type="color"
                        :value="model || '#000000'"
                        :disabled="disabled"
                        :aria-describedby="describedBy"
                        :aria-invalid="!!error"
                        class="h-10 w-14 cursor-pointer rounded border border-border bg-background p-1"
                        @input="model = ($event.target as HTMLInputElement).value"
                    />
                    <Input
                        :model-value="model || ''"
                        :disabled="disabled"
                        :aria-label="label"
                        placeholder="#000000"
                        class="max-w-[120px]"
                        @update:model-value="model = $event"
                    />
                </div>

                <!-- File upload -->
                <div v-else-if="type === 'file'">
                    <input
                        :id="fieldId"
                        ref="fileInputRef"
                        type="file"
                        :accept="accept"
                        :multiple="multiple"
                        :disabled="disabled"
                        :aria-describedby="describedBy"
                        :aria-invalid="!!error"
                        class="block w-full text-sm text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 cursor-pointer"
                        @change="handleFileChange"
                    />
                    <p v-if="maxSize" class="mt-1 text-xs text-muted-foreground">Max size: {{ maxSize }}MB</p>
                    <div v-if="imagePreview" class="mt-2">
                        <img :src="imagePreview" alt="Cropped preview" class="h-24 w-auto rounded-md border object-cover" />
                    </div>
                    <LazyImageEditor
                        v-if="imageCrop"
                        v-model:open="imageEditorOpen"
                        :file="imageEditorFile"
                        :aspect-ratio="imageAspectRatio"
                        :output-type="imageOutputType"
                        :output-quality="imageOutputQuality"
                        @save="handleImageSave"
                        @cancel="handleImageCancel"
                    />
                </div>

                <!-- Repeater field -->
                <RepeaterField
                    v-else-if="type === 'repeater'"
                    v-model="model"
                    :sub-schema="subSchema || []"
                    :min-items="minItems"
                    :max-items="maxItems"
                    :add-label="addLabel"
                    :reorderable="reorderable"
                    :collapsible="repeaterCollapsible"
                    :disabled="disabled"
                    :errors="error"
                />

                <!-- Builder field (multi-type blocks) -->
                <BuilderField
                    v-else-if="type === 'builder'"
                    v-model="model"
                    :blocks="blocks || []"
                    :min-items="minItems"
                    :max-items="maxItems"
                    :add-label="addLabel"
                    :reorderable="reorderable"
                    :collapsible="repeaterCollapsible"
                    :disabled="disabled"
                    :errors="error"
                />

                <!-- Slider -->
                <div v-else-if="type === 'slider'" class="flex items-center gap-4">
                    <Slider
                        v-model="sliderModel"
                        :min="min"
                        :max="max"
                        :step="step"
                        :disabled="disabled"
                        :aria-describedby="describedBy"
                        :aria-invalid="!!error"
                        class="flex-1"
                    />
                    <span v-if="showValue" class="min-w-[3ch] text-right text-sm font-medium tabular-nums">
                        {{ sliderModel[0] }}
                    </span>
                </div>

                <!-- Number field -->
                <NumberField
                    v-else-if="type === 'number-field'"
                    :id="fieldId"
                    :model-value="model"
                    :min="min"
                    :max="max"
                    :step="step"
                    :format-options="formatOptions"
                    :disabled="disabled"
                    @update:model-value="model = $event"
                >
                    <NumberFieldContent>
                        <NumberFieldDecrement />
                        <NumberFieldInput :aria-describedby="describedBy" :aria-invalid="!!error" />
                        <NumberFieldIncrement />
                    </NumberFieldContent>
                </NumberField>

                <!-- Pin input -->
                <PinInput
                    v-else-if="type === 'pin-input'"
                    :id="fieldId"
                    v-model="pinModel"
                    :mask="pinMask"
                    :disabled="disabled"
                    :placeholder="placeholder || '○'"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                >
                    <PinInputGroup>
                        <PinInputSlot v-for="(_, i) in pinLength" :key="i" :index="i" />
                    </PinInputGroup>
                </PinInput>

                <!-- Tags input -->
                <TagsInput
                    v-else-if="type === 'tags-input'"
                    :model-value="model || []"
                    :disabled="disabled"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                    @update:model-value="model = $event"
                >
                    <TagsInputItem v-for="tag in (model || [])" :key="tag" :value="tag">
                        <TagsInputItemText />
                        <TagsInputItemDelete />
                    </TagsInputItem>
                    <TagsInputInput
                        :placeholder="tagPlaceholder || placeholder || 'Add tag...'"
                        :disabled="disabled || (maxTags != null && (model || []).length >= maxTags)"
                    />
                </TagsInput>

                <!-- Toggle group / Toggle buttons -->
                <div v-else-if="type === 'toggle-group'" class="space-y-1.5">
                    <ToggleGroup
                        :id="fieldId"
                        :type="toggleMultiple ? 'multiple' : 'single'"
                        :model-value="normalisedToggleModel"
                        :variant="toggleVariant"
                        :size="toggleSize"
                        :spacing="toggleColumns ? 2 : 0"
                        :disabled="disabled"
                        :class="toggleColumns ? 'grid w-full' : (toggleInline ? 'flex flex-wrap' : 'flex w-full flex-wrap')"
                        :style="toggleColumns ? `grid-template-columns: repeat(${toggleColumns}, minmax(0,1fr))` : undefined"
                        :aria-describedby="selectionDescribedBy"
                        :aria-invalid="!!error || selectionOutOfRange"
                        @update:model-value="onToggleChange"
                    >
                        <ToggleGroupItem
                            v-for="opt in resolvedToggleOptions"
                            :key="opt.value"
                            :value="opt.value"
                            :disabled="disabled || opt.disabled || atToggleMax(opt.value)"
                            :aria-label="toggleAriaLabel(opt)"
                            :title="opt.tooltip"
                            :class="['h-auto py-2', toggleColorClass(opt.color)]"
                        >
                            <component :is="opt.icon" v-if="opt.icon" class="size-4" :class="toggleHideLabels ? '' : 'mr-2'" aria-hidden="true" />
                            <span v-if="!toggleHideLabels" class="flex flex-col items-start text-left">
                                <span>{{ opt.label }}</span>
                                <span v-if="opt.description" class="text-xs opacity-70">{{ opt.description }}</span>
                                <span v-if="opt.tooltip" class="sr-only">{{ opt.tooltip }}</span>
                            </span>
                        </ToggleGroupItem>
                    </ToggleGroup>
                    <p v-if="selectionOutOfRange" :id="selectionMsgId" class="text-xs text-destructive" aria-live="polite">
                        {{ selectionMessage }}
                    </p>
                </div>

                <!-- TimePicker -->
                <Input
                    v-else-if="type === 'time'"
                    :id="fieldId"
                    v-model="model"
                    type="time"
                    :min="minTime"
                    :max="maxTime"
                    :required="required"
                    :disabled="disabled"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                />

                <!-- CheckboxList -->
                <div v-else-if="type === 'checkbox-list'" class="space-y-2">
                    <Input
                        v-if="checkboxSearchable"
                        v-model="checkboxSearch"
                        placeholder="Search options..."
                        :aria-label="`Search ${label}`"
                        class="h-8"
                    />
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="text-primary hover:underline" @click="model = (options || []).map(o => o.value)">Select all</button>
                        <button type="button" class="text-primary hover:underline" @click="model = []">Deselect all</button>
                    </div>
                    <div
                        class="max-h-48 overflow-y-auto rounded-md border p-2 grid gap-2"
                        :style="`grid-template-columns: repeat(${checkboxColumns || 1}, minmax(0, 1fr))`"
                        :aria-describedby="selectionDescribedBy"
                        :aria-invalid="!!error || selectionOutOfRange"
                    >
                        <label
                            v-for="opt in filteredCheckboxOptions"
                            :key="opt.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :model-value="Array.isArray(model) && model.includes(opt.value)"
                                :disabled="disabled"
                                @update:model-value="(v: boolean | 'indeterminate') => toggleCheckboxOption(opt.value, v)"
                            />
                            {{ opt.label }}
                        </label>
                    </div>
                    <p v-if="selectionOutOfRange" :id="selectionMsgId" class="text-xs text-destructive" aria-live="polite">
                        {{ selectionMessage }}
                    </p>
                </div>

                <!-- KeyValue -->
                <div v-else-if="type === 'key-value'" class="space-y-2">
                    <div class="grid grid-cols-[1fr_1fr_auto] gap-2 text-xs font-medium text-muted-foreground">
                        <span>{{ keyLabel || 'Key' }}</span>
                        <span>{{ valueLabel || 'Value' }}</span>
                        <span class="w-8" />
                    </div>
                    <div
                        v-for="(pair, i) in (Array.isArray(model) ? model : [])"
                        :key="i"
                        class="grid grid-cols-[1fr_1fr_auto] gap-2"
                    >
                        <Input
                            :model-value="pair.key"
                            :placeholder="keyPlaceholder || 'Enter key...'"
                            :aria-label="`${keyLabel || 'Key'} ${i + 1}`"
                            class="h-8"
                            @update:model-value="(v: any) => updateKvPair(i, 'key', String(v))"
                        />
                        <Input
                            :model-value="pair.value"
                            :placeholder="valuePlaceholder || 'Enter value...'"
                            :aria-label="`${valueLabel || 'Value'} ${i + 1}`"
                            class="h-8"
                            @update:model-value="(v: any) => updateKvPair(i, 'value', String(v))"
                        />
                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0 text-destructive" @click="removeKvPair(i)">
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="disabled || (maxItems != null && Array.isArray(model) && model.length >= maxItems)"
                        @click="addKvPair"
                    >
                        <Plus class="mr-1 size-4" />
                        Add Pair
                    </Button>
                </div>

                <!-- Markdown editor (lazy: marked + dompurify) -->
                <LazyMarkdownEditor
                    v-else-if="type === 'markdown'"
                    :field-id="fieldId"
                    :label="label"
                    :error="error"
                    :described-by="describedBy"
                    :disabled="disabled"
                    :placeholder="placeholder"
                    :model-value="model"
                    :rows="rows"
                    :md-toolbar="mdToolbar"
                    :md-mode="mdMode"
                    :md-mode-switcher="mdModeSwitcher"
                    :md-fullscreen="mdFullscreen"
                    :md-counter="mdCounter"
                    :md-max-length="mdMaxLength"
                    :md-min-height="mdMinHeight"
                    :md-max-height="mdMaxHeight"
                    :md-upload-route="mdUploadRoute"
                    :md-max-upload-kb="mdMaxUploadKb"
                    :md-accepted-types="mdAcceptedTypes"
                    @update:model-value="model = $event"
                />

                <!-- Calendar / Calendar date picker -->
                <Popover v-else-if="type === 'calendar' || (type === 'date' && useCalendar)">
                    <PopoverTrigger as-child>
                        <Button
                            :id="fieldId"
                            variant="outline"
                            :class="[
                                'w-full justify-start text-left font-normal',
                                !model && 'text-muted-foreground',
                            ]"
                            :disabled="disabled"
                            :aria-describedby="describedBy"
                            :aria-invalid="!!error"
                        >
                            <CalendarIcon class="mr-2 size-4" />
                            {{ calendarDisplayText || placeholder || 'Pick a date' }}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0" align="start">
                        <Calendar
                            v-model="calendarValue"
                            initial-focus
                        />
                    </PopoverContent>
                </Popover>

                <!-- Date / DateTime / other native inputs -->
                <Input
                    v-else
                    :id="fieldId"
                    v-model="model"
                    :type="type"
                    :placeholder="placeholder"
                    :required="required"
                    :disabled="disabled"
                    :min="minDate"
                    :max="maxDate"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!error"
                />
            </slot>
        </template>

        <FieldHint
            :field-id="fieldId"
            :hint="hint"
            :hint-icon="hintIcon"
            :hint-icon-tooltip="hintIconTooltip"
            :hint-color="hintColor"
            :hint-action="hintAction"
            :error="error"
            :form="form"
        />
    </div>
</template>
