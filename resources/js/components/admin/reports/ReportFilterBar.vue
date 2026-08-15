<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Badge } from '@/components/ui/badge';
import { Filter, X } from 'lucide-vue-next';
import QueryBuilderGroup from '@/components/admin/QueryBuilderGroup.vue';
import { countQueryRules } from '@/composables/useTableFilters';
import type { QueryGroup } from '@/types/admin';
import type { ReportSchema } from '@/types/reports';

const props = defineProps<{
    schema: ReportSchema;
    modelValue: QueryGroup | null;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: QueryGroup | null] }>();

const { t } = useI18n();

const empty = (): QueryGroup => ({ conjunction: 'and', rules: [], groups: [] });

const open = ref(false);
const draft = ref<QueryGroup>(props.modelValue ? JSON.parse(JSON.stringify(props.modelValue)) : empty());
const dirty = ref(false);

watch(() => props.modelValue, (value) => {
    draft.value = value ? JSON.parse(JSON.stringify(value)) : empty();
    dirty.value = false;
});

const ruleCount = computed(() => countQueryRules(props.modelValue));

function apply(): void {
    const group = draft.value;
    const hasRules = group.rules.length > 0 || group.groups.length > 0;
    emit('update:modelValue', hasRules ? JSON.parse(JSON.stringify(group)) : null);
    dirty.value = false;
    open.value = false;
}

function clear(): void {
    draft.value = empty();
    dirty.value = false;
    emit('update:modelValue', null);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button variant="outline" class="gap-2">
                    <Filter class="size-4" aria-hidden="true" />
                    {{ t('reports.toolbar.filters') }}
                    <Badge v-if="ruleCount > 0" variant="secondary">{{ ruleCount }}</Badge>
                </Button>
            </PopoverTrigger>

            <PopoverContent class="w-[min(46rem,90vw)] p-3" align="start">
                <QueryBuilderGroup
                    :group="draft"
                    :constraints="schema.fields.fields"
                    :depth="0"
                    :max-depth="schema.fields.maxDepth"
                    :max-rules="schema.fields.maxRules"
                    :rule-count="countQueryRules(draft)"
                    @update:group="(g: QueryGroup) => { draft = g; dirty = true; }"
                />

                <div class="mt-3 flex items-center gap-2">
                    <Button size="sm" :disabled="!dirty" @click="apply">{{ t('reports.period.apply') }}</Button>
                    <Button v-if="ruleCount > 0" size="sm" variant="ghost" @click="clear">
                        <X class="size-4" aria-hidden="true" />
                        {{ t('common.cancel') }}
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>
