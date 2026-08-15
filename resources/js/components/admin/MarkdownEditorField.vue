<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { marked } from 'marked';
import { sanitizeHtml } from '@/composables/useSanitize';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useToast } from '@/composables/useToast';
import type { MarkdownButton, MarkdownMode } from '@/composables/useFormSchema';
import {
    Bold, Italic, Strikethrough, Link as LinkIcon, Heading, Quote, Code, SquareCode,
    List, ListOrdered, Table, Image as ImageIcon, Undo2, Redo2, Minus, Maximize2, Columns2, Eye, Pencil,
} from 'lucide-vue-next';

const props = withDefaults(defineProps<{
    fieldId: string;
    label?: string;
    error?: string;
    describedBy?: string;
    disabled?: boolean;
    placeholder?: string;
    modelValue?: any;
    rows?: number;
    mdToolbar?: MarkdownButton[];
    mdMode?: MarkdownMode;
    mdModeSwitcher?: boolean;
    mdFullscreen?: boolean;
    mdCounter?: boolean;
    mdMaxLength?: number;
    mdMinHeight?: string;
    mdMaxHeight?: string;
    mdUploadRoute?: string;
    mdMaxUploadKb?: number;
    mdAcceptedTypes?: string[];
}>(), {
    rows: 10,
    mdMode: 'split',
    mdModeSwitcher: true,
    mdMaxUploadKb: 5120,
    mdAcceptedTypes: () => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
});

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const { t } = useI18n();
const toast = useToast();

const textarea = ref<any>(null);
const fullscreenOpen = ref(false);
const mode = ref<MarkdownMode>(props.mdMode);

const value = computed({
    get: () => String(props.modelValue ?? ''),
    set: (v: string) => emit('update:modelValue', v),
});

const buttons = computed<MarkdownButton[]>(() => props.mdToolbar ?? []);
const has = (b: MarkdownButton) => buttons.value.includes(b);

const showModeSwitcher = computed(() => props.mdModeSwitcher !== false && has('mode'));

const rendered = computed(() => (value.value ? sanitizeHtml(marked.parse(value.value) as string) : ''));

const paneStyle = computed(() => ({
    minHeight: props.mdMinHeight ?? `${(props.rows ?? 10) * 1.5}rem`,
    maxHeight: props.mdMaxHeight,
}));

const charCount = computed(() => value.value.length);
const wordCount = computed(() => (value.value.trim() ? value.value.trim().split(/\s+/).length : 0));
const overLimit = computed(() => props.mdMaxLength != null && charCount.value > props.mdMaxLength);

function el(): HTMLTextAreaElement | null {
    const root = (textarea.value as any)?.$el ?? textarea.value;
    return (root as HTMLTextAreaElement) ?? null;
}

/** execCommand keeps the browser's native undo stack alive, so undo/redo keep working. */
function replaceSelection(text: string, selectStart?: number, selectEnd?: number) {
    const node = el();
    if (!node) return;
    node.focus();
    let ok = false;
    try {
        ok = document.execCommand('insertText', false, text);
    } catch {
        ok = false;
    }
    if (!ok) {
        const start = node.selectionStart;
        const end = node.selectionEnd;
        const next = value.value.slice(0, start) + text + value.value.slice(end);
        value.value = next;
    }
    if (selectStart != null) {
        const base = node.selectionStart - text.length;
        nextTick(() => node.setSelectionRange(base + selectStart, base + (selectEnd ?? selectStart)));
    }
}

function wrap(before: string, after = before, sample = '') {
    const node = el();
    if (!node) return;
    const selected = value.value.slice(node.selectionStart, node.selectionEnd) || sample;
    replaceSelection(`${before}${selected}${after}`, before.length, before.length + selected.length);
}

function prefixLines(prefix: string | ((i: number) => string)) {
    const node = el();
    if (!node) return;
    const start = node.selectionStart;
    const end = node.selectionEnd;
    const lineStart = value.value.lastIndexOf('\n', start - 1) + 1;
    const block = value.value.slice(lineStart, end) || '';
    const next = block
        .split('\n')
        .map((line, i) => `${typeof prefix === 'function' ? prefix(i) : prefix}${line}`)
        .join('\n');
    node.setSelectionRange(lineStart, end);
    replaceSelection(next);
}

