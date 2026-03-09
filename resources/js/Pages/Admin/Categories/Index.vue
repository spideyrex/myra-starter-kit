<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import type { CategoryData } from '@/types';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    categories: CategoryData[];
}>();

const { can } = usePermissions();
const { confirm } = useConfirm();

const showModal = ref(false);
const editingCategory = ref<CategoryData | null>(null);

const form = useForm({
    name: '',
    slug: '',
    description: '',
});

function generateSlug(name: string): string {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
}

function openCreate() {
    editingCategory.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(category: CategoryData) {
    editingCategory.value = category;
    form.name = category.name;
    form.slug = category.slug;
    form.description = category.description ?? '';
    form.clearErrors();
    showModal.value = true;
}

function handleNameInput(value: string | number) {
    form.name = String(value);
    if (!editingCategory.value) {
        form.slug = generateSlug(String(value));
    }
}

function submit() {
    if (editingCategory.value) {
        form.put(route('admin.categories.update', editingCategory.value.id), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; },
        });
    } else {
        form.post(route('admin.categories.store'), {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

async function deleteCategory(category: CategoryData) {
    if ((category.articles_count ?? 0) > 0) {
        await confirm({
            title: 'Cannot Delete',
            description: `This category has ${category.articles_count} article(s). Remove or reassign them first.`,
            confirmText: 'OK',
        });
        return;
    }

    const confirmed = await confirm({
        title: 'Delete Category',
        description: `Are you sure you want to delete "${category.name}"?`,
        variant: 'destructive',
        confirmText: 'Delete',
    });

    if (confirmed) {
        router.delete(route('admin.categories.destroy', category.id), { preserveScroll: true });
    }
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Content' }, { label: 'Categories' }]">
        <Head title="Categories" />

        <PageHeader title="Categories" description="Manage article categories.">
            <template #actions>
                <Button v-if="can('categories.create')" @click="openCreate">
                    <Plus class="mr-2 size-4" />
                    Add Category
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 overflow-x-auto rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Slug</TableHead>
                        <TableHead>Articles</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="categories.length === 0">
                        <TableCell colspan="4" class="py-8 text-center text-muted-foreground">
                            No categories found.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="category in categories" :key="category.id" class="hover:bg-muted/50">
                        <TableCell class="font-medium">{{ category.name }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ category.slug }}</TableCell>
                        <TableCell>
                            <Badge variant="secondary">{{ category.articles_count ?? 0 }}</Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Button v-if="can('categories.edit')" variant="ghost" size="icon" class="size-8" @click="openEdit(category)">
                                    <Pencil class="size-4" />
                                </Button>
                                <Button v-if="can('categories.delete')" variant="ghost" size="icon" class="size-8 text-destructive hover:text-destructive" @click="deleteCategory(category)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog v-model:open="showModal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editingCategory ? 'Edit Category' : 'Create Category' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingCategory ? 'Update the category details.' : 'Add a new category for organizing articles.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="cat-name">Name</Label>
                        <Input id="cat-name" :model-value="form.name" @update:model-value="handleNameInput" placeholder="Category name" />
                        <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="cat-slug">Slug</Label>
                        <Input id="cat-slug" v-model="form.slug" placeholder="category-slug" />
                        <p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="cat-description">Description</Label>
                        <Textarea id="cat-description" v-model="form.description" placeholder="Optional description..." rows="3" />
                        <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showModal = false">Cancel</Button>
                        <LoadingButton type="submit" :loading="form.processing">
                            {{ editingCategory ? 'Update' : 'Create' }}
                        </LoadingButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
