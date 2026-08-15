<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Search, Clock, Loader2 } from 'lucide-vue-next';
import SearchHighlight from '@/components/admin/SearchHighlight.vue';
import { useGlobalSearch, type SearchItem } from '@/composables/useGlobalSearch';

const props = withDefaults(defineProps<{
    /** Set false when a host layout already owns the Cmd/Ctrl+K binding. */
    bindShortcut?: boolean;
}>(), { bindShortcut: true });

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const inputRef = ref<HTMLInputElement | null>(null);
const listboxId = 'myra-command-listbox';

const { query, results, loading, hasSearched, activeIndex, flatItems, recent, next, prev, open: markOpened, reset } =
    useGlobalSearch();

const showRecent = computed(() => query.value.length < 2 && recent.value.length > 0);

const visibleItems = computed<SearchItem[]>(() => (showRecent.value ? recent.value : flatItems.value));

function optionId(item: SearchItem, index: number): string {
    return `${listboxId}-opt-${index}-${item.id}`;
}

const activeDescendant = computed(() => {
    const item = visibleItems.value[activeIndex.value];
    return item ? optionId(item, activeIndex.value) : undefined;
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

function move(delta: 1 | -1) {
    if (showRecent.value) {
        const total = recent.value.length;
        if (total === 0) return;
        activeIndex.value = (activeIndex.value + delta + total) % total;
        return;
    }
    delta === 1 ? next() : prev();
}

function choose(item?: SearchItem) {
    const target = item ?? visibleItems.value[activeIndex.value];
    if (!target) return;
    markOpened(target);
    open.value = false;
    router.visit(target.url);
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
                    :aria-expanded="visibleItems.length > 0"
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
                {{ loading ? t('common.loading') : t('search.a11y.results', { n: flatItems.length }) }}
            </p>

            <div class="max-h-[60vh] overflow-y-auto p-1">
                <p v-if="showRecent" class="px-2 py-1.5 text-xs font-medium text-muted-foreground">
                    {{ t('search.recent') }}
                </p>

                <ul :id="listboxId" role="listbox" :aria-label="t('search.a11y.results', { n: visibleItems.length })" class="space-y-0.5">
                    <template v-if="showRecent">
                        <li
                            v-for="(item, i) in recent"
                            :id="optionId(item, i)"
                            :key="`recent-${item.url}`"
                            role="option"
                            :aria-selected="activeIndex === i"
                            :class="[
                                'flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-sm',
                                activeIndex === i ? 'bg-accent text-accent-foreground' : '',
                            ]"
                            @click="choose(item)"
                            @mousemove="activeIndex = i"
                        >
                            <Clock class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span class="truncate">{{ item.title }}</span>
                        </li>
                    </template>

                    <template v-else>
                        <template v-for="group in results" :key="group.key ?? group.group">
                            <li role="presentation" class="px-2 pb-1 pt-2 text-xs font-medium text-muted-foreground">
                                {{ group.group }}
                            </li>
                            <li
                                v-for="item in group.items"
                                :id="optionId(item, flatItems.indexOf(item))"
                                :key="`${group.key}-${item.id}`"
                                role="option"
                                :aria-selected="flatItems.indexOf(item) === activeIndex"
                                :class="[
                                    'flex cursor-pointer flex-col rounded-md px-2 py-2 text-sm',
                                    flatItems.indexOf(item) === activeIndex ? 'bg-accent text-accent-foreground' : '',
                                ]"
                                @click="choose(item)"
                                @mousemove="activeIndex = flatItems.indexOf(item)"
                            >
                                <span class="truncate font-medium">
                                    <SearchHighlight :text="item.title" :matches="item.matches" field="title" />
                                </span>
                                <span v-if="item.description" class="truncate text-xs text-muted-foreground">
                                    <SearchHighlight :text="item.description" :matches="item.matches" field="description" />
                                </span>
                            </li>
                        </template>
                    </template>
                </ul>

                <p v-if="hasSearched && flatItems.length === 0" class="px-2 py-6 text-center text-sm text-muted-foreground">
                    {{ t('search.noResults') }}
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
