<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import DateCell from '@/components/admin/DateCell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConfirm } from '@/composables/useConfirm';
import { LayoutDashboard, Settings2, Trash2 } from 'lucide-vue-next';

// >>> MYRA v2.7 [B] START
/** Local on purpose: no type crosses a bundle boundary from here. */
interface RoleDashboardRow {
    id: number;
    name: string;
    priority: number;
    is_active: boolean;
    is_locked: boolean;
    configured: boolean;
    widget_count: number;
    updated_at: string | null;
}

const props = defineProps<{
    roles: RoleDashboardRow[];
    dashboardKey: string;
    isSuperAdmin: boolean;
}>();

const { t } = useI18n();
const { confirm } = useConfirm();

const anyConfigured = computed(() => props.roles.some(role => role.configured));

/** The locked role is authored by a super-admin only — the server enforces it too. */
function authorable(role: RoleDashboardRow): boolean {
    return !role.is_locked || props.isSuperAdmin;
}

async function clearDashboard(role: RoleDashboardRow): Promise<void> {
    const ok = await confirm({
        title: t('roleDashboardAdmin.clearTitle', { role: role.name }),
        description: t('roleDashboardAdmin.clearDescription', { role: role.name }),
        confirmText: t('roleDashboardAdmin.clearConfirm'),
        cancelText: t('roleDashboardAdmin.cancel'),
        variant: 'destructive',
    });

    if (!ok) return;

    router.delete(route('admin.role-dashboards.destroy', role.id), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: t('roleDashboardAdmin.breadcrumb') }]">
        <Head :title="t('roleDashboardAdmin.title')" />

        <PageHeader
            :title="t('roleDashboardAdmin.title')"
            :description="t('roleDashboardAdmin.subtitle')"
        />

        <Card class="mt-6">
            <CardContent class="pt-6">
                <p class="mb-4 flex items-start gap-2 text-sm text-muted-foreground">
                    <LayoutDashboard class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    {{ t('roleDashboardAdmin.priorityHint') }}
                </p>

                <div v-if="!anyConfigured" class="rounded-lg border border-dashed px-4 py-6 text-center">
                    <p class="text-sm font-medium">{{ t('roleDashboardAdmin.emptyTitle') }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('roleDashboardAdmin.empty') }}</p>
                </div>

                <div class="mt-4">
                    <Table>
                        <TableCaption class="sr-only">{{ t('roleDashboardAdmin.caption') }}</TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">{{ t('roleDashboardAdmin.columns.role') }}</TableHead>
                                <TableHead scope="col">{{ t('roleDashboardAdmin.columns.priority') }}</TableHead>
                                <TableHead scope="col">{{ t('roleDashboardAdmin.columns.status') }}</TableHead>
                                <TableHead scope="col">{{ t('roleDashboardAdmin.columns.widgets') }}</TableHead>
                                <TableHead scope="col">{{ t('roleDashboardAdmin.columns.updated') }}</TableHead>
                                <TableHead scope="col" class="text-right">
                                    {{ t('roleDashboardAdmin.columns.actions') }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="role in roles" :key="role.id" :data-role="role.name">
                                <TableCell class="font-medium capitalize">
                                    {{ role.name }}
                                    <span v-if="!role.is_active" class="mt-0.5 block text-xs text-muted-foreground">
                                        {{ t('roleDashboardAdmin.inactive') }}
                                    </span>
                                    <span v-else-if="!authorable(role)" class="mt-0.5 block text-xs text-muted-foreground">
                                        {{ t('roleDashboardAdmin.locked') }}
                                    </span>
                                </TableCell>
                                <TableCell class="tabular-nums text-muted-foreground">{{ role.priority }}</TableCell>
                                <TableCell>
                                    <Badge :variant="role.configured ? 'default' : 'outline'">
                                        {{ role.configured
                                            ? t('roleDashboardAdmin.configured')
                                            : t('roleDashboardAdmin.notConfigured') }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="tabular-nums text-muted-foreground">
                                    {{ role.configured ? t('roleDashboardAdmin.widgetCount', { count: role.widget_count }) : '—' }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    <DateCell v-if="role.updated_at" :value="role.updated_at" format="relative" />
                                    <span v-else>{{ t('roleDashboardAdmin.never') }}</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button
                                            v-if="authorable(role)"
                                            as-child
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                :href="route('admin.role-dashboards.edit', role.id)"
                                                :aria-label="t('roleDashboardAdmin.configureRole', { role: role.name })"
                                            >
                                                <Settings2 class="mr-2 size-4" aria-hidden="true" />
                                                {{ t('roleDashboardAdmin.configure') }}
                                            </Link>
                                        </Button>
                                        <Button
                                            v-if="role.configured && authorable(role)"
                                            variant="ghost"
                                            size="sm"
                                            :aria-label="t('roleDashboardAdmin.clearRole', { role: role.name })"
                                            @click="clearDashboard(role)"
                                        >
                                            <Trash2 class="mr-2 size-4 text-destructive" aria-hidden="true" />
                                            {{ t('roleDashboardAdmin.clear') }}
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </AuthenticatedLayout>
</template>
<!-- <<< MYRA v2.7 [B] END -->
