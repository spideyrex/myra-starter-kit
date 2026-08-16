<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { VueDraggable } from 'vue-draggable-plus';
import { ArrowLeft, GripVertical, Layers, Users } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DashboardGrid from '@/components/admin/DashboardGrid.vue';
import DashboardEditorBar from '@/components/admin/DashboardEditorBar.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { StatWidget, type ColSpan, type DashboardLayoutPayload } from '@/composables/useDashboardWidgets';
import { useDashboardLayout } from '@/composables/useDashboardLayout';
import { useReorderable } from '@/composables/useReorderable';
import type { CatalogueEntry } from '@/composables/useWidgetCatalogue';

const props = defineProps<{
    catalogue: CatalogueEntry[];
    saved: DashboardLayoutPayload;
    tampered: { submitted: any[]; resolved: any[] };
}>();

const { t } = useI18n();

const savedLayout = ref<DashboardLayoutPayload | null>(props.saved);

const declared = computed(() => [
    StatWidget.make('demo_active').title(t('dashboardLayout.demo.active')).value(() => 128).icon(Users).color('green'),
    StatWidget.make('demo_signups').title(t('dashboardLayout.demo.signups')).value(() => 42).icon(Users).color('blue'),
    StatWidget.make('demo_trend').title(t('dashboardLayout.demo.trend')).value(() => '12%').icon(Layers).color('violet').colSpan({ sm: 2, lg: 2 }),
    StatWidget.make('demo_pending').title(t('dashboardLayout.demo.pending')).value(() => 7).icon(Users).color('orange'),
]);

const dashboard = useDashboardLayout({
    dashboard: 'admin.dashboard',
    declared,
    saved: savedLayout,
    catalogue: () => props.catalogue,
    editable: true,
    t: (key, params) => t(key, (params ?? {}) as any),
});

dashboard.editing.value = true;

// --- tampering ---------------------------------------------------------------

const tamperedOn = ref(false);

const renderLayout = computed<DashboardLayoutPayload | null>(() => {
    const base = dashboard.layout.value;
    if (!tamperedOn.value || !base) return base;

    return {
        ...base,
        entries: [
            ...base.entries,
            { key: 'not-declared', order: 0, colSpan: 4, rowSpan: 1, hidden: false },
        ],
    };
});

const droppedEntries = computed(() => {
    if (!tamperedOn.value) return [];

    const declaredKeys = new Set([
        ...declared.value.map(w => w.toSchema().key),
        ...dashboard.instances.value.map(w => w.key),
    ]);

    return (renderLayout.value?.entries ?? [])
        .map(e => e.key)
        .filter(key => !declaredKeys.has(key));
});

// --- pointer drag (vue-draggable-plus, page chunk only) ----------------------

const dragList = ref<Array<{ key: string; title: string }>>([]);

const tiles = computed(() => (dashboard.widgets.value as any[]).map(w => ({
    key: w.key,
    title: w.title ?? w.key,
})));

const reorder = useReorderable<{ key: string; title: string }>({
    items: tiles,
    keyOf: item => item.key,
    onMove: (key, toIndex) => dashboard.moveTo(key, toIndex),
    announce: (item, index, total) => t('dashboardLayout.a11y.position', {
        label: item.title, index: index + 1, total,
    }),
    roleDescription: () => t('dashboardLayout.a11y.roledescription'),
});

function syncDragList(): void {
    dragList.value = tiles.value.map(tile => ({ ...tile }));
}

syncDragList();

function onDragUpdate(event: { oldIndex?: number; newIndex?: number }): void {
    const from = event.oldIndex ?? -1;
    const to = event.newIndex ?? -1;
    const item = tiles.value[from];

    if (!item || to < 0) {
        syncDragList();

        return;
    }

    dashboard.moveTo(item.key, to);
    syncDragList();
}

// --- payload preview ---------------------------------------------------------

const lastPayload = ref<string>('');

function showPayload(): void {
    lastPayload.value = JSON.stringify(dashboard.payload(), null, 2);
}

function resetLocal(): void {
    savedLayout.value = null;
    tamperedOn.value = false;
    lastPayload.value = '';
    syncDragList();
}

