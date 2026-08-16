<script setup lang="ts">
import { defineAsyncComponent, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { LayoutGrid, Plus, RotateCcw, Save, Undo2, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
    AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { REORDER_INSTRUCTIONS_ID } from '@/composables/useReorderable';
import type { CatalogueEntry } from '@/composables/useWidgetCatalogue';
import type { WidgetSchema } from '@/composables/useDashboardWidgets';

const props = withDefaults(defineProps<{
    editing: boolean;
    dirty: boolean;
    saving: boolean;
    customised: boolean;
    announcement: string;
    catalogue?: CatalogueEntry[];
    used?: string[];
    atLimit?: boolean;
    hidden?: WidgetSchema[];
}>(), { catalogue: () => [], used: () => [], atLimit: false, hidden: () => [] });

const emit = defineEmits<{
    (e: 'update:editing', value: boolean): void;
    (e: 'add', catalogueKey: string, binding: Record<string, unknown>): void;
    (e: 'undo'): void;
    (e: 'reset'): void;
    (e: 'save'): void;
    (e: 'cancel'): void;
    (e: 'restore', key: string): void;
}>();

const { t } = useI18n();

// The sheet is dead weight until someone opens the editor.
const WidgetCatalogueSheet = defineAsyncComponent(() => import('./WidgetCatalogueSheet.vue'));

const sheetOpen = ref(false);
const sheetPrefetched = ref(false);

function prefetch(): void {
    if (sheetPrefetched.value) return;
    sheetPrefetched.value = true;
    void import('./WidgetCatalogueSheet.vue');
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-end gap-2">
        <template v-if="!props.editing">
            <Button
                variant="outline"
                size="sm"
                @pointerenter="prefetch"
                @focus="prefetch"
                @click="emit('update:editing', true)"
            >
                <LayoutGrid class="mr-1.5 size-3.5" aria-hidden="true" />
                {{ t('dashboardLayout.edit') }}
            </Button>
        </template>

        <template v-else>
            <Button variant="outline" size="sm" :disabled="atLimit" @click="sheetOpen = true">
                <Plus class="mr-1.5 size-3.5" aria-hidden="true" />
                {{ t('dashboardLayout.addWidget') }}
            </Button>

            <Button variant="ghost" size="sm" @click="emit('undo')">
                <Undo2 class="mr-1.5 size-3.5" aria-hidden="true" />
                {{ t('dashboardLayout.undo') }}
            </Button>

            <AlertDialog v-if="customised">
                <AlertDialogTrigger as-child>
                    <Button variant="ghost" size="sm">
                        <RotateCcw class="mr-1.5 size-3.5" aria-hidden="true" />
                        {{ t('dashboardLayout.reset') }}
                    </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('dashboardLayout.reset') }}</AlertDialogTitle>
                        <AlertDialogDescription>{{ t('dashboardLayout.resetConfirm') }}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{{ t('dashboardLayout.cancel') }}</AlertDialogCancel>
                        <AlertDialogAction @click="emit('reset')">{{ t('dashboardLayout.reset') }}</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Button variant="ghost" size="sm" @click="emit('cancel')">
                <X class="mr-1.5 size-3.5" aria-hidden="true" />
                {{ t('dashboardLayout.cancel') }}
            </Button>

            <Button size="sm" :disabled="!dirty || saving" @click="emit('save')">
                <Save class="mr-1.5 size-3.5" aria-hidden="true" />
                {{ saving ? t('dashboardLayout.saving') : t('dashboardLayout.done') }}
            </Button>
        </template>

        <div v-if="props.editing && hidden.length > 0" class="flex w-full flex-wrap items-center justify-end gap-1.5">
            <span class="text-xs text-muted-foreground">{{ t('dashboardLayout.hiddenWidgets') }}</span>
            <Button
                v-for="widget in hidden"
                :key="widget.key"
                variant="outline"
                size="sm"
                class="h-7 px-2 text-xs"
                @click="emit('restore', widget.key)"
            >
                {{ widget.title ?? widget.key }} · {{ t('dashboardLayout.restore') }}
            </Button>
        </div>

        <!-- The ONE announcer for this surface. -->
        <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">
            {{ announcement }}
        </p>
        <p :id="REORDER_INSTRUCTIONS_ID" class="sr-only">
            {{ t('dashboardLayout.a11y.instructions') }}
        </p>

        <WidgetCatalogueSheet
            v-if="sheetOpen || sheetPrefetched"
            v-model:open="sheetOpen"
            :entries="catalogue"
            :used="used"
            :at-limit="atLimit"
            @add="(key: string, binding: Record<string, unknown>) => emit('add', key, binding)"
        />
    </div>
</template>
