<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConfirm } from '@/composables/useConfirm';
import { Plus, Pencil, Trash2 } from 'lucide-vue-next';

defineProps<{
    templates: Array<{
        id: number;
        name: string;
        slug: string;
        subject: string;
        created_at: string;
    }>;
}>();

const { confirm } = useConfirm();

async function deleteTemplate(id: number) {
    const confirmed = await confirm({ title: 'Delete Template', description: 'This action cannot be undone.', variant: 'destructive', confirmText: 'Delete' });
    if (confirmed) router.delete(route('admin.email-templates.destroy', id));
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Email' }, { label: 'Templates' }]">
        <Head title="Email Templates" />
        <PageHeader title="Email Templates" description="Manage email templates.">
            <template #actions>
                <Button as-child><Link :href="route('admin.email-templates.create')"><Plus class="mr-2 size-4" />New Template</Link></Button>
            </template>
        </PageHeader>

        <Card class="mt-6">
            <CardContent class="pt-6">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Subject</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="t in templates" :key="t.id">
                            <TableCell class="font-medium">{{ t.name }}</TableCell>
                            <TableCell class="text-muted-foreground">{{ t.slug }}</TableCell>
                            <TableCell>{{ t.subject }}</TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="route('admin.email-templates.edit', t.id)"><Pencil class="size-4" /></Link>
                                </Button>
                                <Button variant="ghost" size="sm" @click="deleteTemplate(t.id)">
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </AuthenticatedLayout>
</template>
