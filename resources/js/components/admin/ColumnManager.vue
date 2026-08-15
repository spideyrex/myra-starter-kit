<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Columns3, GripVertical, RotateCcw, Search } from 'lucide-vue-next';
import type { ColumnManagerEntry } from '@/types/table-views';

const props = withDefaults(defineProps<{
    entries: ColumnManagerEntry[];
    isDefault?: boolean;
    reorderable?: boolean;
    idPrefix?: string;
    defaultOpen?: boolean;
}>(), {
    isDefault: true,
    reorderable: true,
    idPrefix: 'cm',
    defaultOpen: false,
});

const emit = defineEmits<{
    toggle: [key: string, visible: boolean];
    move: [key: string, delta: -1 | 1];
    reorder: [from: number, to: number];
    reset: [];
}>();

const { t } = useI18n();

const term = ref('');
const liveMessage = ref('');
const dragIndex = ref<number | null>(null);

const filtered = computed(() => {
    const needle = term.value.trim().toLowerCase();
    if (!needle) return props.entries;
    return props.entries.filter(e => e.label.toLowerCase().includes(needle));
});

const grouped = computed(() => {
    const groups = new Map<string, ColumnManagerEntry[]>();
    for (const entry of filtered.value) {
        const key = entry.group ?? '';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key)!.push(entry);
    }
    return [...groups.entries()];
});

function indexOf(entry: ColumnManagerEntry): number {
    return props.entries.findIndex(e => e.key === entry.key);
}

function announce(entry: ColumnManagerEntry): void {
    liveMessage.value = t('columns.a11y.moved', {
        label: entry.label,
        position: indexOf(entry) + 1,
        total: props.entries.length,
    });
}

function shift(entry: ColumnManagerEntry, delta: -1 | 1): void {
    emit('move', entry.key, delta);
    announce(entry);
}

function onKeydown(event: KeyboardEvent, entry: ColumnManagerEntry): void {
    if (!props.reorderable || !event.altKey) return;
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        shift(entry, -1);
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        shift(entry, 1);
    }
}

function onDragStart(entry: ColumnManagerEntry): void {
    dragIndex.value = indexOf(entry);
}

function onDrop(entry: ColumnManagerEntry): void {
    const from = dragIndex.value;
    dragIndex.value = null;
    if (from === null) return;
    const to = indexOf(entry);
    if (from === to) return;
    emit('reorder', from, to);
    announce(entry);
}
</script>

<template>
    <Popover :default-open="defaultOpen">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                :aria-label="t('columns.title')"
            >
                <Columns3 class="mr-2 size-4" />
                <span class="hidden sm:inline">{{ t('columns.title') }}</span>
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[18rem] max-w-[calc(100vw-2rem)] p-3 sm:w-[20rem]" align="end">
            <fieldset class="space-y-2 border-0 p-0">
                <legend class="sr-only">{{ t('columns.a11y.list') }}</legend>

                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="term"
                        type="search"
                        class="h-8 pl-8 text-sm"
                        :placeholder="t('columns.search')"
                        :aria-label="t('columns.search')"
                    />
                </div>

                <ul class="max-h-64 space-y-0.5 overflow-y-auto pr-1">
                    <template v-for="[groupLabel, groupEntries] in grouped" :key="groupLabel || '_'">
                        <li v-if="groupLabel" class="px-1 pt-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            {{ groupLabel }}
                        </li>
                        <li
                            v-for="entry in groupEntries"
                            :key="entry.key"
                            class="cm-row flex items-center gap-2 rounded-md px-1 py-1 hover:bg-muted/50 focus-within:bg-muted/60"
                            :class="{ 'opacity-60': dragIndex === indexOf(entry) }"
                            :draggable="reorderable"
                            @dragstart="onDragStart(entry)"
                            @dragover.prevent
                            @drop.prevent="onDrop(entry)"
                            @dragend="dragIndex = null"
                            @keydown="onKeydown($event, entry)"
                        >
                            <button
                                v-if="reorderable"
                                type="button"
                                class="cursor-grab rounded-sm p-0.5 text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                :aria-label="`${entry.label}: ${t('columns.moveUp')} / ${t('columns.moveDown')}`"
                                @click.prevent
                            >
                                <GripVertical class="size-3.5" />
                            </button>
                            <Checkbox
                                :id="`${idPrefix}-${entry.key}`"
                                class="focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                :model-value="entry.visible"
                                @update:model-value="(v: boolean | 'indeterminate') => emit('toggle', entry.key, v === true)"
                            />
                            <label
                                :for="`${idPrefix}-${entry.key}`"
                                class="flex-1 cursor-pointer select-none truncate text-sm"
                            >{{ entry.label }}</label>
                            <span class="sr-only">{{ entry.visible ? t('columns.visible') : t('columns.hidden') }}</span>
                        </li>
                    </template>
                </ul>

                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 w-full justify-start text-xs text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :disabled="isDefault"
                    @click="emit('reset')"
                >
                    <RotateCcw class="mr-1.5 size-3" />
                    {{ t('columns.reset') }}
                </Button>
            </fieldset>

            <p class="sr-only" aria-live="polite" aria-atomic="true">{{ liveMessage }}</p>
        </PopoverContent>
    </Popover>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .cm-row {
        transition: background-color 150ms ease, opacity 150ms ease;
    }
}
</style>
