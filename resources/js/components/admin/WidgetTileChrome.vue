<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Eye, EyeOff, GripVertical, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import type { ColSpan, WidgetSchema } from '@/composables/useDashboardWidgets';

const props = withDefaults(defineProps<{
    widget: WidgetSchema;
    editing: boolean;
    hidden?: boolean;
    removable?: boolean;
    handleProps?: Record<string, unknown>;
}>(), { hidden: false, removable: false, handleProps: () => ({}) });

const emit = defineEmits<{
    (e: 'resize', colSpan: ColSpan): void;
    (e: 'toggle-hidden'): void;
    (e: 'remove'): void;
    (e: 'rename', title: string | null): void;
}>();

const { t } = useI18n();
const renaming = ref(false);
const draft = ref('');

const currentSpan = computed(() => {
    const span = props.widget.colSpan;

    return String(typeof span === 'number' ? span : span?.lg ?? 1);
});

const label = computed(() => props.widget.title ?? props.widget.key);

function onSpan(value: unknown): void {
    const next = Number(value);
    if (!Number.isFinite(next)) return;

    emit('resize', next >= 2 ? { sm: 2, lg: next } : 1);
}

function startRename(): void {
    draft.value = props.widget.title ?? '';
    renaming.value = true;
}

function commitRename(): void {
    renaming.value = false;
    const value = draft.value.trim();
    emit('rename', value === '' ? null : value.slice(0, 60));
}
</script>

<template>
    <!-- Invisible AND non-focusable when editing is false. -->
    <div
        v-if="editing"
        class="absolute inset-x-0 top-0 z-10 flex flex-wrap items-center gap-1.5 rounded-t-xl border-b border-dashed border-primary/30 bg-background/95 p-1.5 backdrop-blur"
    >
        <button
            type="button"
            v-bind="handleProps"
            class="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :aria-label="t('dashboardLayout.a11y.grabHandle', { label })"
        >
            <GripVertical class="size-4" aria-hidden="true" />
        </button>

        <template v-if="renaming">
            <Input
                v-model="draft"
                class="h-7 w-36 text-xs"
                :aria-label="t('dashboardLayout.rename')"
                :maxlength="60"
                @keydown.enter.prevent="commitRename"
                @keydown.esc.prevent="renaming = false"
                @blur="commitRename"
            />
        </template>
        <Button v-else variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="startRename">
            {{ t('dashboardLayout.rename') }}
        </Button>

        <Select :model-value="currentSpan" @update:model-value="onSpan">
            <SelectTrigger class="h-7 w-[4.5rem] text-xs" :aria-label="t('dashboardLayout.size')">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="1">1</SelectItem>
                <SelectItem value="2">2</SelectItem>
                <SelectItem value="4">4</SelectItem>
            </SelectContent>
        </Select>

        <Button
            variant="ghost"
            size="sm"
            class="h-7 px-2 text-xs"
            :aria-pressed="hidden"
            @click="emit('toggle-hidden')"
        >
            <component :is="hidden ? Eye : EyeOff" class="mr-1 size-3.5" aria-hidden="true" />
            {{ hidden ? t('dashboardLayout.restore') : t('dashboardLayout.hide') }}
        </Button>

        <Button
            v-if="removable"
            variant="ghost"
            size="sm"
            class="h-7 px-2 text-xs text-destructive hover:text-destructive"
            :aria-label="t('dashboardLayout.remove')"
            @click="emit('remove')"
        >
            <Trash2 class="size-3.5" aria-hidden="true" />
        </Button>
    </div>
</template>
