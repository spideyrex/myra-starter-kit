<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { TextColumn, DateColumn } from '@/composables/useTableSchema';
import { Action } from '@/composables/useTableActions';
import type { PaginatedData } from '@/types';
import type { SavedView } from '@/types/table-views';
import { BookOpen } from 'lucide-vue-next';

defineProps<{
    courses: PaginatedData<any>;
    filters: Record<string, string>;
    savedViews?: SavedView[];
}>();

const { t } = useI18n();

const columns = computed(() => [
    TextColumn.make('title').label(t('clusters.learning.courses.title')).sortable().searchable().grow(),
    TextColumn.make('lessons_count').label(t('clusters.learning.courses.lessonCount')).alignEnd(),
    DateColumn.make('created_at').label(t('clusters.learning.courses.created')).sortable(),
]);

const actions = computed(() => [
    Action.make(t('clusters.learning.courses.manage'))
        .icon(BookOpen)
        .url((row: any) => route('admin.learning.courses.lessons.index', row.id)),
]);
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[
            { label: t('clusters.learning.label') },
            { label: t('clusters.learning.courses.label') },
        ]"
    >
        <Head :title="t('clusters.learning.courses.label')" />

        <PageHeader
            :title="t('clusters.learning.courses.label')"
            :description="t('clusters.learning.courses.description')"
        />

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :data="courses"
                :filters="filters"
                :actions="actions"
                :saved-views="savedViews"
                route-name="admin.learning.courses.index"
                table-key="learning-courses"
                :search-placeholder="t('clusters.learning.courses.searchPlaceholder')"
            />
        </div>
    </AuthenticatedLayout>
</template>
