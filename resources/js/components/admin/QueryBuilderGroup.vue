<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { QueryGroup, QueryRule } from '@/types/admin';
import type { QueryConstraintSchema } from '@/types/query-builder';
import { Button } from '@/components/ui/button';
import { Plus, Trash2, FolderPlus } from 'lucide-vue-next';
import QueryBuilderRule from '@/components/admin/QueryBuilderRule.vue';
import { emptyValueForOperator } from '@/composables/useTableFilters';

/** @deprecated legacy shape, still accepted via the `fields` prop. */
interface QueryBuilderFieldDef {
    name: string;
    label: string;
    operators: string[];
}

const props = withDefaults(defineProps<{
    group: QueryGroup;
    constraints?: QueryConstraintSchema[];
    fields?: QueryBuilderFieldDef[];
    depth: number;
    maxDepth?: number;
    maxRules?: number;
    /** Total rules across the whole tree, so the cap is enforced globally. */
    ruleCount?: number;
}>(), {
    constraints: () => [],
    fields: () => [],
    maxDepth: 3,
    maxRules: 25,
    ruleCount: 0,
});

const emit = defineEmits<{
    'update:group': [group: QueryGroup];
}>();

const { t } = useI18n();

const resolvedConstraints = computed<QueryConstraintSchema[]>(() =>
    props.constraints.length
        ? props.constraints
        : props.fields.map(f => ({
            name: f.name,
            type: 'text' as const,
            label: f.label,
            operators: (f.operators ?? []) as any,
        })));

const atRuleCap = computed(() => props.ruleCount >= props.maxRules);
const atDepthCap = computed(() => props.depth + 1 >= props.maxDepth);

function toggleConjunction() {
    emit('update:group', {
        ...props.group,
        conjunction: props.group.conjunction === 'and' ? 'or' : 'and',
    });
}

function addRule() {
    if (atRuleCap.value) return;
    const first = resolvedConstraints.value[0];
    const operator = first?.operators?.[0] ?? 'eq';
    const newRule: QueryRule = {
        field: first?.name ?? '',
        operator,
        value: emptyValueForOperator(operator),
    };
    emit('update:group', { ...props.group, rules: [...props.group.rules, newRule] });
}

function updateRule(index: number, rule: QueryRule) {
    const rules = [...props.group.rules];
    rules[index] = rule;
    emit('update:group', { ...props.group, rules });
}

function removeRule(index: number) {
    const rules = [...props.group.rules];
    rules.splice(index, 1);
    emit('update:group', { ...props.group, rules });
}

function addSubGroup() {
    if (atDepthCap.value || atRuleCap.value) return;
    const newGroup: QueryGroup = { conjunction: 'and', rules: [], groups: [] };
    emit('update:group', { ...props.group, groups: [...props.group.groups, newGroup] });
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
</script>

<template>
    <div
        :class="[
            'qb-group relative rounded-lg border',
            depth > 0 ? 'bg-muted/30 border-dashed' : 'bg-background',
        ]"
    >
        <div v-if="group.rules.length > 1 || group.groups.length > 0" class="absolute left-3.5 top-10 bottom-12 w-px bg-border" aria-hidden="true" />

        <div class="p-3 space-y-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :aria-pressed="group.conjunction === 'and'"
                        :aria-label="t('filters.a11y.conjunction')"
                        :class="[
                            'qb-conjunction inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                            group.conjunction === 'and'
                                ? 'border-transparent bg-primary text-primary-foreground'
                                : 'border-transparent bg-secondary text-secondary-foreground',
                        ]"
                        @click="toggleConjunction"
                    >
                        {{ group.conjunction === 'and' ? t('filters.and') : t('filters.or') }}
                    </button>
                    <span class="text-[11px] text-muted-foreground">
                        {{ group.conjunction === 'and' ? t('filters.matchAll') : t('filters.matchAny') }}
                    </span>
                </div>
                <slot name="remove" />
            </div>

            <QueryBuilderRule
                v-for="(rule, i) in group.rules"
                :key="`rule-${i}`"
                :rule="rule"
                :constraints="resolvedConstraints"
                :index="i"
                @update:rule="(r: QueryRule) => updateRule(i, r)"
                @remove="removeRule(i)"
            />

            <div v-for="(subGroup, i) in group.groups" :key="`group-${i}`" class="pl-4">
                <QueryBuilderGroup
                    :group="subGroup"
                    :constraints="resolvedConstraints"
                    :depth="depth + 1"
                    :max-depth="maxDepth"
                    :max-rules="maxRules"
                    :rule-count="ruleCount"
                    @update:group="(g: QueryGroup) => updateSubGroup(i, g)"
                >
                    <template #remove>
                        <Button
                            variant="ghost"
                            size="sm"
                            type="button"
                            class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            :aria-label="t('filters.removeGroup')"
                            @click="removeSubGroup(i)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />
                        </Button>
                    </template>
                </QueryBuilderGroup>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 pl-4 pt-0.5">
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    class="h-7 gap-1 text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    data-testid="qb-add-rule"
                    :disabled="atRuleCap"
                    :title="atRuleCap ? t('filters.ruleLimit', { max: maxRules }) : undefined"
                    @click="addRule"
                >
                    <Plus class="size-3" aria-hidden="true" />
                    {{ t('filters.addRule') }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    class="h-7 gap-1 text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    data-testid="qb-add-group"
                    :disabled="atDepthCap || atRuleCap"
                    :title="atDepthCap ? t('filters.depthLimit', { max: maxDepth }) : undefined"
                    @click="addSubGroup"
                >
                    <FolderPlus class="size-3" aria-hidden="true" />
                    {{ t('filters.addGroup') }}
                </Button>
                <span v-if="atRuleCap" class="text-[11px] text-muted-foreground" aria-live="polite">
                    {{ t('filters.ruleLimit', { max: maxRules }) }}
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .qb-group,
    .qb-conjunction {
        transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
    }
}
</style>
