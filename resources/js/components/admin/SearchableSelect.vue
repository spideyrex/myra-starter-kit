<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-vue-next';
import type { SelectOption } from '@/types/admin';

/**
 * Searchable combobox for the Select field. Supports static options (filtered
 * client-side) and async options from an endpoint (`optionsUrl`), debounced —
 * ideal for relationship pickers. Endpoint receives `?search=` and returns
 * `[{ value, label }]` or `{ data: [...] }`.
 */
const props = withDefaults(defineProps<{
    modelValue?: string | number | null;
    options?: SelectOption[];
    optionsUrl?: string;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
}>(), {
    options: () => [],
    placeholder: 'Select…',
});

const emit = defineEmits<{ 'update:modelValue': [value: any] }>();

const open = ref(false);
const search = ref('');
const remoteOptions = ref<SelectOption[]>([]);
const loading = ref(false);
let debounce: ReturnType<typeof setTimeout> | undefined;

const isAsync = computed(() => !!props.optionsUrl);

const availableOptions = computed<SelectOption[]>(() => {
    if (isAsync.value) return remoteOptions.value;
    const q = search.value.toLowerCase();
    return (props.options || []).filter(o => o.label.toLowerCase().includes(q));
});

const selectedLabel = computed(() => {
    const all = isAsync.value ? remoteOptions.value : (props.options || []);
    const found = all.find(o => String(o.value) === String(props.modelValue));
    if (found) return found.label;
    return props.modelValue != null && props.modelValue !== '' ? String(props.modelValue) : '';
});

async function fetchRemote(q: string) {
    if (!props.optionsUrl) return;
    loading.value = true;
    try {
        const sep = props.optionsUrl.includes('?') ? '&' : '?';
        const res = await fetch(`${props.optionsUrl}${sep}search=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        remoteOptions.value = Array.isArray(data) ? data : (data.data ?? []);
    } catch {
        remoteOptions.value = [];
    } finally {
        loading.value = false;
    }
}

watch(search, (q) => {
    if (!isAsync.value) return;
    clearTimeout(debounce);
    debounce = setTimeout(() => fetchRemote(q), 250);
});

watch(open, (v) => {
    if (v && isAsync.value && remoteOptions.value.length === 0) fetchRemote('');
});

function select(value: any) {
    emit('update:modelValue', value);
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :disabled="disabled"
                class="w-full justify-between font-normal"
            >
                <span :class="{ 'text-muted-foreground': !selectedLabel }">{{ selectedLabel || placeholder }}</span>
                <ChevronsUpDown class="ml-2 size-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[--reka-popover-trigger-width] p-0" align="start">
            <!-- We filter ourselves (static) or rely on the server (async), so disable cmdk's built-in filter. -->
            <Command :should-filter="false">
                <CommandInput v-model="search" :placeholder="placeholder" />
                <CommandList>
                    <div v-if="loading" class="flex items-center justify-center py-4 text-sm text-muted-foreground">
                        <Loader2 class="mr-2 size-4 animate-spin" /> Loading…
                    </div>
                    <CommandEmpty v-else>No results found.</CommandEmpty>
                    <CommandGroup>
                        <CommandItem
                            v-for="opt in availableOptions"
                            :key="opt.value"
                            :value="String(opt.value)"
                            @select="select(opt.value)"
                        >
                            <Check class="mr-2 size-4" :class="String(opt.value) === String(modelValue) ? 'opacity-100' : 'opacity-0'" />
                            {{ opt.label }}
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
