<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, type Component } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import SectionList from '@/components/admin/pagebuilder/SectionList.vue';
import SectionCatalogue from '@/components/admin/pagebuilder/SectionCatalogue.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ExternalLink, Plus, Redo2, Save, Undo2 } from 'lucide-vue-next';
import { useConfirm } from '@/composables/useConfirm';
import { useSectionList, type SectionSchema } from '@/composables/useSectionList';

const props = withDefaults(defineProps<{
    blocks?: unknown;
    schemas?: SectionSchema[];
    template?: string;
    maxBlocks?: number;
    canConvert?: boolean;
}>(), {
    blocks: () => [],
    schemas: () => [],
    template: 'classic',
    maxBlocks: 100,
    canConvert: true,
});

const { t, te } = useI18n();
const { confirm } = useConfirm();

const list = useSectionList({
    schemas: () => props.schemas,
    initial: props.blocks,
    t: (key, named) => (named ? t(key, named) : t(key)),
    has: key => te(key),
    maxBlocks: props.maxBlocks,
});

// Deliberately loose: Inertia's FormDataType rejects `unknown`, and a section's
// `data` map is unknown by design.
const form = useForm<{ blocks: any[] }>({ blocks: list.payload() });

const errors = computed(() => form.errors as unknown as Record<string, string>);

/** Bag entries that address no single card — the page cap, mostly. */
const listErrors = computed(() => Object.entries(errors.value)
    .filter(([key]) => !/^blocks\.\d+/.test(key))
    .map(([, message]) => message));

const catalogueOpen = ref(false);
const insertAt = ref<number | undefined>(undefined);

const disabledTypes = computed(() =>
    props.schemas.filter(schema => !list.canAdd(schema.key)).map(schema => schema.key));

const previewUrl = computed(() => `/?template=${encodeURIComponent(props.template)}`);

/**
 * The live-preview pane ships with the section library bundle. Resolving it
 * through a glob means this page renders with or without it — an absent pane
 * degrades to the saved public page in an iframe.
 */
const previewModules = import.meta.glob<{ default: Component }>(
    '../../../components/admin/pagebuilder/*Preview.vue',
    { eager: true },
);

const PreviewPane = computed<Component | null>(() => {
    const first = Object.values(previewModules)[0];

    return first?.default ?? null;
});

const wide = ref(false);
let media: MediaQueryList | null = null;

function onMediaChange(event: MediaQueryListEvent | MediaQueryList): void {
    wide.value = event.matches;
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
    if (!list.dirty.value) return;

    event.preventDefault();
    event.returnValue = '';
}

function onKeydown(event: KeyboardEvent): void {
    if (!(event.metaKey || event.ctrlKey)) return;

    const key = event.key.toLowerCase();

    if (key === 's') {
        event.preventDefault();
        save();

        return;
    }

    if (key === 'k') {
        event.preventDefault();
        openCatalogue(undefined);
    }
}

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    window.addEventListener('keydown', onKeydown);

    media = window.matchMedia('(min-width: 1280px)');
    onMediaChange(media);
    media.addEventListener('change', onMediaChange);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    window.removeEventListener('keydown', onKeydown);
    media?.removeEventListener('change', onMediaChange);
});

function openCatalogue(atIndex: number | undefined): void {
    const selected = list.rows.value.findIndex(row => row.id === list.selectedId.value);

    insertAt.value = atIndex ?? (selected >= 0 ? selected + 1 : undefined);
    catalogueOpen.value = true;
}

function addSection(type: string): void {
    list.add(type, insertAt.value);
    insertAt.value = undefined;
}

function save(): void {
    if (form.processing) return;

    form.blocks = list.payload();
    form.put(route('admin.landing.builder.update'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            const next = (page?.props as Record<string, unknown> | undefined)?.blocks;
            list.markSaved(Array.isArray(next) ? next : undefined);
            toast.success(t('pageBuilder.editor.savedToast'));
        },
        onError: () => toast.error(t('pageBuilder.editor.errors.title')),
    });
}

async function discard(): Promise<void> {
    if (!list.dirty.value) return;

    const ok = await confirm({
        title: t('pageBuilder.editor.confirm.discard.title'),
        description: t('pageBuilder.editor.confirm.discard.description'),
        confirmText: t('pageBuilder.editor.confirm.discard.confirm'),
        cancelText: t('common.cancel'),
        variant: 'destructive',
    });

    if (ok) list.discard();
}

async function convert(): Promise<void> {
    const resolver = (globalThis as { route?: (name: string) => string }).route;
    if (typeof resolver !== 'function') return;

    try {
        const response = await fetch(resolver('admin.landing.builder.convert'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                Accept: 'application/json',
            },
        });

        if (!response.ok) throw new Error(String(response.status));

        const data = await response.json();
        if (!Array.isArray(data?.blocks) || data.blocks.length === 0) {
            toast.error(t('pageBuilder.editor.convertEmpty'));

            return;
        }

        list.load(data.blocks);
        toast.success(t('pageBuilder.editor.convertLoaded', { count: data.blocks.length }));
    } catch {
        toast.error(t('pageBuilder.editor.convertFailed'));
    }
}

