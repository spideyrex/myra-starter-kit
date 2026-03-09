<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import InfolistEntries from '@/components/admin/InfolistEntries.vue';
import RelationManager from '@/components/admin/RelationManager.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Section } from '@/composables/useInfolistSchema';
import {
    TextEntry, BadgeEntry, DateEntry, BooleanEntry, ImageEntry,
} from '@/composables/useInfolistSchema';
import {
    TextColumn, BadgeColumn, DateColumn,
} from '@/composables/useTableSchema';
import { TextInput, Select } from '@/composables/useFormSchema';
import { ArrowLeft, ShoppingCart, Activity } from 'lucide-vue-next';
import type { PaginatedData } from '@/types';

const props = defineProps<{
    user: Record<string, any>;
    orders: PaginatedData<any>;
    activities: PaginatedData<any>;
    filters: Record<string, string>;
}>();

const userInfoSchema: any[] = [
    Section.make('User Details').columns(3).schema([
        ImageEntry.make('avatar').label('Avatar').circular().size(64),
        TextEntry.make('name').label('Name').copyable(),
        TextEntry.make('email').label('Email').copyable(),
        BadgeEntry.make('status').label('Status').colors({
            active: 'default',
            suspended: 'destructive',
        }),
        DateEntry.make('created_at').label('Joined').format('relative'),
        BooleanEntry.make('email_verified').label('Verified'),
    ] as any),
];

const orderColumns = [
    TextColumn.make('id').label('#').sortable(),
    TextColumn.make('order_number').label('Order').sortable(),
    TextColumn.make('total').label('Total').money().alignEnd().sortable(),
    BadgeColumn.make('status').label('Status').colors({
        completed: 'default',
        processing: 'secondary',
        pending: 'outline',
        cancelled: 'destructive',
    }),
    DateColumn.make('created_at').label('Date').format('date').sortable(),
];

const activityColumns = [
    TextColumn.make('description').label('Action').grow(),
    TextColumn.make('subject').label('Subject'),
    DateColumn.make('created_at').label('Date').format('relative').sortable(),
];

const createOrderSchema = [
    TextInput.make('order_number').label('Order Number').required(),
    Select.make('status').label('Status').options({
        pending: 'Pending',
        processing: 'Processing',
        completed: 'Completed',
    }).required(),
    TextInput.make('total').label('Total ($)').numeric().required(),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: 'Relation Manager' },
    ]">
        <Head title="Relation Manager Demo" />

        <PageHeader title="Relation Managers" description="Embedded DataTable for managing related records on a detail page.">
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
            <!-- User Infolist Header -->
            <InfolistEntries :schema="userInfoSchema" :record="user" />

            <!-- Relation Manager Tabs -->
            <Tabs default-value="orders">
                <TabsList>
                    <TabsTrigger value="orders">
                        <ShoppingCart class="mr-2 size-4" />
                        Orders ({{ orders.meta.total }})
                    </TabsTrigger>
                    <TabsTrigger value="activity">
                        <Activity class="mr-2 size-4" />
                        Activity ({{ activities.meta.total }})
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="orders" class="mt-4">
                    <RelationManager
                        title="Orders"
                        description="Orders placed by this user"
                        :columns="orderColumns"
                        :data="orders"
                        :filters="filters"
                        route-name="admin.demo.relation-manager"
                        :searchable="true"
                        search-placeholder="Search orders..."
                        :create-schema="createOrderSchema"
                        create-route-name="admin.demo.relation-create"
                        create-label="Add Order"
                    />
                </TabsContent>
                <TabsContent value="activity" class="mt-4">
                    <RelationManager
                        title="Activity Log"
                        description="Recent actions by this user"
                        :columns="activityColumns"
                        :data="activities"
                        :filters="filters"
                        route-name="admin.demo.relation-manager"
                        query-prefix="act_"
                        :searchable="false"
                    />
                </TabsContent>
            </Tabs>
        </div>
    </AuthenticatedLayout>
</template>
