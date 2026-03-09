<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import TipTapEditor from '@/components/TipTapEditor.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown, ImageIcon, X } from 'lucide-vue-next';
import type { PageData } from '@/types';
import { ref } from 'vue';

const props = defineProps<{
    page: PageData | null;
}>();

const isEditing = computed(() => !!props.page);

const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    body_html: props.page?.body_html ?? '',
    excerpt: props.page?.excerpt ?? '',
    meta: {
        meta_title: props.page?.meta?.meta_title ?? '',
        meta_description: props.page?.meta?.meta_description ?? '',
        meta_keywords: props.page?.meta?.meta_keywords ?? '',
    },
    status: props.page?.status ?? 'draft',
    is_public: props.page?.is_public ?? true,
    published_at: props.page?.published_at ?? '',
    featured_image: null as File | null,
    remove_featured_image: false,
});

const imagePreview = ref<string | null>(props.page?.featured_image_url ?? null);
const seoOpen = ref(false);

function generateSlug(title: string): string {
    return title
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
}

// Auto-generate slug for new pages
if (!isEditing.value) {
    watch(() => form.title, (title) => {
        form.slug = generateSlug(title);
    });
}

function handleImageChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        form.featured_image = file;
        form.remove_featured_image = false;
        imagePreview.value = URL.createObjectURL(file);
    }
}

function removeImage() {
    form.featured_image = null;
    form.remove_featured_image = true;
    imagePreview.value = null;
}

function submit() {
    const options = {
        forceFormData: true,
        preserveScroll: true,
    };

    if (isEditing.value) {
        form
            .transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.pages.update', props.page!.id), options);
    } else {
        form.post(route('admin.pages.store'), options);
    }
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Content' },
        { label: 'Pages', href: route('admin.pages.index') },
        { label: isEditing ? 'Edit Page' : 'Create Page' },
    ]">
        <Head :title="isEditing ? 'Edit Page' : 'Create Page'" />

        <PageHeader :title="isEditing ? 'Edit Page' : 'Create Page'" :description="isEditing ? `Editing: ${page!.title}` : 'Create a new static page.'" />

        <form @submit.prevent="submit" class="mt-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Left Column -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Title -->
                    <div class="space-y-2">
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="form.title" placeholder="Page title" />
                        <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                    </div>

                    <!-- Slug -->
                    <div class="space-y-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" placeholder="page-slug" />
                        <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
                    </div>

                    <!-- Body -->
                    <div class="space-y-2">
                        <Label>Content</Label>
                        <TipTapEditor
                            v-model="form.body_html"
                            placeholder="Write your page content..."
                            :toolbar="['bold', 'italic', 'strike', '|', 'h1', 'h2', 'h3', '|', 'bulletList', 'orderedList', '|', 'blockquote', 'code', '|', 'link', 'image', '|', 'undo', 'redo']"
                        />
                        <p v-if="form.errors.body_html" class="text-sm text-destructive">{{ form.errors.body_html }}</p>
                    </div>

                    <!-- Excerpt -->
                    <div class="space-y-2">
                        <Label for="excerpt">Excerpt</Label>
                        <Textarea id="excerpt" v-model="form.excerpt" placeholder="Short summary of the page..." rows="3" />
                        <p v-if="form.errors.excerpt" class="text-sm text-destructive">{{ form.errors.excerpt }}</p>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="space-y-6">
                    <!-- Status Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Status</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="status">Status</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="published">Published</SelectItem>
                                        <SelectItem value="archived">Archived</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex items-center justify-between">
                                <Label for="is_public">Public</Label>
                                <Switch id="is_public" :checked="form.is_public" @update:checked="(v: boolean) => form.is_public = v" />
                            </div>

                            <div class="space-y-2">
                                <Label for="published_at">Publish Date</Label>
                                <Input id="published_at" type="datetime-local" v-model="form.published_at" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- SEO Card -->
                    <Collapsible v-model:open="seoOpen">
                        <Card>
                            <CardHeader class="cursor-pointer" @click="seoOpen = !seoOpen">
                                <div class="flex items-center justify-between">
                                    <CardTitle class="text-base">SEO</CardTitle>
                                    <CollapsibleTrigger as-child>
                                        <ChevronDown class="size-4 transition-transform" :class="{ 'rotate-180': seoOpen }" />
                                    </CollapsibleTrigger>
                                </div>
                            </CardHeader>
                            <CollapsibleContent>
                                <CardContent class="space-y-4">
                                    <div class="space-y-2">
                                        <Label for="meta_title">Meta Title</Label>
                                        <Input id="meta_title" v-model="form.meta.meta_title" placeholder="SEO title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="meta_description">Meta Description</Label>
                                        <Textarea id="meta_description" v-model="form.meta.meta_description" placeholder="SEO description" rows="3" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="meta_keywords">Meta Keywords</Label>
                                        <Input id="meta_keywords" v-model="form.meta.meta_keywords" placeholder="keyword1, keyword2" />
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    <!-- Featured Image Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Featured Image</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="imagePreview" class="relative mb-4">
                                <img :src="imagePreview" alt="Featured image" class="w-full rounded-md object-cover" />
                                <Button type="button" variant="destructive" size="icon" class="absolute right-2 top-2 size-7" @click="removeImage">
                                    <X class="size-4" />
                                </Button>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center rounded-md border-2 border-dashed p-6 text-center">
                                <ImageIcon class="mb-2 size-8 text-muted-foreground" />
                                <p class="text-sm text-muted-foreground">Upload a featured image</p>
                            </div>
                            <Input type="file" accept="image/*" class="mt-3" @change="handleImageChange" />
                            <p v-if="form.errors.featured_image" class="mt-1 text-sm text-destructive">{{ form.errors.featured_image }}</p>
                        </CardContent>
                    </Card>

                    <!-- Submit -->
                    <LoadingButton type="submit" :loading="form.processing" class="w-full">
                        {{ isEditing ? 'Update Page' : 'Create Page' }}
                    </LoadingButton>
                </div>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
