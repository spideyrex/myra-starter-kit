<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import FormField from '@/components/admin/FormField.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ChevronDown, ChevronUp, ImageOff, Plus, Trash2, Upload } from 'lucide-vue-next';
import { defaultsForListRow, type SectionFieldSchema } from '@/composables/useSectionList';
import type { FieldType } from '@/composables/useFormSchema';

/**
 * One declared section field, rendered through the existing FormField for every
 * type the form layer already knows. `image` and `list` are the two the form
 * layer has no primitive for, so they get a local control each.
 */
const props = withDefaults(defineProps<{
    field: SectionFieldSchema;
    modelValue: unknown;
    /** Card-scoped error bag, keyed by the path BELOW `data.`. */
    errors?: Record<string, string>;
    path: string;
    idPrefix: string;
    disabled?: boolean;
    uploadRoute?: string;
}>(), {
    errors: () => ({}),
    uploadRoute: 'admin.landing.builder.image',
});

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>();

const { t, te } = useI18n();

const FORM_TYPE: Record<string, FieldType> = {
    text: 'text',
    textarea: 'textarea',
    html: 'richtext',
    url: 'url',
    bool: 'switch',
    number: 'number-field',
    select: 'select',
    icon: 'select',
};

const label = computed(() => (te(props.field.labelKey) ? t(props.field.labelKey) : props.field.name));

const formType = computed<FieldType>(() => {
    const mapped = FORM_TYPE[props.field.type];
    if (mapped === 'select' && (props.field.options ?? []).length === 0) return 'text';

    return mapped ?? 'text';
});

const selectOptions = computed(() => (props.field.options ?? []).map(value => ({ label: value, value })));

const error = computed(() => props.errors[props.path]);

const rows = computed<Record<string, unknown>[]>(() =>
    (Array.isArray(props.modelValue) ? props.modelValue : []).filter(
        (row): row is Record<string, unknown> => typeof row === 'object' && row !== null && !Array.isArray(row),
    ));

const listFull = computed(() => props.field.max !== null && rows.value.length >= props.field.max);

function emitRows(next: Record<string, unknown>[]): void {
    emit('update:modelValue', next);
}

function addRow(): void {
    if (listFull.value) return;
    emitRows([...rows.value, defaultsForListRow(props.field)]);
}

function removeRow(index: number): void {
    emitRows(rows.value.filter((_, i) => i !== index));
}

function moveRow(index: number, delta: number): void {
    const target = index + delta;
    if (target < 0 || target >= rows.value.length) return;

    const next = [...rows.value];
    [next[index], next[target]] = [next[target], next[index]];
    emitRows(next);
}

function updateRow(index: number, name: string, value: unknown): void {
    const next = rows.value.map((row, i) => (i === index ? { ...row, [name]: value } : row));
    emitRows(next);
}

// --- image ---
const uploading = ref(false);
const brokenPreview = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const imagePath = computed(() => (typeof props.modelValue === 'string' ? props.modelValue : ''));

/** The public disk's conventional prefix. A miss degrades to the path text. */
const imageSrc = computed(() => (imagePath.value === '' ? '' : `/storage/${imagePath.value}`));

function uploadUrl(): string | null {
    const resolver = (globalThis as { route?: (name: string) => string }).route;

    try {
        return typeof resolver === 'function' ? resolver(props.uploadRoute) : null;
    } catch {
        return null;
    }
}

async function onPick(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;

    const url = uploadUrl();
    if (url === null) {
        toast.error(t('pageBuilder.editor.image.failed'));

        return;
    }

    const body = new FormData();
    body.append('file', file);
    uploading.value = true;

    try {
        const response = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                Accept: 'application/json',
            },
        });

        if (!response.ok) throw new Error(String(response.status));

        const data = await response.json();
        if (typeof data?.path !== 'string' || data.path === '') throw new Error('no path');

        brokenPreview.value = false;
        emit('update:modelValue', data.path);
    } catch {
        toast.error(t('pageBuilder.editor.image.failed'));
    } finally {
        uploading.value = false;
    }
}

function clearImage(): void {
    brokenPreview.value = false;
    emit('update:modelValue', '');
}
</script>