function revert(): void {
    list.load([]);
}
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[
            { label: t('navGroups.system') },
            { label: t('landing.title'), href: route('admin.landing.index') },
            { label: t('pageBuilder.editor.title') },
        ]"
    >
        <Head :title="t('pageBuilder.editor.title')" />

        <PageHeader :title="t('pageBuilder.editor.title')" :description="t('pageBuilder.editor.description')">
            <template #actions>
                <a
                    :href="previewUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
                >
                    <ExternalLink class="size-4" aria-hidden="true" />
                    {{ t('pageBuilder.editor.openPreview') }}
                </a>
            </template>
        </PageHeader>

        <div
            class="sticky top-0 z-20 mt-4 flex flex-wrap items-center gap-2 border-b bg-background/95 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/75"
        >
            <Button type="button" :disabled="form.processing" @click="save">
                <Save class="mr-2 size-4" aria-hidden="true" />
                {{ form.processing ? t('pageBuilder.editor.saving') : t('pageBuilder.editor.save') }}
            </Button>
            <Button type="button" variant="ghost" :disabled="!list.dirty.value || form.processing" @click="discard">
                {{ t('pageBuilder.editor.discard') }}
            </Button>

            <div class="flex items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    :disabled="!list.canUndo.value"
                    :aria-label="t('pageBuilder.editor.undo')"
                    @click="list.undo()"
                >
                    <Undo2 class="size-4" aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    :disabled="!list.canRedo.value"
                    :aria-label="t('pageBuilder.editor.redo')"
                    @click="list.redo()"
                >
                    <Redo2 class="size-4" aria-hidden="true" />
                </Button>
            </div>

            <Button
                type="button"
                variant="outline"
                :disabled="list.atLimit.value"
                @click="openCatalogue(undefined)"
            >
                <Plus class="mr-2 size-4" aria-hidden="true" />
                {{ t('pageBuilder.editor.addSection') }}
            </Button>

            <div class="ml-auto flex items-center gap-2">
                <Badge variant="secondary" class="text-xs">
                    {{ t('pageBuilder.editor.limit', { count: list.count.value, max: maxBlocks }) }}
                </Badge>
                <span v-if="list.dirty.value" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span class="size-2 rounded-full bg-warning" aria-hidden="true" />
                    {{ t('pageBuilder.editor.dirty') }}
                </span>
                <span v-else class="text-xs text-muted-foreground">{{ t('pageBuilder.editor.saved') }}</span>
            </div>
        </div>

        <div
            v-if="listErrors.length"
            role="alert"
            class="mt-4 rounded-md border border-destructive p-3 text-sm text-destructive"
        >
            <p class="font-medium">{{ t('pageBuilder.editor.errors.title') }}</p>
            <ul class="mt-1 list-inside list-disc">
                <li v-for="(message, index) in listErrors" :key="index">{{ message }}</li>
            </ul>
        </div>

        <p v-if="schemas.length === 0" class="mt-4 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
            {{ t('pageBuilder.editor.catalogue.unavailable') }}
        </p>

        <!-- >=1280px: outline beside the preview. Below: two tabs. -->
        <ResizablePanelGroup
            v-if="wide"
            id="page-builder"
            direction="horizontal"
            class="mt-4 items-stretch"
        >
            <ResizablePanel id="page-builder-outline" :default-size="42" :min-size="30">
                <section class="h-full overflow-y-auto pr-3" :aria-label="t('pageBuilder.editor.tabs.sections')">
                    <SectionList
                        :list="list"
                        :errors="errors"
                        :can-convert="canConvert"
                        :disabled="form.processing"
                        @open-catalogue="openCatalogue"
                        @convert="convert"
                        @revert="revert"
                    />
                </section>
            </ResizablePanel>

            <ResizableHandle id="page-builder-handle" with-handle />

            <ResizablePanel id="page-builder-preview" :default-size="58" :min-size="30">
                <section class="h-full pl-3" :aria-label="t('pageBuilder.editor.preview.label')">
                    <component
                        :is="PreviewPane"
                        v-if="PreviewPane"
                        :blocks="list.rows.value"
                        :template="template"
                    />
                    <div v-else class="flex h-full min-h-[32rem] flex-col gap-2">
                        <p class="text-xs text-muted-foreground">{{ t('pageBuilder.editor.preview.savedOnly') }}</p>
                        <iframe
                            :src="previewUrl"
                            :title="t('pageBuilder.editor.preview.label')"
                            class="h-full w-full flex-1 rounded-md border bg-background"
                        />
                    </div>
                </section>
            </ResizablePanel>
        </ResizablePanelGroup>

        <Tabs v-else default-value="sections" class="mt-4">
            <TabsList>
                <TabsTrigger value="sections">{{ t('pageBuilder.editor.tabs.sections') }}</TabsTrigger>
                <TabsTrigger value="preview">{{ t('pageBuilder.editor.tabs.preview') }}</TabsTrigger>
            </TabsList>
            <TabsContent value="sections">
                <SectionList
                    :list="list"
                    :errors="errors"
                    :can-convert="canConvert"
                    :disabled="form.processing"
                    @open-catalogue="openCatalogue"
                    @convert="convert"
                    @revert="revert"
                />
            </TabsContent>
            <TabsContent value="preview">
                <component
                    :is="PreviewPane"
                    v-if="PreviewPane"
                    :blocks="list.rows.value"
                    :template="template"
                />
                <div v-else class="space-y-2">
                    <p class="text-xs text-muted-foreground">{{ t('pageBuilder.editor.preview.savedOnly') }}</p>
                    <iframe
                        :src="previewUrl"
                        :title="t('pageBuilder.editor.preview.label')"
                        class="h-[32rem] w-full rounded-md border bg-background"
                    />
                </div>
            </TabsContent>
        </Tabs>

        <SectionCatalogue
            v-model:open="catalogueOpen"
            :schemas="schemas"
            :disabled-types="disabledTypes"
            @select="addSection"
        />
    </AuthenticatedLayout>
</template>