const TABLE_TEMPLATE = '\n| Column | Column |\n| --- | --- |\n| Cell | Cell |\n';

function run(button: MarkdownButton) {
    switch (button) {
        case 'bold': return wrap('**', '**', t('fields.md.boldSample'));
        case 'italic': return wrap('*', '*', t('fields.md.italicSample'));
        case 'strike': return wrap('~~', '~~');
        case 'code': return wrap('`', '`');
        case 'codeBlock': return wrap('\n```\n', '\n```\n');
        case 'link': return wrap('[', '](https://)');
        case 'image': return wrap('![', '](https://)');
        case 'heading': return prefixLines('## ');
        case 'quote': return prefixLines('> ');
        case 'bulletList': return prefixLines('- ');
        case 'orderedList': return prefixLines(i => `${i + 1}. `);
        case 'hr': return replaceSelection('\n---\n');
        case 'table': return replaceSelection(TABLE_TEMPLATE);
        case 'undo': { el()?.focus(); document.execCommand('undo'); return; }
        case 'redo': { el()?.focus(); document.execCommand('redo'); return; }
        default: return;
    }
}

const TOOLBAR_META: Partial<Record<MarkdownButton, { icon: any; key: string }>> = {
    bold: { icon: Bold, key: 'bold' },
    italic: { icon: Italic, key: 'italic' },
    strike: { icon: Strikethrough, key: 'strike' },
    link: { icon: LinkIcon, key: 'link' },
    heading: { icon: Heading, key: 'heading' },
    quote: { icon: Quote, key: 'quote' },
    code: { icon: Code, key: 'code' },
    codeBlock: { icon: SquareCode, key: 'codeBlock' },
    bulletList: { icon: List, key: 'bulletList' },
    orderedList: { icon: ListOrdered, key: 'orderedList' },
    table: { icon: Table, key: 'table' },
    image: { icon: ImageIcon, key: 'image' },
    hr: { icon: Minus, key: 'hr' },
    undo: { icon: Undo2, key: 'undo' },
    redo: { icon: Redo2, key: 'redo' },
};

const actionButtons = computed(() => buttons.value
    .filter(b => TOOLBAR_META[b])
    .map(b => ({ button: b, icon: TOOLBAR_META[b]!.icon, labelKey: `fields.md.${TOOLBAR_META[b]!.key}` })));

// --- Paste / drop image upload (private disk, permission-gated URL) ---
const uploading = ref(false);

/** Client-side gate only — the endpoint re-validates size, mime AND magic bytes. */
function rejectReason(file: File): string | null {
    if (file.size / 1024 > props.mdMaxUploadKb) return t('table.upload.tooLarge', { max: props.mdMaxUploadKb });
    if (!props.mdAcceptedTypes.includes(file.type)) return t('table.upload.badType');
    return null;
}

async function uploadFile(file: File) {
    if (!props.mdUploadRoute) return;

    const reason = rejectReason(file);
    if (reason) {
        toast.error(reason);
        return;
    }

    const body = new FormData();
    body.append('file', file);
    uploading.value = true;
    try {
        const res = await fetch(route(props.mdUploadRoute), {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                Accept: 'application/json',
            },
        });
        if (!res.ok) throw new Error(String(res.status));
        const data = await res.json();
        if (!data?.url) throw new Error('no url');
        replaceSelection(`![${file.name}](${data.url})`);
    } catch {
        toast.error(t('fields.md.uploadFailed'));
    } finally {
        uploading.value = false;
    }
}

function filesFrom(e: ClipboardEvent | DragEvent): File[] {
    const list = (e as ClipboardEvent).clipboardData?.files ?? (e as DragEvent).dataTransfer?.files;
    return list ? Array.from(list).filter(f => f.type.startsWith('image/')) : [];
}

function onPaste(e: ClipboardEvent) {
    if (!props.mdUploadRoute) return;
    const files = filesFrom(e);
    if (!files.length) return;
    e.preventDefault();
    files.forEach(uploadFile);
}

