<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import Modal from '@/components/Modal.vue';
import TipTapEditor from '@/components/TipTapEditor.vue';
import { Eye, Send } from 'lucide-vue-next';
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
const showTestModal = ref(false);
const editorRef = ref<InstanceType<typeof TipTapEditor> | null>(null);

const form = useForm({
    name: props.template?.name || '',
    slug: props.template?.slug || '',
    subject: props.template?.subject || '',
    body_html: props.template?.body_html || '',
    variables: props.template?.variables || [],
});

function insertVariable(variable: string) {
    // Access the editor through the component's exposed slot
    const editorEl = document.querySelector('.tiptap-editor-wrapper .tiptap');
    if (editorEl) {
        // Use a simple approach: append to body_html
        form.body_html = form.body_html.replace(/<\/p>$/, `{{${variable}}}</p>`);
    }
}

const testForm = useForm({
    email: '',
    variables: {} as Record<string, string>,
});

function sendTestEmail() {
    testForm.post(route('admin.email-templates.send-test', props.template!.id), {
        onSuccess: () => { showTestModal.value = false; },
    });
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
                            <div class="flex gap-1">
                                <Button v-if="isEditing" type="button" variant="ghost" size="sm" @click="showTestModal = true">
                                    <Send class="mr-1 size-4" />Send Test
                                </Button>
                                <Button type="button" variant="ghost" size="sm" @click="showPreview = true">
                                    <Eye class="mr-1 size-4" />Preview
                                </Button>
                            </div>
                        </div>

                        <TipTapEditor
                            v-model="form.body_html"
                            placeholder="Start writing your email template..."
                            :toolbar="['bold', 'italic', '|', 'bulletList', 'orderedList', '|', 'link', 'image', '|', 'undo', 'redo']"
                        >
                            <template #toolbar-extra>
                                <div v-if="form.variables.length" class="ml-auto flex flex-wrap items-center gap-1">
                                    <span class="text-xs text-muted-foreground">Insert:</span>
                                    <Button v-for="v in form.variables" :key="v" type="button" variant="outline" size="sm" class="h-6 text-xs" @click="insertVariable(v)">
                                        {{ v }}
                                    </Button>
                                </div>
                            </template>
                        </TipTapEditor>
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

        <!-- Send Test Modal -->
        <Modal :open="showTestModal" title="Send Test Email" description="Send this template to a test recipient with sample values." @update:open="showTestModal = $event">
            <form @submit.prevent="sendTestEmail" class="space-y-4">
                <div class="space-y-2">
                    <Label>Recipient Email</Label>
                    <Input v-model="testForm.email" type="email" placeholder="test@example.com" required />
                    <p v-if="testForm.errors.email" class="text-sm text-destructive">{{ testForm.errors.email }}</p>
                </div>
                <div v-if="props.template?.variables?.length" class="space-y-3">
                    <Label class="text-muted-foreground">Template Variables</Label>
                    <div v-for="v in props.template.variables" :key="v" class="space-y-1">
                        <Label class="text-xs">{{ v }}</Label>
                        <Input v-model="testForm.variables[v]" :placeholder="'Value for {{' + v + '}}'" />
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="showTestModal = false">Cancel</Button>
                <LoadingButton :loading="testForm.processing" @click="sendTestEmail">
                    <Send class="mr-2 size-4" />Send Test
                </LoadingButton>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>
