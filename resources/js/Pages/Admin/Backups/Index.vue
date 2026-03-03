<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingButton from '@/components/LoadingButton.vue';
import { Button } from '@/components/ui/button';
import { SimpleTable, DateCell } from '@/components/admin';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { useConfirm } from '@/composables/useConfirm';
import { Plus, Download, Trash2, Database } from 'lucide-vue-next';
import type { SimpleTableColumn } from '@/types/admin';

defineProps<{
    backups: Array<{
        path: string;
        name: string;
        size: string;
        date: string;
    }>;
}>();

const { confirm } = useConfirm();
const { confirmDelete } = useConfirmAction();
const creatingBackup = ref(false);

const columns: SimpleTableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'size', label: 'Size' },
    { key: 'date', label: 'Date' },
];

function downloadBackup(path: string) {
    window.location.href = route('admin.backups.download', { path });
}

async function createBackup() {
    const confirmed = await confirm({ title: 'Create Backup', description: 'Are you sure you want to create a new backup? This may take a moment.', confirmText: 'Create' });
    if (!confirmed) return;
    creatingBackup.value = true;
    router.post(route('admin.backups.store'), {}, {
        onFinish: () => { creatingBackup.value = false; },
    });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'System' }, { label: 'Backups' }]">
        <Head title="Backups" />
        <PageHeader title="Backups" description="Manage database and application backups.">
            <template #actions>
                <LoadingButton :loading="creatingBackup" type="button" @click="createBackup"><Plus class="mr-2 size-4" />Create Backup</LoadingButton>
            </template>
        </PageHeader>

        <SimpleTable :columns="columns" :items="backups" row-key="path" empty-title="No backups" empty-description="Create your first backup to get started." :empty-icon="Database">
            <template #cell-name="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>
            <template #cell-date="{ value }">
                <DateCell :value="value" />
            </template>
            <template #actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="icon-sm" @click="downloadBackup(row.path)">
                        <Download class="size-4" />
                    </Button>
                    <Button variant="ghost" size="icon-sm" @click="confirmDelete('admin.backups.destroy', { path: row.path }, { title: 'Delete Backup', description: 'This backup will be permanently deleted.' })">
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                </div>
            </template>
        </SimpleTable>
    </AuthenticatedLayout>
</template>
