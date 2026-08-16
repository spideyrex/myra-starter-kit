<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Kbd } from '@/components/ui/kbd';
import { Search, Clock, Loader2, Command as CommandIcon } from 'lucide-vue-next';
import SearchHighlight from '@/components/admin/SearchHighlight.vue';
import { useGlobalSearch, type SearchItem } from '@/composables/useGlobalSearch';
import { useCommandRegistry, type CommandMatch } from '@/composables/useCommandRegistry';

const props = withDefaults(defineProps<{
    /** Set false when a host layout already owns the Cmd/Ctrl+K binding. */
    bindShortcut?: boolean;
}>(), { bindShortcut: true });

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const inputRef = ref<HTMLInputElement | null>(null);
const listboxId = 'myra-command-listbox';

const { query, results, loading, hasSearched, activeIndex, flatItems, recent, open: markOpened, reset } =
    useGlobalSearch();

const { match } = useCommandRegistry();

/** One listbox, two kinds of row — the a11y machinery below is unchanged. */
type CommandRow = { kind: 'command'; id: string; command: CommandMatch };
type RecordRow = { kind: 'record'; id: string; item: SearchItem; groupLabel: string | null };
type PaletteRow = CommandRow | RecordRow;

const showRecent = computed(() => query.value.length < 2 && recent.value.length > 0);

const commandRows = computed<CommandRow[]>(() =>
    match(query.value).slice(0, 8).map(command => ({ kind: 'command', id: `cmd-${command.id}`, command })),
);

const recordRows = computed<RecordRow[]>(() => {
    if (showRecent.value) {
        return recent.value.map((item, i) => ({
            kind: 'record' as const,
            id: `rec-${i}-${item.id}`,
            item,
            groupLabel: null,
        }));
    }

    const out: RecordRow[] = [];

    for (const group of results.value) {
        let first = true;
        for (const item of group.items) {
            out.push({
                kind: 'record' as const,
                id: `${group.key ?? group.group}-${item.id}`,
                item,
                groupLabel: first ? group.group : null,
            });
            first = false;
        }
    }

    return out;
});

const rows = computed<PaletteRow[]>(() => [...commandRows.value, ...recordRows.value]);

function optionId(row: PaletteRow, index: number): string {
    return `${listboxId}-opt-${index}-${row.id}`;
}

const activeDescendant = computed(() => {
    const row = rows.value[activeIndex.value];
    return row ? optionId(row, activeIndex.value) : undefined;
});

// The binding lives here, so no shared layout has to be edited.
useEventListener(document, 'keydown', (e: KeyboardEvent) => {
    if (!props.bindShortcut) return;
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value = !open.value;
    }
});

watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        inputRef.value?.focus();
    } else {
        reset();
    }
});

watch(rows, (list) => {
    if (activeIndex.value > list.length - 1) activeIndex.value = 0;
});

function move(delta: 1 | -1) {
    const total = rows.value.length;
    if (total === 0) return;
    activeIndex.value = (activeIndex.value + delta + total) % total;
}

function choose(row?: PaletteRow) {
    const target = row ?? rows.value[activeIndex.value];
    if (!target) return;

    if (target.kind === 'command') {
        open.value = false;
        void target.command.run({ close: () => { open.value = false; } });
        return;
    }

    markOpened(target.item);
    open.value = false;
    router.visit(target.item.url);
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        move(1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        move(-1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        choose();
    }
}

defineExpose({ open });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-[min(100vw-1.5rem,36rem)] gap-0 p-0">
            <DialogTitle class="sr-only">{{ t('search.placeholder') }}</DialogTitle>
            <DialogDescription class="sr-only">{{ t('search.a11y.hint') }}</DialogDescription>

            <div class="flex items-center gap-2 border-b px-3">
                <Search class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                <input
                    ref="inputRef"
                    v-model="query"
                    role="combobox"
                    type="text"
                    autocomplete="off"
                    :aria-expanded="rows.length > 0"
                    :aria-controls="listboxId"
                    :aria-activedescendant="activeDescendant"
                    :aria-label="t('search.placeholder')"
                    :placeholder="t('search.placeholder')"
                    class="h-11 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-0"
                    @keydown="onKeydown"
                >
                <Loader2 v-if="loading" class="size-4 shrink-0 animate-spin text-muted-foreground" aria-hidden="true" />
            </div>

            <p class="sr-only" role="status" aria-live="polite" aria-atomic="true">
                {{ loading ? t('common.loading') : t('search.a11y.results', { n: rows.length }) }}
            </p>

            <div class="max-h-[60vh] overflow-y-auto p-1">
                <ul :id="listboxId" role="listbox" :aria-label="t('search.a11y.results', { n: rows.length })" class="space-y-0.5">
                    <template v-if="commandRows.length">
                        <li role="presentation" class="px-2 pb-1 pt-2 text-xs font-medium text-muted-foreground">
                            {{ t('gallery.commands.heading') }}
                        </li>
                        <li
                            v-for="(row, i) in commandRows"
                            :id="optionId(row, i)"
                            :key="row.id"
                            role="option"
                            :aria-selected="activeIndex === i"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-sm',
                                activeIndex === i ? 'bg-accent text-accent-foreground' : '',
                            ]"
                            @click="choose(row)"
                            @mousemove="activeIndex = i"
                        >
                            <component
                                :is="row.command.icon ?? CommandIcon"
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="truncate">
                                <SearchHighlight :text="row.command.title" :matches="row.command.matches" field="title" />
                            </span>
                            <Kbd v-if="row.command.shortcut" class="ml-auto">{{ row.command.shortcut }}</Kbd>
                        </li>
                    </template>

                    <li v-if="showRecent" role="presentation" class="px-2 pb-1 pt-2 text-xs font-medium text-muted-foreground">
                        {{ t('search.recent') }}
                    </li>

                    <template v-for="(row, i) in recordRows" :key="row.id">
                        <li v-if="row.groupLabel" role="presentation" class="px-2 pb-1 pt-2 text-xs font-medium text-muted-foreground">
                            {{ row.groupLabel }}
                        </li>
                        <li
                            :id="optionId(row, commandRows.length + i)"
                            role="option"
                            :aria-selected="commandRows.length + i === activeIndex"
                            :class="[
                                'flex cursor-pointer flex-col rounded-md px-2 py-2 text-sm',
                                commandRows.length + i === activeIndex ? 'bg-accent text-accent-foreground' : '',
                            ]"
                            @click="choose(row)"
                            @mousemove="activeIndex = commandRows.length + i"
                        >
                            <span class="flex items-center gap-2 truncate font-medium">
                                <Clock v-if="showRecent" class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <SearchHighlight :text="row.item.title" :matches="row.item.matches" field="title" />
                            </span>
                            <span v-if="row.item.description" class="truncate text-xs text-muted-foreground">
                                <SearchHighlight :text="row.item.description" :matches="row.item.matches" field="description" />
                            </span>
                        </li>
                    </template>
                </ul>

                <p v-if="hasSearched && rows.length === 0" class="px-2 py-6 text-center text-sm text-muted-foreground">
                    {{ t('search.noResults') }}
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
