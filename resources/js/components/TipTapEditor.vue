<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import LinkExtension from '@tiptap/extension-link';
import ImageExtension from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import { Button } from '@/components/ui/button';
import {
    Bold,
    Italic,
    Strikethrough,
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    Quote,
    Code,
    Link as LinkIcon,
    ImageIcon,
    Undo,
    Redo,
    Minus,
} from 'lucide-vue-next';

type ToolbarItem =
    | 'bold' | 'italic' | 'strike'
    | 'h1' | 'h2' | 'h3'
    | 'bulletList' | 'orderedList'
    | 'blockquote' | 'code' | 'codeBlock'
    | 'link' | 'image'
    | 'horizontalRule'
    | 'undo' | 'redo'
    | '|';

const props = withDefaults(defineProps<{
    modelValue?: string;
    placeholder?: string;
    toolbar?: ToolbarItem[];
    disabled?: boolean;
}>(), {
    modelValue: '',
    placeholder: 'Start writing...',
    toolbar: () => ['bold', 'italic', 'strike', '|', 'h1', 'h2', 'h3', '|', 'bulletList', 'orderedList', '|', 'blockquote', 'code', '|', 'link', 'image', '|', 'undo', 'redo'],
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    content: props.modelValue,
    editable: !props.disabled,
    extensions: [
        StarterKit,
        LinkExtension.configure({ openOnClick: false }),
        ImageExtension,
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val || '', { emitUpdate: false });
    }
});

watch(() => props.disabled, (val) => {
    editor.value?.setEditable(!val);
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function setLink() {
    const url = window.prompt('Enter URL');
    if (url) {
        editor.value?.chain().focus().setLink({ href: url }).run();
    }
}

function addImage() {
    const url = window.prompt('Enter image URL');
    if (url) {
        editor.value?.chain().focus().setImage({ src: url }).run();
    }
}

const toolbarItems: Record<string, { icon: any; action: () => void; isActive?: () => boolean }> = {
    bold: { icon: Bold, action: () => editor.value?.chain().focus().toggleBold().run(), isActive: () => !!editor.value?.isActive('bold') },
    italic: { icon: Italic, action: () => editor.value?.chain().focus().toggleItalic().run(), isActive: () => !!editor.value?.isActive('italic') },
    strike: { icon: Strikethrough, action: () => editor.value?.chain().focus().toggleStrike().run(), isActive: () => !!editor.value?.isActive('strike') },
    h1: { icon: Heading1, action: () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run(), isActive: () => !!editor.value?.isActive('heading', { level: 1 }) },
    h2: { icon: Heading2, action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(), isActive: () => !!editor.value?.isActive('heading', { level: 2 }) },
    h3: { icon: Heading3, action: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(), isActive: () => !!editor.value?.isActive('heading', { level: 3 }) },
    bulletList: { icon: List, action: () => editor.value?.chain().focus().toggleBulletList().run(), isActive: () => !!editor.value?.isActive('bulletList') },
    orderedList: { icon: ListOrdered, action: () => editor.value?.chain().focus().toggleOrderedList().run(), isActive: () => !!editor.value?.isActive('orderedList') },
    blockquote: { icon: Quote, action: () => editor.value?.chain().focus().toggleBlockquote().run(), isActive: () => !!editor.value?.isActive('blockquote') },
    code: { icon: Code, action: () => editor.value?.chain().focus().toggleCodeBlock().run(), isActive: () => !!editor.value?.isActive('codeBlock') },
    link: { icon: LinkIcon, action: setLink },
    image: { icon: ImageIcon, action: addImage },
    horizontalRule: { icon: Minus, action: () => editor.value?.chain().focus().setHorizontalRule().run() },
    undo: { icon: Undo, action: () => editor.value?.chain().focus().undo().run() },
    redo: { icon: Redo, action: () => editor.value?.chain().focus().redo().run() },
};
</script>

<template>
    <div class="tiptap-editor-wrapper">
        <div v-if="editor" class="flex flex-wrap items-center gap-1 rounded-t-md border border-b-0 bg-muted/50 p-1">
            <template v-for="(item, index) in toolbar" :key="index">
                <div v-if="item === '|'" class="mx-1 h-6 w-px bg-border" />
                <Button
                    v-else-if="toolbarItems[item]"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="size-8 p-0"
                    :class="{ 'bg-accent': toolbarItems[item].isActive?.() }"
                    :disabled="disabled"
                    @click="toolbarItems[item].action()"
                >
                    <component :is="toolbarItems[item].icon" class="size-4" />
                </Button>
            </template>
            <slot name="toolbar-extra" :editor="editor" />
        </div>
        <EditorContent
            :editor="editor"
            class="tiptap-content min-h-[200px] rounded-b-md border p-3 focus-within:ring-2 focus-within:ring-ring"
            :class="{ 'opacity-50 pointer-events-none': disabled }"
        />
    </div>
</template>

<style>
.tiptap-content .tiptap {
    outline: none;
    min-height: 180px;
}
.tiptap-content .tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: hsl(var(--muted-foreground));
    pointer-events: none;
    height: 0;
}
.tiptap-content .tiptap h1 { font-size: 1.5rem; font-weight: 700; margin: 0.5rem 0; }
.tiptap-content .tiptap h2 { font-size: 1.25rem; font-weight: 600; margin: 0.5rem 0; }
.tiptap-content .tiptap h3 { font-size: 1.1rem; font-weight: 600; margin: 0.5rem 0; }
.tiptap-content .tiptap ul { list-style: disc; padding-left: 1.5rem; margin: 0.5rem 0; }
.tiptap-content .tiptap ol { list-style: decimal; padding-left: 1.5rem; margin: 0.5rem 0; }
.tiptap-content .tiptap blockquote { border-left: 3px solid hsl(var(--border)); padding-left: 1rem; margin: 0.5rem 0; color: hsl(var(--muted-foreground)); }
.tiptap-content .tiptap pre { background: hsl(var(--muted)); border-radius: 0.375rem; padding: 0.75rem; margin: 0.5rem 0; font-family: monospace; font-size: 0.875rem; }
.tiptap-content .tiptap code { background: hsl(var(--muted)); padding: 0.125rem 0.25rem; border-radius: 0.25rem; font-size: 0.875rem; }
.tiptap-content .tiptap img { max-width: 100%; border-radius: 0.375rem; margin: 0.5rem 0; }
.tiptap-content .tiptap a { color: hsl(var(--primary)); text-decoration: underline; }
.tiptap-content .tiptap hr { border-color: hsl(var(--border)); margin: 1rem 0; }
</style>