<template>
    <!-- Repeatable list of flat rows -->
    <div v-if="field.type === 'list'" class="space-y-2 sm:col-span-2">
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium">{{ label }}</span>
            <Badge v-if="field.max" variant="secondary" class="text-xs">{{ rows.length }}/{{ field.max }}</Badge>
        </div>

        <div
            v-for="(row, index) in rows"
            :key="`${path}-${index}`"
            class="rounded-md border border-dashed p-3"
        >
            <div class="mb-2 flex items-center gap-1">
                <span class="text-xs font-medium text-muted-foreground">
                    {{ t('pageBuilder.editor.list.rowLabel', { index: index + 1 }) }}
                </span>
                <div class="ml-auto flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        :disabled="disabled || index === 0"
                        :aria-label="t('pageBuilder.editor.list.moveUp', { index: index + 1 })"
                        @click="moveRow(index, -1)"
                    >
                        <ChevronUp class="size-3.5" aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7"
                        :disabled="disabled || index === rows.length - 1"
                        :aria-label="t('pageBuilder.editor.list.moveDown', { index: index + 1 })"
                        @click="moveRow(index, 1)"
                    >
                        <ChevronDown class="size-3.5" aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7 text-destructive hover:text-destructive"
                        :disabled="disabled"
                        :aria-label="t('pageBuilder.editor.list.removeRow', { index: index + 1 })"
                        @click="removeRow(index)"
                    >
                        <Trash2 class="size-3.5" aria-hidden="true" />
                    </Button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <SectionFieldControl
                    v-for="sub in field.of ?? []"
                    :key="sub.name"
                    :field="sub"
                    :model-value="row[sub.name]"
                    :errors="errors"
                    :path="`${path}.${index}.${sub.name}`"
                    :id-prefix="`${idPrefix}-${index}`"
                    :disabled="disabled"
                    @update:model-value="(value: unknown) => updateRow(index, sub.name, value)"
                />
            </div>
        </div>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="w-full border-dashed"
            :disabled="disabled || listFull"
            @click="addRow"
        >
            <Plus class="mr-2 size-4" aria-hidden="true" />
            {{ t('pageBuilder.editor.list.addRow') }}
        </Button>

        <p v-if="listFull && field.max" class="text-xs text-muted-foreground">
            {{ t('pageBuilder.editor.list.full', { max: field.max }) }}
        </p>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>

    <!-- Storage-path image with an inline upload -->
    <div v-else-if="field.type === 'image'" class="space-y-2">
        <span class="block text-sm font-medium">
            {{ label }}
            <span v-if="field.required" class="text-destructive">*</span>
        </span>

        <div class="flex items-start gap-3">
            <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted">
                <img
                    v-if="imageSrc && !brokenPreview"
                    :src="imageSrc"
                    :alt="label"
                    class="size-full object-cover"
                    @error="brokenPreview = true"
                />
                <ImageOff v-else class="size-5 text-muted-foreground" aria-hidden="true" />
            </div>

            <div class="min-w-0 flex-1 space-y-1.5">
                <p class="truncate text-xs text-muted-foreground">
                    {{ imagePath || t('pageBuilder.editor.image.none') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="disabled || uploading"
                        @click="fileInput?.click()"
                    >
                        <Upload class="mr-2 size-3.5" aria-hidden="true" />
                        {{ uploading ? t('pageBuilder.editor.image.uploading') : (imagePath ? t('pageBuilder.editor.image.replace') : t('pageBuilder.editor.image.upload')) }}
                    </Button>
                    <Button
                        v-if="imagePath"
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="disabled || uploading"
                        @click="clearImage"
                    >
                        {{ t('pageBuilder.editor.image.remove') }}
                    </Button>
                </div>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="sr-only"
                    :aria-label="t('pageBuilder.editor.image.upload')"
                    :disabled="disabled || uploading"
                    @change="onPick"
                />
            </div>
        </div>

        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>

    <!-- Everything the form layer already knows -->
    <FormField
        v-else
        :label="label"
        :name="`${idPrefix}-${path}`"
        :type="formType"
        :required="field.required"
        :error="error"
        :options="formType === 'select' ? selectOptions : undefined"
        :rows="field.type === 'textarea' ? 3 : undefined"
        :disabled="disabled"
        :class="field.type === 'html' || field.type === 'textarea' ? 'sm:col-span-2' : ''"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
