<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { sectionIcon } from './sectionIcons';
import {
    CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList,
} from '@/components/ui/command';
import { SECTION_GROUP_ORDER, type SectionSchema } from '@/composables/useSectionList';

const props = withDefaults(defineProps<{
    open: boolean;
    schemas: SectionSchema[];
    /** Types already at maxPerPage, or blocked by the page cap. */
    disabledTypes?: string[];
}>(), {
    schemas: () => [],
    disabledTypes: () => [],
});

const emit = defineEmits<{ 'update:open': [value: boolean]; select: [type: string] }>();

const { t, te } = useI18n();

function label(schema: SectionSchema): string {
    return te(schema.labelKey) ? t(schema.labelKey) : schema.key;
}

function description(schema: SectionSchema): string {
    return te(schema.descriptionKey) ? t(schema.descriptionKey) : '';
}

function groupLabel(group: string): string {
    const key = `pageBuilder.editor.catalogue.groups.${group}`;

    return te(key) ? t(key) : group;
}

const groups = computed(() => {
    const seen = new Set<string>();
    const ordered: string[] = [];

    for (const group of SECTION_GROUP_ORDER) {
        if (props.schemas.some(schema => schema.group === group)) {
            ordered.push(group);
            seen.add(group);
        }
    }

    for (const schema of props.schemas) {
        if (!seen.has(schema.group)) {
            ordered.push(schema.group);
            seen.add(schema.group);
        }
    }

    return ordered.map(group => ({
        group,
        items: props.schemas.filter(schema => schema.group === group),
    }));
});

function isDisabled(schema: SectionSchema): boolean {
    return props.disabledTypes.includes(schema.key);
}

function reason(schema: SectionSchema): string {
    return schema.maxPerPage > 0
        ? t('pageBuilder.editor.catalogue.maxReached', { max: schema.maxPerPage })
        : t('pageBuilder.editor.catalogue.blocked');
}

function choose(schema: SectionSchema): void {
    if (isDisabled(schema)) return;

    emit('select', schema.key);
    emit('update:open', false);
}
</script>

<template>
    <CommandDialog
        :open="open"
        :title="t('pageBuilder.editor.catalogue.title')"
        :description="t('pageBuilder.editor.catalogue.description')"
        @update:open="emit('update:open', $event)"
    >
        <CommandInput :placeholder="t('pageBuilder.editor.catalogue.placeholder')" />
        <CommandList>
            <CommandEmpty>{{ t('pageBuilder.editor.catalogue.empty') }}</CommandEmpty>

            <CommandGroup
                v-for="entry in groups"
                :key="entry.group"
                :heading="groupLabel(entry.group)"
            >
                <CommandItem
                    v-for="schema in entry.items"
                    :key="schema.key"
                    :value="`${schema.key} ${label(schema)} ${description(schema)}`"
                    :disabled="isDisabled(schema)"
                    class="items-start gap-3 py-2"
                    @select="choose(schema)"
                >
                    <component :is="sectionIcon(schema.icon)" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ label(schema) }}</span>
                        <span v-if="description(schema)" class="block truncate text-xs text-muted-foreground">
                            {{ description(schema) }}
                        </span>
                        <span v-if="isDisabled(schema)" class="block text-xs text-muted-foreground">
                            {{ reason(schema) }}
                        </span>
                    </span>
                </CommandItem>
            </CommandGroup>
        </CommandList>
    </CommandDialog>
</template>
