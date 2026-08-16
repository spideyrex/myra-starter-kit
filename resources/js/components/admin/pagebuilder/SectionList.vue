<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { VueDraggable } from 'vue-draggable-plus';
import SectionCard from './SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { LayoutTemplate, Plus, RotateCcw, Wand2 } from 'lucide-vue-next';
import { useConfirm } from '@/composables/useConfirm';
import { REORDER_INSTRUCTIONS_ID, useReorderable } from '@/composables/useReorderable';
import type { SectionRow, UseSectionList } from '@/composables/useSectionList';

const props = withDefaults(defineProps<{
    list: UseSectionList;
    /** Raw Laravel bag: `blocks.3.data.title` -> message. */
    errors?: Record<string, string>;
    disabled?: boolean;
    canConvert?: boolean;
}>(), {
    errors: () => ({}),
    canConvert: true,
});

const emit = defineEmits<{ openCatalogue: [atIndex: number | undefined]; convert: []; revert: [] }>();

const { t } = useI18n();
const { confirm } = useConfirm();

/** `blocks.{i}.data.{path}` -> { [i]: { [path]: message } }. */
const errorsByIndex = computed(() => {
    const map: Record<number, Record<string, string>> = {};

    for (const [key, message] of Object.entries(props.errors ?? {})) {
        const match = /^blocks\.(\d+)(?:\.(.*))?$/.exec(key);
        if (!match) continue;

        const index = Number(match[1]);
        const rest = match[2] ?? '';
        const path = rest.startsWith('data.') ? rest.slice(5) : '__row';

        map[index] = { ...(map[index] ?? {}), [path]: String(message) };
    }

    return map;
});

const rows = computed<SectionRow[]>(() => props.list.rows.value);
// A mirror VueDraggable may mutate freely: writes are dropped and `@update`
// drives the real move, so pointer drag and the keyboard share one code path.
const dragList = computed<SectionRow[]>({
    get: () => [...rows.value],
    set: () => undefined,
});

const reorder = useReorderable<SectionRow>({
    items: rows,
    keyOf: row => row.id,
    onMove: (id, toIndex) => props.list.move(id, toIndex),
    announce: (row, index, total) => t('pageBuilder.editor.a11y.moved', {
        label: props.list.titleOf(row),
        index: index + 1,
        total,
    }),
    enabled: () => props.disabled !== true,
    roleDescription: () => t('pageBuilder.editor.a11y.roledescription'),
});

function onDragUpdate(event: { oldIndex?: number; newIndex?: number }): void {
    const from = event.oldIndex ?? -1;
    const to = event.newIndex ?? -1;
    const row = rows.value[from];

    if (!row || to < 0) return;

    props.list.move(row.id, to);
}

async function removeRow(row: SectionRow): Promise<void> {
    const ok = await confirm({
        title: t('pageBuilder.editor.confirm.delete.title'),
        description: t('pageBuilder.editor.confirm.delete.description', { label: props.list.titleOf(row) }),
        confirmText: t('pageBuilder.editor.confirm.delete.confirm'),
        cancelText: t('common.cancel'),
        variant: 'destructive',
    });

    if (ok) props.list.remove(row.id);
}

async function revert(): Promise<void> {
    const ok = await confirm({
        title: t('pageBuilder.editor.confirm.revert.title'),
        description: t('pageBuilder.editor.confirm.revert.description'),
        confirmText: t('pageBuilder.editor.confirm.revert.confirm'),
        cancelText: t('common.cancel'),
        variant: 'destructive',
    });

    if (ok) emit('revert');
}

// A card that failed validation expands and scrolls itself into view, so a
// 20-section page never hides the reason a save bounced.
watch(errorsByIndex, async (map) => {
    const first = Object.keys(map).map(Number).sort((a, b) => a - b)[0];
    if (first === undefined) return;

    const row = rows.value[first];
    if (!row) return;

    props.list.expand(row.id);
    props.list.selectedId.value = row.id;

    await nextTick();
    document.getElementById(`pb-section-${row.id}`)?.scrollIntoView({ block: 'center' });
});
</script>

<template>
    <div class="space-y-3">
        <Empty v-if="rows.length === 0" class="border border-dashed">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <LayoutTemplate aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle>{{ t('pageBuilder.editor.empty.title') }}</EmptyTitle>
                <EmptyDescription>{{ t('pageBuilder.editor.empty.description') }}</EmptyDescription>
            </EmptyHeader>
            <EmptyContent class="flex flex-wrap justify-center gap-2">
                <Button type="button" :disabled="disabled" @click="emit('openCatalogue', undefined)">
                    <Plus class="mr-2 size-4" aria-hidden="true" />
                    {{ t('pageBuilder.editor.addSection') }}
                </Button>
                <Button
                    v-if="canConvert"
                    type="button"
                    variant="outline"
                    :disabled="disabled"
                    @click="emit('convert')"
                >
                    <Wand2 class="mr-2 size-4" aria-hidden="true" />
                    {{ t('pageBuilder.editor.empty.convert') }}
                </Button>
            </EmptyContent>
        </Empty>

        <template v-else>
            <VueDraggable
                v-model="dragList"
                :animation="150"
                handle=".myra-pb-handle"
                :disabled="disabled"
                class="space-y-3"
                role="list"
                :aria-label="t('pageBuilder.editor.a11y.list')"
                @update="onDragUpdate"
            >
                <SectionCard
                    v-for="(row, index) in dragList"
                    :key="row.id"
                    :row="row"
                    :schema="list.schemaOf(row.type)"
                    :index="index"
                    :total="dragList.length"
                    :title="list.titleOf(row)"
                    :errors="errorsByIndex[index] ?? {}"
                    :collapsed="list.isCollapsed(row.id)"
                    :selected="list.selectedId.value === row.id"
                    :handle-props="reorder.handleProps(row, index)"
                    :can-duplicate="list.canAdd(row.type)"
                    :disabled="disabled"
                    @select="list.selectedId.value = row.id"
                    @toggle-collapsed="list.toggleCollapsed(row.id)"
                    @toggle-enabled="list.toggle(row.id)"
                    @duplicate="list.duplicate(row.id)"
                    @remove="removeRow(row)"
                    @update="(field: string, value: unknown) => list.update(row.id, field, value)"
                    @update-variant="(key: string, value: unknown) => list.updateVariant(row.id, key, value)"
                />
            </VueDraggable>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="flex-1 border-dashed"
                    :disabled="disabled || list.atLimit.value"
                    @click="emit('openCatalogue', undefined)"
                >
                    <Plus class="mr-2 size-4" aria-hidden="true" />
                    {{ t('pageBuilder.editor.addSection') }}
                </Button>
                <Button type="button" variant="ghost" :disabled="disabled" @click="revert">
                    <RotateCcw class="mr-2 size-4" aria-hidden="true" />
                    {{ t('pageBuilder.editor.empty.revert') }}
                </Button>
            </div>
        </template>

        <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">
            {{ reorder.announcement.value }}
        </p>
        <p :id="REORDER_INSTRUCTIONS_ID" class="sr-only">
            {{ t('pageBuilder.editor.a11y.instructions') }}
        </p>
    </div>
</template>
