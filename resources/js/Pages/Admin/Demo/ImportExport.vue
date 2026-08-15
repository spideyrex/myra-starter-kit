<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import ImportModal from '@/components/ImportModal.vue';
import ExportDropdown from '@/components/ExportDropdown.vue';
import { Button } from '@/components/ui/button';
import { TextColumn, DateColumn } from '@/composables/useTableSchema';
import { BulkAction } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import { ArrowLeft, Upload, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    contacts: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const { t } = useI18n();
const showImport = ref(false);

const columns = [
    TextColumn.make('name').label('Name').sortable().grow(),
    TextColumn.make('email').sortable(),
    TextColumn.make('phone').label('Phone'),
    TextColumn.make('company').sortable(),
    DateColumn.make('created_at').label('Created').sortable().format('relative'),
];

const bulkActions = [
    BulkAction.make('Delete')
        .action((ids) => router.post(route('admin.demo.bulk-action'), { ids, action: 'delete' }))
        .destructive()
        .requiresConfirmation('Delete Contacts', 'Are you sure you want to delete the selected contacts?')
        .icon(Trash2),
];

const xlsxColumns = [
    { header: 'Name', key: 'name' },
    { header: 'Email', key: 'email' },
    { header: 'Phone', key: 'phone' },
    { header: 'Company', key: 'company' },
    { header: 'Created', key: 'created_at' },
];

const xlsxData = computed(() => props.contacts.data);
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Import & Export' }]">
        <Head title="Import & Export Demo" />

        <PageHeader
            title="Import & Export"
            description="Streaming server export with a column picker, and a resumable import with per-cell validation."
        >
            <template #actions>
                <ExportDropdown
                    csv-route="admin.demo.export-csv"
                    xlsx-filename="contacts"
                    xlsx-sheet-name="Contacts"
                    :formats="['csv']"
                    :columns="xlsxColumns"
                    :data="xlsxData"
                />
                <Button variant="outline" @click="showImport = true">
                    <Upload class="mr-2 size-4" aria-hidden="true" />
                    {{ t('transfer.import.title') }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" aria-hidden="true" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="contacts"
                :filters="filters"
                :selectable="true"
                :bulk-actions="bulkActions"
                route-name="admin.demo.import-export"
                search-placeholder="Search contacts..."
            />
        </div>

        <ImportModal
            v-model:open="showImport"
            :title="t('transfer.import.title')"
            resource="demo-contacts"
            :sample-href="route('admin.demo.import-sample')"
        />
    </AuthenticatedLayout>
</template>
