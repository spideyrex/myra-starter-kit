<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { QueryGroup, QueryRule } from '@/types/admin';
import type { AskBarSchema } from '@/composables/useAskBar';

const props = defineProps<{
    tree: QueryGroup | null;
    schema?: AskBarSchema | null;
    readonly?: boolean;
}>();

const emit = defineEmits<{ drop: [path: number[]] }>();

const { t, te } = useI18n();

interface Chip {
    path: number[];
    rule: QueryRule;
    conjunction: 'and' | 'or';
    depth: number;
}

function walk(group: QueryGroup | null, path: number[], depth: number, out: Chip[]): void {
    if (!group) return;

    (group.rules ?? []).forEach((rule, index) => {
        out.push({ path: [...path, index], rule, conjunction: group.conjunction, depth });
    });

    (group.groups ?? []).forEach((child, index) => {
        walk(child, [...path, index], depth + 1, out);
    });
}

const chips = computed<Chip[]>(() => {
    const out: Chip[] = [];
    walk(props.tree, [], 0, out);

    return out;
});

function fieldLabel(name: string): string {
    const field = props.schema?.fields?.find((f) => f.name === name) as { labelKey?: string } | undefined;
    const key = field?.labelKey;

    return key && te(key) ? t(key) : name;
}

function operatorLabel(op: string): string {
    const key = `filters.op.${op}`;

    return te(key) ? t(key) : op;
}

function valueLabel(value: QueryRule['value']): string {
    if (value === null || value === undefined) return '';
    if (Array.isArray(value)) return value.join(', ');

    return String(value);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <p v-if="chips.length === 0" class="text-sm text-muted-foreground">
            {{ t('assistant.unresolved') }}
        </p>

        <template v-for="(chip, i) in chips" :key="chip.path.join('-')">
            <span v-if="i > 0" class="text-xs uppercase tracking-wide text-muted-foreground">
                {{ t(`filters.${chip.conjunction}`) }}
            </span>

            <Badge variant="secondary" class="gap-1.5 py-1 font-normal">
                <span class="font-medium">{{ fieldLabel(chip.rule.field) }}</span>
                <span class="text-muted-foreground">{{ operatorLabel(chip.rule.operator) }}</span>
                <span v-if="valueLabel(chip.rule.value)">{{ valueLabel(chip.rule.value) }}</span>

                <Button
                    v-if="!readonly"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="-mr-1 size-4 rounded-full"
                    :aria-label="t('filters.removeRule')"
                    @click="emit('drop', chip.path)"
                >
                    <X class="size-3" aria-hidden="true" />
                </Button>
            </Badge>
        </template>
    </div>
</template>
