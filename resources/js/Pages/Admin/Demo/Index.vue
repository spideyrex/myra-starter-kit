<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Type,
    Repeat,
    FormInput,
    CheckSquare,
    Trash2,
    PanelTop,
    Upload,
    Search,
    ArrowRight,
    Pencil,
    Eye,
    Info,
    Link2,
    Layers,
    GripVertical,
    LayoutDashboard,
    ListChecks,
    SlidersHorizontal,
} from 'lucide-vue-next';

const demos = [
    // --- Original 8 features ---
    {
        title: 'Rich Text Editor',
        description: 'TipTap-powered WYSIWYG editor with configurable toolbar, image/link support, and FormField integration.',
        icon: Type,
        route: 'admin.demo.rich-text-editor',
        badge: 'TipTap v3',
    },
    {
        title: 'Repeater Field',
        description: 'Dynamic add/remove rows with reordering, collapsible items, and nested field schemas.',
        icon: Repeat,
        route: 'admin.demo.repeater-field',
        badge: 'Form Builder',
    },
    {
        title: 'Form Builder',
        description: 'Complete showcase of all field types and layout components: Sections, Grids, Tabs, Wizard, Callouts, and more.',
        icon: FormInput,
        route: 'admin.demo.form-builder',
        badge: 'All Fields',
    },
    {
        title: 'Bulk Actions',
        description: 'DataTable with row selection, bulk actions with confirmation dialogs, filters, and CSV export.',
        icon: CheckSquare,
        route: 'admin.demo.bulk-actions',
        badge: 'DataTable',
    },
    {
        title: 'Soft Deletes & Trash',
        description: 'Trash/restore/force-delete workflow with conditional row actions and trashed filter.',
        icon: Trash2,
        route: 'admin.demo.soft-deletes',
        badge: 'SoftDeletes',
    },
    {
        title: 'Action Modals',
        description: 'Inline CRUD via modal dialogs with dynamic form schemas, no page navigation required.',
        icon: PanelTop,
        route: 'admin.demo.action-modals',
        badge: 'Modal',
    },
    {
        title: 'Import & Export',
        description: 'CSV import with column mapping and preview, plus streaming CSV export.',
        icon: Upload,
        route: 'admin.demo.import-export',
        badge: 'CSV',
    },
    {
        title: 'Global Search',
        description: 'Command palette (Cmd+K) with debounced API search across all resources.',
        icon: Search,
        route: 'admin.demo.global-search',
        badge: 'Cmd+K',
    },
    // --- 7 Advanced features ---
    {
        title: 'Inline Table Editing',
        description: 'Edit cell values directly in the table with Select, TextInput, and Toggle columns.',
        icon: Pencil,
        route: 'admin.demo.inline-editing',
        badge: 'Inline Edit',
    },
    {
        title: 'Conditional Fields',
        description: 'Show/hide form fields based on other field values using string or function conditions.',
        icon: Eye,
        route: 'admin.demo.conditional-fields',
        badge: 'Visibility',
    },
    {
        title: 'Infolist / Show Page',
        description: 'Schema-based read-only detail views with Text, Badge, Date, Boolean, Image, and Repeatable entries.',
        icon: Info,
        route: 'admin.demo.infolist',
        badge: 'Infolist',
    },
    {
        title: 'Relation Managers',
        description: 'Embedded DataTable for managing related records on a detail page with create modals.',
        icon: Link2,
        route: 'admin.demo.relation-manager',
        badge: 'Relations',
    },
    {
        title: 'Row Grouping & Summarizers',
        description: 'Group rows by column values with collapsible headers and aggregated summary rows.',
        icon: Layers,
        route: 'admin.demo.grouping',
        badge: 'Grouping',
    },
    {
        title: 'Drag-and-Drop Reordering',
        description: 'Reorder table rows with drag-and-drop, persists sort order to backend.',
        icon: GripVertical,
        route: 'admin.demo.reordering',
        badge: 'Sortable',
    },
    {
        title: 'Dashboard Widgets',
        description: 'Configurable widget builders: StatWidget, ChartWidget, TableWidget, and CustomWidget.',
        icon: LayoutDashboard,
        route: 'admin.demo.widgets',
        badge: 'Widgets',
    },
    // --- Medium Impact features ---
    {
        title: 'New Field Types',
        description: 'TimePicker, CheckboxList, KeyValue editor, and Markdown editor with live preview.',
        icon: ListChecks,
        route: 'admin.demo.field-types',
        badge: 'Fields',
    },
    {
        title: 'Advanced Filters',
        description: 'Date range filter with from/to inputs and recursive query builder with AND/OR groups.',
        icon: SlidersHorizontal,
        route: 'admin.demo.advanced-filters',
        badge: 'Filters',
    },
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo' }, { label: 'Feature Demos' }]">
        <Head title="Feature Demos" />

        <PageHeader
            title="Feature Demos"
            description="Interactive demos showcasing all starter kit features. Click any card to explore."
        />

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Card
                v-for="(demo, index) in demos"
                :key="demo.route"
                class="group relative overflow-hidden transition-all hover:shadow-md hover:border-primary/50"
                :class="`animate-stagger-${(index % 4) + 1}`"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <component :is="demo.icon" class="size-5" />
                        </div>
                        <Badge variant="secondary" class="text-xs">{{ demo.badge }}</Badge>
                    </div>
                    <CardTitle class="mt-3 text-base">{{ demo.title }}</CardTitle>
                    <CardDescription class="text-sm">{{ demo.description }}</CardDescription>
                </CardHeader>
                <CardContent class="pt-0">
                    <Button variant="ghost" size="sm" class="group/btn -ml-2 text-primary" as-child>
                        <Link :href="route(demo.route)">
                            View Demo
                            <ArrowRight class="ml-1 size-4 transition-transform group-hover/btn:translate-x-1" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
