<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { TextColumn, DateColumn } from '@/composables/useTableSchema';
import { DeleteAction } from '@/composables/useTableActions';
import { hydrateCrumbs } from '@/nav/breadcrumbs';
import type { PaginatedData } from '@/types';
import type { ServerCrumb } from '@/types/nav';
import { Plus } from 'lucide-vue-next';

const props = defineProps<{
    parent: { id: number; title: string };
    crumbs: ServerCrumb[];
    storeUrl: string;
    lessons: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const { t } = useI18n();

const breadcrumbs = computed(() => hydrateCrumbs(props.crumbs, t));

const columns = computed(() => [
    TextColumn.make('title').label(t('clusters.learning.lessons.title')).sortable().searchable().grow(),
    TextColumn.make('position').label(t('clusters.learning.lessons.position')).sortable().alignEnd(),
    DateColumn.make('created_at').label(t('clusters.learning.lessons.created')).sortable(),
]);

const actions = computed(() => [
    DeleteAction.make('admin.learning.courses.lessons.destroy')
        .label(t('clusters.learning.lessons.delete'))
        .confirmTitle(t('clusters.learning.lessons.delete'))
        .confirmDescription(t('clusters.learning.lessons.deleteConfirm'))
        .routeParams((row: any) => ({ course: props.parent.id, lesson: row.id })),
]);

const form = useForm({ title: '', position: '' });

function submit() {
    // The URL carries the parent; the body never does.
    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('clusters.learning.lessons.label')" />

        <PageHeader
            :title="t('clusters.learning.lessons.label')"
            :description="t('clusters.learning.lessons.description', { course: parent.title })"
        />

        <div class="mt-6 space-y-4">
            <Card>
                <CardContent class="pt-6">
                    <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submit">
                        <div class="flex-1 space-y-1.5">
                            <Label for="lesson-title">{{ t('clusters.learning.lessons.title') }}</Label>
                            <Input
                                id="lesson-title"
                                v-model="form.title"
                                :placeholder="t('clusters.learning.lessons.titlePlaceholder')"
                                :aria-invalid="!!form.errors.title"
                            />
                            <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                        </div>
                        <div class="space-y-1.5 sm:w-32">
                            <Label for="lesson-position">{{ t('clusters.learning.lessons.position') }}</Label>
                            <Input
                                id="lesson-position"
                                v-model="form.position"
                                type="number"
                                min="0"
                                :placeholder="t('clusters.learning.lessons.positionPlaceholder')"
                            />
                            <p v-if="form.errors.position" class="text-sm text-destructive">{{ form.errors.position }}</p>
                        </div>
                        <Button type="submit" :disabled="form.processing">
                            <Plus class="mr-2 size-4" />
                            {{ t('clusters.learning.lessons.add') }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <DataTable
                :columns="columns"
                :data="lessons"
                :filters="filters"
                :actions="actions"
                route-name="admin.learning.courses.lessons.index"
                :route-params="{ course: parent.id }"
                table-key="learning-lessons"
                :search-placeholder="t('clusters.learning.lessons.searchPlaceholder')"
            />
        </div>
    </AuthenticatedLayout>
</template>
