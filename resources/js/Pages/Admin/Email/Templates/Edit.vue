<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onBeforeUnmount } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import Modal from '@/components/Modal.vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import LinkExtension from '@tiptap/extension-link';
import ImageExtension from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import { Bold, Italic, List, ListOrdered, Link as LinkIcon, ImageIcon, Undo, Redo, Eye } from 'lucide-vue-next';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps<{
    template: {
        id: number;
        name: string;
        slug: string;
        subject: string;
        body_html: string;
        variables: string[] | null;
    } | null;
}>();

const isEditing = !!props.template;
const showPreview = ref(false);

const form = useForm({
    name: props.template?.name || '',
    slug: props.template?.slug || '',
    subject: props.template?.subject || '',
    body_html: props.template?.body_html || '',
    variables: props.template?.variables || [],
});

const editor = useEditor({
    content: form.body_html,
    extensions: [
        StarterKit,
        LinkExtension.configure({ openOnClick: false }),
        ImageExtension,
        Placeholder.configure({ placeholder: 'Start writing your email template...' }),
    ],
    onUpdate: ({ editor }) => {
        form.body_html = editor.getHTML();
    },
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

function insertVariable(variable: string) {
    editor.value?.chain().focus().insertContent(`{{${variable}}}`).run();
}

const { confirm } = useConfirm();

async function submit() {
    const confirmed = await confirm({ title: isEditing ? 'Update Template' : 'Create Template', description: `Are you sure you want to ${isEditing ? 'update' : 'create'} this email template?`, confirmText: isEditing ? 'Update' : 'Create' });
    if (!confirmed) return;
    if (isEditing) {
        form.put(route('admin.email-templates.update', props.template!.id));
    } else {
        form.post(route('admin.email-templates.store'));
    }
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Email' }, { label: 'Templates', href: route('admin.email-templates.index') }, { label: isEditing ? 'Edit' : 'Create' }]">
        <Head :title="isEditing ? 'Edit Template' : 'Create Template'" />
        <PageHeader :title="isEditing ? 'Edit Template' : 'Create Template'" />

        <Card class="mt-6 max-w-4xl">
            <CardContent class="pt-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Name</Label>
                            <Input v-model="form.name" required />
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Slug</Label>
                            <Input v-model="form.slug" required :disabled="isEditing" />
                            <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Subject</Label>
                        <Input v-model="form.subject" required />
                        <p v-if="form.errors.subject" class="text-sm text-destructive">{{ form.errors.subject }}</p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label>Body</Label>
                            <Button type="button" variant="ghost" size="sm" @click="showPreview = true">
                                <Eye class="mr-1 size-4" />Preview
                            </Button>
                        </div>

                        <!-- Toolbar -->
                        <div v-if="editor" class="flex flex-wrap items-center gap-1 rounded-t-md border border-b-0 bg-muted/50 p-1">
                            <Button type="button" variant="ghost" size="sm" class="size-8 p-0" @click="editor.chain().focus().toggleBold().run()" :class="{ 'bg-accent': editor.isActive('bold') }">
                                <Bold class="size-4" />
                            </Button>
                            <Button type="button" variant="ghost" size="sm" class="size-8 p-0" @click="editor.chain().focus().toggleItalic().run()" :class="{ 'bg-accent': editor.isActive('italic') }">
                                <Italic class="size-4" />
                            </Button>
                            <Button type="button" variant="ghost" size="sm" class="size-8 p-0" @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'bg-accent': editor.isActive('bulletList') }">
                                <List class="size-4" />
                            </Button>
                            <Button type="button" variant="ghost" size="sm" class="size-8 p-0" @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'bg-accent': editor.isActive('orderedList') }">
                                <ListOrdered class="size-4" />
                            </Button>
                            <Button type="button" variant="ghost" size="sm" class="size-8 p-0" @click="setLink">
                                <LinkIcon class="size-4" />
                            </Button>
                            <Button type="button" variant="ghost" size="sm" class="size-8 p-0" @click="addImage">
                                <ImageIcon class="size-4" />
                            </Button>
                            <div class="mx-1 h-6 w-px bg-border" />
                            <Button type="button" variant="ghost" size="sm" class="size-8 p-0" @click="editor.chain().focus().undo().run()">
                                <Undo class="size-4" />
                            </Button>
                            <Button type="button" variant="ghost" size="sm" class="size-8 p-0" @click="editor.chain().focus().redo().run()">
                                <Redo class="size-4" />
                            </Button>

                            <!-- Variable insertion -->
                            <div v-if="form.variables.length" class="ml-auto flex items-center gap-1">
                                <span class="text-xs text-muted-foreground">Insert:</span>
                                <Button v-for="v in form.variables" :key="v" type="button" variant="outline" size="sm" class="h-6 text-xs" @click="insertVariable(v)">
                                    {{ v }}
                                </Button>
                            </div>
                        </div>

                        <EditorContent :editor="editor" class="tiptap-editor min-h-[300px] rounded-b-md border p-3 focus-within:ring-2 focus-within:ring-ring" />
                        <p class="text-xs text-muted-foreground">Use &#123;&#123;variable_name&#125;&#125; for dynamic content.</p>
                        <p v-if="form.errors.body_html" class="text-sm text-destructive">{{ form.errors.body_html }}</p>
                    </div>

                    <div class="flex gap-2">
                        <LoadingButton :loading="form.processing">{{ isEditing ? 'Update' : 'Create' }}</LoadingButton>
                        <Button variant="outline" as-child><Link :href="route('admin.email-templates.index')">Cancel</Link></Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Preview Modal -->
        <Modal :open="showPreview" title="Email Preview" @update:open="showPreview = $event">
            <div class="space-y-2">
                <p class="text-sm"><strong>Subject:</strong> {{ form.subject }}</p>
                <div class="rounded border bg-white p-4 text-sm" v-html="form.body_html" />
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
.tiptap-editor .tiptap {
    outline: none;
    min-height: 280px;
}
.tiptap-editor .tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: hsl(var(--muted-foreground));
    pointer-events: none;
    height: 0;
}
</style>
