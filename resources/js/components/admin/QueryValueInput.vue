<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    TagsInput,
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText,
} from '@/components/ui/tags-input';
import { operatorArity } from '@/composables/useTableFilters';
import type { QueryConstraintSchema, QueryRuleValue } from '@/types/query-builder';

const props = defineProps<{
    constraint?: QueryConstraintSchema;
    operator: string;
    modelValue: QueryRuleValue;
    labelledBy?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: QueryRuleValue] }>();

const { t } = useI18n();

const arity = computed(() => operatorArity(props.operator));
const type = computed(() => props.constraint?.type ?? 'text');

/** count_* and in_month/in_year always take a number regardless of field type. */
const numericOperator = computed(() =>
    ['count_gte', 'count_lte', 'count_eq', 'in_month', 'in_year'].includes(props.operator));

const inputKind = computed<'text' | 'number' | 'date' | 'select' | 'list' | 'none'>(() => {
    if (arity.value === 0) return 'none';
    if (arity.value === -1) return 'list';
    if (numericOperator.value || type.value === 'number') return 'number';
    if (type.value === 'date') return 'date';
    if (type.value === 'select') return 'select';
    return 'text';
});

const pair = computed<[string, string]>(() => {
    const v = props.modelValue;
    return Array.isArray(v) ? [String(v[0] ?? ''), String(v[1] ?? '')] : ['', ''];
});

const list = computed<string[]>(() => {
    const v = props.modelValue;
    if (Array.isArray(v)) return v.map(String);
    return v === null || v === '' ? [] : [String(v)];
});

const single = computed(() => (Array.isArray(props.modelValue) ? '' : String(props.modelValue ?? '')));

const options = computed(() => props.constraint?.options ?? []);

function setSingle(v: unknown) {
    emit('update:modelValue', String(v ?? ''));
}

function setPair(index: 0 | 1, v: unknown) {
    const next: [string, string] = [...pair.value] as [string, string];
    next[index] = String(v ?? '');
    emit('update:modelValue', next);
}

function setList(v: unknown) {
    emit('update:modelValue', (v as string[]) ?? []);
}

function toggleOption(value: string, checked: boolean) {
    const next = checked ? [...list.value, value] : list.value.filter(v => v !== value);
    emit('update:modelValue', next);
}
</script>

<template>
    <!-- is_filled / is_blank / is_true / is_false take no operand at all. -->
    <template v-if="inputKind !== 'none'">
    <!-- between / date_between -->
    <div v-if="arity === 2" class="flex items-center gap-1.5">
        <Input
            :type="type === 'date' ? 'date' : 'number'"
            :model-value="pair[0]"
            :aria-label="t('filters.a11y.valueFrom')"
            :placeholder="t('filters.enterValue')"
            class="h-8 w-[110px] text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            @update:model-value="(v: any) => setPair(0, v)"
        />
        <span class="text-xs text-muted-foreground">—</span>
        <Input
            :type="type === 'date' ? 'date' : 'number'"
            :model-value="pair[1]"
            :aria-label="t('filters.a11y.valueTo')"
            :placeholder="t('filters.enterValue')"
            class="h-8 w-[110px] text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            @update:model-value="(v: any) => setPair(1, v)"
        />
    </div>

    <!-- in / not_in / related_to: a whitelisted option list, or a free tag list -->
    <div v-else-if="inputKind === 'list'" class="flex flex-wrap items-center gap-1.5">
        <fieldset v-if="options.length" class="flex flex-wrap items-center gap-1.5">
            <legend class="sr-only">{{ t('filters.a11y.valueList') }}</legend>
            <label
                v-for="opt in options"
                :key="opt.value"
                class="inline-flex cursor-pointer items-center gap-1 rounded-md border bg-background px-2 py-1 text-xs focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2"
            >
                <input
                    type="checkbox"
                    class="size-3 accent-primary"
                    :checked="list.includes(opt.value)"
                    @change="(e: any) => toggleOption(opt.value, e.target.checked)"
                >
                <span>{{ opt.label }}</span>
            </label>
        </fieldset>
        <TagsInput
            v-else
            :model-value="list"
            class="min-h-8 w-[190px] py-0.5 text-xs"
            :aria-label="t('filters.a11y.valueList')"
            @update:model-value="setList"
        >
            <TagsInputItem v-for="item in list" :key="item" :value="item">
                <TagsInputItemText />
                <TagsInputItemDelete :aria-label="t('filters.removeValue')" />
            </TagsInputItem>
            <TagsInputInput :placeholder="t('filters.enterValue')" class="text-xs" />
        </TagsInput>
    </div>

    <!-- Single whitelisted option -->
    <Select
        v-else-if="inputKind === 'select'"
        :model-value="single"
        @update:model-value="setSingle"
    >
        <SelectTrigger class="h-8 w-[150px] text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" :aria-labelledby="labelledBy">
            <SelectValue :placeholder="t('filters.enterValue')" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
        </SelectContent>
    </Select>

    <Input
        v-else
        :type="inputKind === 'number' ? 'number' : inputKind === 'date' ? 'date' : 'text'"
        :model-value="single"
        :placeholder="t('filters.enterValue')"
        :aria-label="t('filters.enterValue')"
        class="h-8 w-[150px] text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        @update:model-value="setSingle"
    />
    </template>
</template>
