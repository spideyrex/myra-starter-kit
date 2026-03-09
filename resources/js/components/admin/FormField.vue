<script setup lang="ts">
import { ref, computed } from 'vue';
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
import PasswordInput from '@/components/PasswordInput.vue';
import TipTapEditor from '@/components/TipTapEditor.vue';
import RepeaterField from '@/components/admin/RepeaterField.vue';
import type { SelectOption } from '@/types/admin';
import type { FieldType, SchemaItem } from '@/composables/useFormSchema';
import { CalendarDate, type DateValue, getLocalTimeZone, today } from '@internationalized/date';
import { CalendarIcon, Plus, Trash2 } from 'lucide-vue-next';
import { marked } from 'marked';

const props = withDefaults(defineProps<{
    label: string;
    name: string;
    error?: string;
    required?: boolean;
    hint?: string;
    type?: FieldType;
    placeholder?: string;
    options?: SelectOption[];
    disabled?: boolean;
    rows?: number;
    // Date fields
    minDate?: string;
    maxDate?: string;
    // Radio fields
    inline?: boolean;
    // File fields
    accept?: string;
    multiple?: boolean;
    maxSize?: number;
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
    // ToggleGroup
    toggleMultiple?: boolean;
    toggleVariant?: 'default' | 'outline';
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
}>(), {
    type: 'text',
    pinLength: 6,
    step: 1,
});

const model = defineModel<any>();

const fieldId = computed(() => `field-${props.name}`);

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (!target.files) return;
    model.value = props.multiple ? Array.from(target.files) : target.files[0];
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

// --- Markdown helpers ---
const renderedMarkdown = computed(() => {
    if (!model.value) return '';
    return marked.parse(String(model.value)) as string;
});
</script>

<template>
    <!-- Hidden field — no visible element -->
    <input v-if="type === 'hidden'" type="hidden" :name="name" :value="model" />

    <div v-else :class="type === 'switch' || type === 'checkbox' ? 'flex items-center gap-3' : 'space-y-2'">
        <template v-if="type === 'switch'">
            <Switch :id="fieldId" v-model="model" :disabled="disabled" />
            <Label :for="fieldId">{{ label }}</Label>
        </template>
        <template v-else-if="type === 'checkbox'">
            <Checkbox :id="fieldId" :checked="model" :disabled="disabled" @update:checked="model = $event" />
            <Label :for="fieldId">{{ label }}</Label>
        </template>
        <template v-else>
            <Label :for="fieldId">
                {{ label }}
                <span v-if="required" class="text-destructive">*</span>
            </Label>

            <slot>
                <TipTapEditor
                    v-if="type === 'richtext'"
                    v-model="model"
                    :placeholder="editorPlaceholder || placeholder || 'Start writing...'"
                    :toolbar="toolbar as any"
                    :disabled="disabled"
                />
                <Textarea
                    v-else-if="type === 'textarea'"
                    :id="fieldId"
                    v-model="model"
                    :placeholder="placeholder"
                    :required="required"
                    :disabled="disabled"
                    :rows="rows"
                />
                <Select
                    v-else-if="type === 'select'"
                    v-model="model"
                    :disabled="disabled"
                >
                    <SelectTrigger :id="fieldId">
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
                />

                <!-- Radio group (shadcn RadioGroup) -->
                <RadioGroup
                    v-else-if="type === 'radio'"
                    :model-value="model"
                    :disabled="disabled"
                    :class="inline ? 'flex flex-wrap gap-4' : 'space-y-2'"
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
                        class="h-10 w-14 cursor-pointer rounded border border-border bg-background p-1"
                        @input="model = ($event.target as HTMLInputElement).value"
                    />
                    <Input
                        :model-value="model || ''"
                        :disabled="disabled"
                        placeholder="#000000"
                        class="max-w-[120px]"
                        @update:model-value="model = $event"
                    />
                </div>

                <!-- File upload -->
                <div v-else-if="type === 'file'">
                    <input
                        :id="fieldId"
                        type="file"
                        :accept="accept"
                        :multiple="multiple"
                        :disabled="disabled"
                        class="block w-full text-sm text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 cursor-pointer"
                        @change="handleFileChange"
                    />
                    <p v-if="maxSize" class="mt-1 text-xs text-muted-foreground">Max size: {{ maxSize }}MB</p>
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

                <!-- Slider -->
                <div v-else-if="type === 'slider'" class="flex items-center gap-4">
                    <Slider
                        v-model="sliderModel"
                        :min="min"
                        :max="max"
                        :step="step"
                        :disabled="disabled"
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
                        <NumberFieldInput />
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

                <!-- Toggle group -->
                <ToggleGroup
                    v-else-if="type === 'toggle-group'"
                    :type="toggleMultiple ? 'multiple' : 'single'"
                    :model-value="model"
                    :variant="toggleVariant"
                    :disabled="disabled"
                    @update:model-value="model = $event"
                >
                    <ToggleGroupItem
                        v-for="opt in options"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </ToggleGroupItem>
                </ToggleGroup>

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
                />

                <!-- CheckboxList -->
                <div v-else-if="type === 'checkbox-list'" class="space-y-2">
                    <Input
                        v-if="checkboxSearchable"
                        v-model="checkboxSearch"
                        placeholder="Search options..."
                        class="h-8"
                    />
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="text-primary hover:underline" @click="model = (options || []).map(o => o.value)">Select all</button>
                        <button type="button" class="text-primary hover:underline" @click="model = []">Deselect all</button>
                    </div>
                    <div class="max-h-48 overflow-y-auto rounded-md border p-2" :class="`grid gap-2 grid-cols-${checkboxColumns || 1}`">
                        <label
                            v-for="opt in filteredCheckboxOptions"
                            :key="opt.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :checked="Array.isArray(model) && model.includes(opt.value)"
                                @update:checked="(v: boolean | 'indeterminate') => toggleCheckboxOption(opt.value, v)"
                            />
                            {{ opt.label }}
                        </label>
                    </div>
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
                            class="h-8"
                            @update:model-value="(v: any) => updateKvPair(i, 'key', String(v))"
                        />
                        <Input
                            :model-value="pair.value"
                            :placeholder="valuePlaceholder || 'Enter value...'"
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

                <!-- Markdown Editor -->
                <div v-else-if="type === 'markdown'" class="grid grid-cols-2 gap-0 rounded-md border overflow-hidden">
                    <Textarea
                        :id="fieldId"
                        v-model="model"
                        :placeholder="placeholder || 'Write markdown...'"
                        :rows="rows || 10"
                        :disabled="disabled"
                        class="rounded-none border-0 border-r font-mono text-sm resize-none"
                    />
                    <div class="overflow-auto p-3 prose prose-sm dark:prose-invert max-w-none" :style="`max-height: ${(rows || 10) * 1.5 + 1.5}rem`" v-html="renderedMarkdown" />
                </div>

                <!-- Calendar / Calendar date picker -->
                <Popover v-else-if="type === 'calendar' || (type === 'date' && useCalendar)">
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            :class="[
                                'w-full justify-start text-left font-normal',
                                !model && 'text-muted-foreground',
                            ]"
                            :disabled="disabled"
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
                />
            </slot>

            <p v-if="hint && !error" class="text-sm text-muted-foreground">{{ hint }}</p>
            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        </template>
    </div>
</template>
