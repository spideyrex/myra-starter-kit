<script setup lang="ts">
import type { QueryGroup, QueryRule } from '@/types/admin';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Trash2, FolderPlus } from 'lucide-vue-next';

interface QueryBuilderFieldDef {
    name: string;
    label: string;
    operators: string[];
}

const props = defineProps<{
    group: QueryGroup;
    fields: QueryBuilderFieldDef[];
    depth: number;
}>();

const emit = defineEmits<{
    'update:group': [group: QueryGroup];
}>();

function toggleConjunction() {
    emit('update:group', {
        ...props.group,
        conjunction: props.group.conjunction === 'and' ? 'or' : 'and',
    });
}

function addRule() {
    const firstField = props.fields[0];
    const newRule: QueryRule = {
        field: firstField?.name || '',
        operator: firstField?.operators[0] || '=',
        value: '',
    };
    emit('update:group', {
        ...props.group,
        rules: [...props.group.rules, newRule],
    });
}

function updateRule(index: number, patch: Partial<QueryRule>) {
    const rules = [...props.group.rules];
    rules[index] = { ...rules[index], ...patch };
    if (patch.field) {
        const fieldDef = props.fields.find(f => f.name === patch.field);
        if (fieldDef && !fieldDef.operators.includes(rules[index].operator)) {
            rules[index].operator = fieldDef.operators[0] || '=';
        }
    }
    emit('update:group', { ...props.group, rules });
}

function removeRule(index: number) {
    const rules = [...props.group.rules];
    rules.splice(index, 1);
    emit('update:group', { ...props.group, rules });
}

function addSubGroup() {
    if (props.depth >= 1) return;
    const newGroup: QueryGroup = { conjunction: 'and', rules: [], groups: [] };
    emit('update:group', {
        ...props.group,
        groups: [...props.group.groups, newGroup],
    });
}

function updateSubGroup(index: number, subGroup: QueryGroup) {
    const groups = [...props.group.groups];
    groups[index] = subGroup;
    emit('update:group', { ...props.group, groups });
}

function removeSubGroup(index: number) {
    const groups = [...props.group.groups];
    groups.splice(index, 1);
    emit('update:group', { ...props.group, groups });
}

function getOperatorsForField(fieldName: string): string[] {
    const f = props.fields.find(f => f.name === fieldName);
    return f?.operators || ['='];
}

const operatorLabels: Record<string, string> = {
    '=': 'equals',
    '!=': 'not equal',
    '>': 'greater than',
    '<': 'less than',
    '>=': 'at least',
    '<=': 'at most',
    'contains': 'contains',
    'starts_with': 'starts with',
    'ends_with': 'ends with',
};
</script>

<template>
    <div
        :class="[
            'relative rounded-lg border transition-colors',
            depth > 0 ? 'bg-muted/30 border-dashed' : 'bg-background',
        ]"
    >
        <!-- Conjunction indicator line -->
        <div v-if="group.rules.length > 1 || group.groups.length > 0" class="absolute left-3.5 top-10 bottom-12 w-px bg-border" />

        <div class="p-3 space-y-2">
            <!-- Header: Conjunction toggle -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Badge
                        class="cursor-pointer select-none uppercase tracking-wide text-[10px] font-semibold px-2 py-0.5 transition-colors"
                        :variant="group.conjunction === 'and' ? 'default' : 'secondary'"
                        @click="toggleConjunction"
                    >
                        {{ group.conjunction }}
                    </Badge>
                    <span class="text-[11px] text-muted-foreground">
                        {{ group.conjunction === 'and' ? 'Match all conditions' : 'Match any condition' }}
                    </span>
                </div>
                <slot name="remove" />
            </div>

            <!-- Rules -->
            <div v-for="(rule, i) in group.rules" :key="`rule-${i}`" class="relative flex flex-wrap items-center gap-1.5 pl-4">
                <!-- Dot connector -->
                <div class="absolute left-[11px] top-1/2 -translate-y-1/2 size-1.5 rounded-full bg-border" />

                <Select :model-value="rule.field" @update:model-value="(v: any) => updateRule(i, { field: String(v) })">
                    <SelectTrigger class="h-8 w-[130px] text-xs">
                        <SelectValue placeholder="Select field" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="f in fields" :key="f.name" :value="f.name">{{ f.label }}</SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="rule.operator" @update:model-value="(v: any) => updateRule(i, { operator: String(v) })">
                    <SelectTrigger class="h-8 w-[120px] text-xs">
                        <SelectValue placeholder="Operator" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="op in getOperatorsForField(rule.field)" :key="op" :value="op">
                            <span class="font-mono text-xs mr-1.5">{{ op }}</span>
                            <span v-if="operatorLabels[op]" class="text-muted-foreground text-[11px]">{{ operatorLabels[op] }}</span>
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Input
                    :model-value="rule.value"
                    placeholder="Enter value…"
                    class="h-8 w-[150px] text-xs"
                    @update:model-value="(v: any) => updateRule(i, { value: String(v) })"
                />

                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive transition-colors"
                    @click="removeRule(i)"
                >
                    <Trash2 class="size-3.5" />
                </Button>
            </div>

            <!-- Sub-groups -->
            <div v-for="(subGroup, i) in group.groups" :key="`group-${i}`" class="pl-4">
                <QueryBuilderGroup
                    :group="subGroup"
                    :fields="fields"
                    :depth="depth + 1"
                    @update:group="(g: QueryGroup) => updateSubGroup(i, g)"
                >
                    <template #remove>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive transition-colors"
                            @click="removeSubGroup(i)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </template>
                </QueryBuilderGroup>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-1.5 pl-4 pt-0.5">
                <Button variant="outline" size="sm" class="h-7 text-xs gap-1" @click="addRule">
                    <Plus class="size-3" />
                    Rule
                </Button>
                <Button v-if="depth < 1" variant="outline" size="sm" class="h-7 text-xs gap-1" @click="addSubGroup">
                    <FolderPlus class="size-3" />
                    Group
                </Button>
            </div>
        </div>
    </div>
</template>