watch(tiles, syncDragList, { deep: true });
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: t('nav.featureDemos'), href: route('admin.demo.index') },
        { label: t('dashboardLayout.demo.title') },
    ]">
        <Head :title="t('dashboardLayout.demo.title')" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ t('dashboardLayout.demo.title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('dashboardLayout.demo.subtitle') }}</p>
                </div>
                <Link :href="route('admin.demo.index')" class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline">
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    {{ t('dashboardLayout.demo.back') }}
                </Link>
            </div>

            <DashboardEditorBar
                :editing="dashboard.editing.value"
                :dirty="dashboard.dirty.value"
                :saving="dashboard.saving.value"
                :customised="dashboard.customised.value"
                :announcement="dashboard.announcement.value"
                :catalogue="props.catalogue"
                :used="dashboard.usedCatalogueKeys.value"
                :hidden="dashboard.hidden.value"
                @update:editing="dashboard.editing.value = $event"
                @add="(key: string, binding: any) => dashboard.addInstance(key, binding)"
                @restore="dashboard.toggleHidden($event)"
                @undo="dashboard.undo()"
                @reset="resetLocal()"
                @save="showPayload()"
                @cancel="dashboard.cancel()"
            />

            <DashboardGrid
                :widgets="dashboard.widgets.value"
                :page-props="{}"
                :layout="renderLayout"
                :editing="dashboard.editing.value"
                @reorder="(key: string, index: number) => dashboard.moveTo(key, index)"
                @resize="(key: string, colSpan: ColSpan) => dashboard.resize(key, colSpan)"
                @rename="(key: string, title: string | null) => dashboard.rename(key, title)"
                @hide="dashboard.toggleHidden($event)"
                @remove="dashboard.removeInstance($event)"
            />

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('dashboardLayout.demo.pointerTitle') }}</CardTitle>
                        <CardDescription>{{ t('dashboardLayout.demo.pointerDescription') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <VueDraggable
                            v-model="dragList"
                            :animation="150"
                            handle=".myra-drag-handle"
                            class="space-y-2"
                            role="list"
                            @update="onDragUpdate"
                        >
                            <div
                                v-for="(tile, index) in dragList"
                                :key="tile.key"
                                class="flex items-center gap-2 rounded-lg border bg-card p-2"
                            >
                                <button
                                    type="button"
                                    class="myra-drag-handle inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    v-bind="reorder.handleProps(tile, index)"
                                    :aria-label="t('dashboardLayout.a11y.grabHandle', { label: tile.title })"
                                >
                                    <GripVertical class="size-4" aria-hidden="true" />
                                </button>
                                <span class="text-sm">{{ tile.title }}</span>
                            </div>
                        </VueDraggable>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('dashboardLayout.demo.announcerTitle') }}</CardTitle>
                        <CardDescription>{{ t('dashboardLayout.demo.announcerDescription') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="rounded-md bg-muted p-3 font-mono text-xs">
                            {{ dashboard.announcement.value || '—' }}
                        </p>
                        <p class="text-xs text-muted-foreground">{{ t('dashboardLayout.a11y.instructions') }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('dashboardLayout.demo.tamperTitle') }}</CardTitle>
                    <CardDescription>{{ t('dashboardLayout.demo.tamperDescription') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <Button variant="outline" size="sm" @click="tamperedOn = !tamperedOn">
                        {{ tamperedOn ? t('dashboardLayout.demo.tamperOff') : t('dashboardLayout.demo.tamperOn') }}
                    </Button>

                    <div v-if="tamperedOn" class="space-y-2 text-sm">
                        <p>
                            {{ t('dashboardLayout.demo.tamperEntryDropped') }}
                            <Badge v-for="key in droppedEntries" :key="key" variant="destructive" class="ml-1">{{ key }}</Badge>
                        </p>
                    </div>

                    <div class="space-y-1 text-sm">
                        <p>{{ t('dashboardLayout.demo.tamperSubmitted', { count: props.tampered.submitted.length }) }}</p>
                        <p>{{ t('dashboardLayout.demo.tamperResolved', { count: props.tampered.resolved.length }) }}</p>
                        <ul class="ml-4 list-disc text-xs text-muted-foreground">
                            <li v-for="item in props.tampered.submitted" :key="item.key">
                                <code>{{ item.catalogue }}</code> — {{ JSON.stringify(item.binding) }}
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="lastPayload">
                <CardHeader>
                    <CardTitle>{{ t('dashboardLayout.demo.payloadTitle') }}</CardTitle>
                    <CardDescription>{{ t('dashboardLayout.demo.payloadDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <pre class="max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs">{{ lastPayload }}</pre>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