function onDrop(e: DragEvent) {
    if (!props.mdUploadRoute) return;
    const files = filesFrom(e);
    if (!files.length) return;
    e.preventDefault();
    files.forEach(uploadFile);
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="actionButtons.length || showModeSwitcher || mdFullscreen" class="flex flex-wrap items-center gap-1 rounded-md border bg-muted/40 p-1">
            <TooltipProvider :delay-duration="300">
                <Tooltip v-for="item in actionButtons" :key="item.button">
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :disabled="disabled || (item.button === 'image' && uploading)"
                            :aria-label="t(item.labelKey)"
                            @click="run(item.button)"
                        >
                            <component :is="item.icon" class="size-4" aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ t(item.labelKey) }}</TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <div class="ml-auto flex items-center gap-1">
                <ToggleGroup
                    v-if="showModeSwitcher"
                    type="single"
                    size="sm"
                    variant="outline"
                    :model-value="mode"
                    :aria-label="t('fields.md.mode')"
                    @update:model-value="(v: any) => { if (v) mode = v as MarkdownMode }"
                >
                    <ToggleGroupItem value="edit" :aria-label="t('fields.md.edit')">
                        <Pencil class="size-3.5" aria-hidden="true" />
                    </ToggleGroupItem>
                    <ToggleGroupItem value="split" class="hidden md:inline-flex" :aria-label="t('fields.md.split')">
                        <Columns2 class="size-3.5" aria-hidden="true" />
                    </ToggleGroupItem>
                    <ToggleGroupItem value="preview" :aria-label="t('fields.md.preview')">
                        <Eye class="size-3.5" aria-hidden="true" />
                    </ToggleGroupItem>
                </ToggleGroup>

                <Button
                    v-if="mdFullscreen"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7"
                    :aria-label="t('fields.md.fullscreen')"
                    @click="fullscreenOpen = true"
                >
                    <Maximize2 class="size-4" aria-hidden="true" />
                </Button>
            </div>
        </div>

        <div
            class="grid grid-cols-1 gap-0 overflow-hidden rounded-md border"
            :class="mode === 'split' ? 'md:grid-cols-2' : ''"
        >
            <Textarea
                v-if="mode !== 'preview'"
                :id="fieldId"
                ref="textarea"
                v-model="value"
                :placeholder="placeholder || t('fields.md.placeholder')"
                :rows="rows"
                :disabled="disabled"
                :aria-describedby="describedBy"
                :aria-invalid="!!error"
                class="resize-none rounded-none border-0 font-mono text-sm"
                :class="mode === 'split' ? 'md:border-r' : ''"
                :style="paneStyle"
                @paste="onPaste"
                @drop="onDrop"
            />
            <div
                v-if="mode !== 'edit'"
                class="prose prose-sm dark:prose-invert max-w-none overflow-auto p-3"
                :class="mode === 'split' ? 'hidden md:block' : ''"
                :style="paneStyle"
                aria-live="polite"
                :aria-label="t('fields.md.renderedPreview')"
                v-html="rendered"
            />
        </div>

        <p v-if="uploading" class="text-xs text-muted-foreground" role="status" aria-live="polite">
            {{ t('table.upload.uploading') }}
        </p>

        <p v-if="mdCounter" class="text-xs" :class="overLimit ? 'text-destructive' : 'text-muted-foreground'" aria-live="polite">
            {{ t('fields.md.words', { count: wordCount }) }} ·
            {{ mdMaxLength != null ? `${charCount}/${mdMaxLength}` : charCount }}
        </p>

        <Dialog v-if="mdFullscreen" v-model:open="fullscreenOpen">
            <DialogContent class="h-[92vh] w-[96vw] max-w-none gap-3 sm:max-w-none">
                <DialogHeader>
                    <DialogTitle>{{ label || t('fields.md.fullscreen') }}</DialogTitle>
                    <DialogDescription class="sr-only">{{ t('fields.md.renderedPreview') }}</DialogDescription>
                </DialogHeader>
                <div class="grid min-h-0 flex-1 grid-cols-1 gap-0 overflow-hidden rounded-md border md:grid-cols-2">
                    <Textarea
                        v-model="value"
                        :placeholder="placeholder || t('fields.md.placeholder')"
                        :disabled="disabled"
                        class="h-full resize-none rounded-none border-0 font-mono text-sm md:border-r"
                        @paste="onPaste"
                        @drop="onDrop"
                    />
                    <div
                        class="prose prose-sm dark:prose-invert max-w-none overflow-auto p-3"
                        aria-live="polite"
                        :aria-label="t('fields.md.renderedPreview')"
                        v-html="rendered"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
