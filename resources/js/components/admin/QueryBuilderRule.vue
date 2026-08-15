<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Trash2 } from 'lucide-vue-next';
import QueryValueInput from '@/components/admin/QueryValueInput.vue';
import { retargetRuleField, retargetRuleOperator } from '@/composables/useTableFilters';
import type { QueryRule } from '@/types/admin';
import type { QueryConstraintSchema, QueryRuleValue } from '@/types/query-builder';

const props = defineProps<{
    rule: QueryRule;
    constraints: QueryConstraintSchema[];
    index: number;
}>();

const emit = defineEmits<{
    'update:rule': [rule: QueryRule];
    remove: [];
}>();

const { t } = useI18n();

const fieldId = computed(() => `qb-rule-${props.index}-field`);

const constraint = computed(() => props.constraints.find(c => c.name === props.rule.field));

const operators = computed(() => constraint.value?.operators ?? []);

function constraintLabel(c: QueryConstraintSchema): string {
    return c.labelKey ? t(c.labelKey) : c.label;
}

/** Every operator label comes from i18n — the enum value is never shown raw. */
function operatorLabel(op: string): string {
    return t(`filters.op.${op}`);
}

function changeField(name: string) {
    emit('update:rule', retargetRuleField(props.rule, name, props.constraints));
}

function changeOperator(operator: string) {
    emit('update:rule', retargetRuleOperator(props.rule, operator));
}

function changeValue(value: QueryRuleValue) {
    emit('update:rule', { ...props.rule, value });
}
</script>

<template>
    <div
        role="group"
        :aria-label="t('filters.a11y.condition', { n: index + 1 })"
        class="relative flex flex-wrap items-center gap-1.5 pl-4"
    >
        <div class="absolute left-[11px] top-1/2 -translate-y-1/2 size-1.5 rounded-full bg-border" aria-hidden="true" />

        <Select :model-value="rule.field" @update:model-value="(v: any) => changeField(String(v))">
            <SelectTrigger :id="fieldId" class="h-8 w-[140px] text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" :aria-label="t('filters.selectField')">
                <SelectValue :placeholder="t('filters.selectField')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="c in constraints" :key="c.name" :value="c.name">
                    {{ constraintLabel(c) }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select :model-value="rule.operator" @update:model-value="(v: any) => changeOperator(String(v))">
            <SelectTrigger class="h-8 w-[150px] text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" :aria-label="t('filters.selectOperator')">
                <SelectValue :placeholder="t('filters.selectOperator')" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="op in operators" :key="op" :value="op">
                    {{ operatorLabel(op) }}
                </SelectItem>
            </SelectContent>
        </Select>

        <QueryValueInput
            :constraint="constraint"
            :operator="rule.operator"
            :model-value="rule.value"
            :labelled-by="fieldId"
            @update:model-value="changeValue"
        />

        <Button
            variant="ghost"
            size="sm"
            type="button"
            class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :aria-label="t('filters.removeRule')"
            @click="emit('remove')"
        >
            <Trash2 class="size-3.5" aria-hidden="true" />
        </Button>
    </div>
</template>
