<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SectionFieldControl from './SectionFieldControl.vue';
import { sectionIcon } from './sectionIcons';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ChevronDown, Copy, GripVertical, Trash2, TriangleAlert } from 'lucide-vue-next';
import { VARIANT_DEFAULT, type SectionRow, type SectionSchema } from '@/composables/useSectionList';

const props = withDefaults(defineProps<{
    row: SectionRow;
    schema?: SectionSchema;
    index: number;
    total: number;
    title: string;
    /** Keyed by the path BELOW `data.`, e.g. `title` or `items.0.name`. */
    errors?: Record<string, string>;
    collapsed?: boolean;
    selected?: boolean;
    handleProps?: Record<string, unknown>;
    canDuplicate?: boolean;
    disabled?: boolean;
}>(), {
    errors: () => ({}),
    handleProps: () => ({}),
    canDuplicate: true,
});

const emit = defineEmits<{
    select: [];
    toggleCollapsed: [];
    toggleEnabled: [];
    duplicate: [];
    remove: [];
    update: [field: string, value: unknown];
    updateVariant: [key: string, value: unknown];
}>();

const { t } = useI18n();

const icon = computed(() => sectionIcon(props.schema?.icon));
const known = computed(() => props.schema !== undefined);
const hasErrors = computed(() => Object.keys(props.errors).length > 0);
const domId = computed(() => `pb-section-${props.row.id}`);

const variantEntries = computed(() => Object.entries(props.schema?.variants ?? {}));

function variantValue(key: string, spec: string[] | 'bool'): string {
    const current = props.row.variant?.[key];

    if (current === undefined || current === null) return VARIANT_DEFAULT;
    if (spec === 'bool') return current === true ? 'true' : 'false';

    return typeof current === 'string' && spec.includes(current) ? current : VARIANT_DEFAULT;
}

function variantOptions(spec: string[] | 'bool'): { value: string; label: string }[] {
    const head = { value: VARIANT_DEFAULT, label: t('pageBuilder.editor.card.variantDefault') };

    if (spec === 'bool') {
        return [
            head,
            { value: 'true', label: t('pageBuilder.editor.card.variantOn') },
            { value: 'false', label: t('pageBuilder.editor.card.variantOff') },
        ];
    }

    return [head, ...spec.map(option => ({ value: option, label: option }))];
}

function onVariantChange(key: string, spec: string[] | 'bool', raw: unknown): void {
    const value = String(raw ?? VARIANT_DEFAULT);

    if (value === VARIANT_DEFAULT) {
        emit('updateVariant', key, VARIANT_DEFAULT);

        return;
    }

    emit('updateVariant', key, spec === 'bool' ? value === 'true' : value);
}

const quarantinePeek = computed(() => {
    try {
        return JSON.stringify(props.row.data ?? {}, null, 2);
    } catch {
        return '{}';
    }
});
</script>

<template>
    <Card
        :id="domId"
        class="overflow-hidden transition-opacity"
        :class="[
            row.enabled ? '' : 'opacity-60',
            selected ? 'ring-2 ring-ring' : '',
            hasErrors ? 'border-destructive' : '',
        ]"
        @click="emit('select')"
    >
        <div class="flex flex-wrap items-center gap-1.5 border-b bg-muted/30 px-2 py-2 sm:gap-2 sm:px-3">
            <button
                type="button"
                class="myra-pb-handle inline-flex size-8 shrink-0 cursor-grab items-center justify-center rounded-md text-muted-foreground hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:cursor-grabbing"
                v-bind="handleProps"
                :aria-label="t('pageBuilder.editor.a11y.grabHandle', { label: title })"
            >
                <GripVertical class="size-4" aria-hidden="true" />
            </button>

            <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1 py-1 text-left text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :aria-expanded="!collapsed"
                :aria-controls="`${domId}-body`"
                @click="emit('toggleCollapsed')"
            >
                <ChevronDown
                    class="size-4 shrink-0 transition-transform"
                    :class="collapsed ? '-rotate-90' : ''"
                    aria-hidden="true"
                />
                <component :is="icon" class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                <span class="truncate">{{ title }}</span>
                <span class="sr-only">
                    {{ t('pageBuilder.editor.a11y.position', { index: index + 1, total }) }}
                </span>
            </button>

            <Badge v-if="!row.enabled" variant="secondary" class="shrink-0 text-xs">
                {{ t('pageBuilder.editor.card.hidden') }}
            </Badge>
            <Badge v-if="hasErrors" variant="destructive" class="shrink-0 text-xs">
                {{ t('pageBuilder.editor.card.hasErrors') }}
            </Badge>

            <div class="ml-auto flex shrink-0 items-center gap-1" @click.stop>
                <Switch
                    :model-value="row.enabled"
                    :disabled="disabled"
                    :aria-label="row.enabled
                        ? t('pageBuilder.editor.card.hide', { label: title })
                        : t('pageBuilder.editor.card.show', { label: title })"
                    @update:model-value="emit('toggleEnabled')"
                />
                <Button
                    v-if="known"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :disabled="disabled || !canDuplicate"
                    :aria-label="t('pageBuilder.editor.card.duplicate', { label: title })"
                    @click="emit('duplicate')"
                >
                    <Copy class="size-4" aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 text-destructive hover:text-destructive"
                    :disabled="disabled"
                    :aria-label="t('pageBuilder.editor.card.delete', { label: title })"
                    @click="emit('remove')"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </Button>
            </div>
        </div>

        <div v-show="!collapsed" :id="`${domId}-body`" class="space-y-4 p-3" @click.stop>
            <template v-if="known">
                <div class="grid gap-4 sm:grid-cols-2">
                    <SectionFieldControl
                        v-for="field in schema!.fields"
                        :key="field.name"
                        :field="field"
                        :model-value="row.data[field.name]"
                        :errors="errors"
                        :path="field.name"
                        :id-prefix="row.id"
                        :disabled="disabled"
                        @update:model-value="(value: unknown) => emit('update', field.name, value)"
                    />
                </div>

                <div v-if="variantEntries.length" class="space-y-3 rounded-md border border-dashed p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {{ t('pageBuilder.editor.card.presentation') }}
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="[key, spec] in variantEntries" :key="key" class="space-y-1.5">
                            <label :for="`${domId}-variant-${key}`" class="text-sm font-medium">{{ key }}</label>
                            <Select
                                :model-value="variantValue(key, spec)"
                                :disabled="disabled"
                                @update:model-value="(value: unknown) => onVariantChange(key, spec, value)"
                            >
                                <SelectTrigger :id="`${domId}-variant-${key}`">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in variantOptions(spec)"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Unknown type: quarantined, never silently dropped. -->
            <div v-else class="space-y-3">
                <div class="flex items-start gap-2 rounded-md border border-dashed p-3">
                    <TriangleAlert class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm font-medium">{{ t('pageBuilder.editor.card.unknown.title') }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ t('pageBuilder.editor.card.unknown.description', { type: row.type }) }}
                        </p>
                    </div>
                </div>
                <pre class="max-h-48 overflow-auto rounded-md bg-muted p-3 text-xs">{{ quarantinePeek }}</pre>
            </div>

            <p v-if="errors.__row" class="text-sm text-destructive">{{ errors.__row }}</p>
        </div>
    </Card>
</template>
