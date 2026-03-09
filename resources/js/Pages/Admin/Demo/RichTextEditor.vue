<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import TipTapEditor from '@/components/TipTapEditor.vue';
import FormFields from '@/components/admin/FormFields.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { RichEditor, Section, type SchemaItem } from '@/composables/useFormSchema';
import { ArrowLeft } from 'lucide-vue-next';

const fullContent = ref('<h2>Welcome to the Rich Text Editor</h2><p>This editor is powered by <strong>TipTap v3</strong> with a configurable toolbar. Try out the formatting options above!</p><ul><li>Bold, italic, and strikethrough</li><li>Headings (H1, H2, H3)</li><li>Bullet and ordered lists</li><li>Blockquotes and code blocks</li></ul><blockquote>This is a blockquote example.</blockquote><p>You can also add <a href="https://tiptap.dev">links</a> and images.</p>');

const minimalContent = ref('<p>A minimal editor with only essential formatting.</p>');

const formData = ref({
    content: '<p>This editor is rendered via the <strong>FormFields</strong> component using <code>RichEditor.make()</code> schema builder.</p>',
    errors: {} as Record<string, string>,
});

const formSchema: SchemaItem[] = [
    Section.make('Rich Editor via Form Schema')
        .description('Using the RichEditor builder class for schema-driven forms.')
        .columns(1)
        .schema([
            RichEditor.make('content')
                .label('Content')
                .toolbar(['bold', 'italic', '|', 'h1', 'h2', '|', 'bulletList', 'orderedList', '|', 'blockquote', 'code', '|', 'link', '|', 'undo', 'redo'])
                .editorPlaceholder('Write your content here...'),
        ]),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Rich Text Editor' }]">
        <Head title="Rich Text Editor Demo" />

        <PageHeader title="Rich Text Editor" description="TipTap-powered WYSIWYG editor with configurable toolbar.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <!-- Full Toolbar -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <CardTitle>Full Toolbar</CardTitle>
                        <Badge variant="secondary">Default</Badge>
                    </div>
                    <CardDescription>All toolbar items enabled — bold, italic, headings, lists, blockquote, code, links, images, undo/redo.</CardDescription>
                </CardHeader>
                <CardContent>
                    <TipTapEditor v-model="fullContent" />
                </CardContent>
            </Card>

            <!-- Minimal Toolbar -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <CardTitle>Minimal Toolbar</CardTitle>
                        <Badge variant="outline">Custom</Badge>
                    </div>
                    <CardDescription>Only bold, italic, and link — great for simple text fields.</CardDescription>
                </CardHeader>
                <CardContent>
                    <TipTapEditor
                        v-model="minimalContent"
                        :toolbar="['bold', 'italic', '|', 'link', '|', 'undo', 'redo']"
                        placeholder="Write something simple..."
                    />
                </CardContent>
            </Card>

            <!-- Custom Toolbar with Extra Slot -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <CardTitle>Custom Toolbar Extension</CardTitle>
                        <Badge variant="outline">Slot</Badge>
                    </div>
                    <CardDescription>Using the <code>#toolbar-extra</code> slot to add custom buttons alongside the built-in toolbar.</CardDescription>
                </CardHeader>
                <CardContent>
                    <TipTapEditor
                        v-model="fullContent"
                        :toolbar="['bold', 'italic', 'strike', '|', 'bulletList', 'orderedList', '|', 'undo', 'redo']"
                    >
                        <template #toolbar-extra="{ editor }">
                            <div class="mx-1 h-6 w-px bg-border" />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 px-2 text-xs"
                                @click="editor.chain().focus().insertContent('{{ variable }}').run()"
                            >
                                Insert Variable
                            </Button>
                        </template>
                    </TipTapEditor>
                </CardContent>
            </Card>

            <!-- Via FormFields -->
            <FormFields :schema="formSchema" :form="formData" />
        </div>
    </AuthenticatedLayout>
</template>
