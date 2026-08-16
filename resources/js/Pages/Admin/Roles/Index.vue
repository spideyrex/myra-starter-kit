<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
// >>> MYRA v2.7 [C] START
import { useI18n } from 'vue-i18n';
import { REORDER_INSTRUCTIONS_ID, useReorderable } from '@/composables/useReorderable';
import { GripVertical, LayoutDashboard } from 'lucide-vue-next';
// <<< MYRA v2.7 [C] END
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { RowActions } from '@/components/admin';
import type { RowAction } from '@/types/admin';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { usePermissions } from '@/composables/usePermissions';
import {
    Plus, Shield, ShieldAlert, ShieldCheck, Check, Minus, ChevronRight,
    Pencil, Trash2, Copy, Users, KeyRound, UserCog, Lock, Settings,
    Mail, Activity, Image, HeartPulse, Database, Key, Bell, Flame,
    Smartphone, FileText, Newspaper, FolderOpen, Search, Power, Eye, EyeOff,
} from 'lucide-vue-next';

interface RoleItem {
    id: number;
    name: string;
    users_count: number;
    permissions: string[];
    is_active: boolean;
    visible: boolean;
    is_locked: boolean;
    is_privileged: boolean;
    // >>> MYRA v2.7 [C] START
    priority: number;
    has_dashboard: boolean;
    // <<< MYRA v2.7 [C] END
    created_at: string;
}

const props = defineProps<{
    roles: RoleItem[];
    permissionMatrix: Record<string, string[]>;
    roleNames: string[];
    rolePermissions: Record<string, string[]>;
    totalUsersWithRoles: number;
    totalPermissions: number;
    isSuperAdmin: boolean;
    // >>> MYRA v2.7 [C] START
    canManageRoleDashboards?: boolean;
    // <<< MYRA v2.7 [C] END
}>();

const { can } = usePermissions();
const { confirmDelete } = useConfirmAction();

const moduleIcons: Record<string, any> = {
    users: UserCog,
    roles: Shield,
    permissions: Lock,
    settings: Settings,
    email: Mail,
    'activity-log': Activity,
    media: Image,
    'system-health': HeartPulse,
    backups: Database,
    'api-tokens': Key,
    notifications: Bell,
    firebase: Flame,
    pages: FileText,
    articles: Newspaper,
    categories: FolderOpen,
    devices: Smartphone,
};

function getModuleIcon(module: string) {
    return moduleIcons[module] || Shield;
}

function cloneRole(roleId: number) {
    router.post(route('admin.roles.clone', roleId));
}

function toggleActive(roleId: number) {
    router.post(route('admin.roles.toggle-active', roleId), {}, { preserveScroll: true });
}

function toggleVisible(roleId: number) {
    router.post(route('admin.roles.toggle-visible', roleId), {}, { preserveScroll: true });
}

// >>> MYRA v2.7 [C] START
const { t } = useI18n();

// A local working copy: reordering is a draft until it is saved, so an arrow key
// never fires a request and a reload always wins.
const orderedRoles = ref<RoleItem[]>([...props.roles]);
const savingOrder = ref(false);

watch(() => props.roles, next => { orderedRoles.value = [...next]; }, { deep: false });

const canReorder = computed(() => props.isSuperAdmin === true);
const orderDirty = computed(() =>
    orderedRoles.value.map(r => r.id).join(',') !== props.roles.map(r => r.id).join(','));

function moveRole(key: string, toIndex: number): void {
    const from = orderedRoles.value.findIndex(r => String(r.id) === key);
    if (from === -1 || toIndex === from) return;

    const next = [...orderedRoles.value];
    const [moved] = next.splice(from, 1);
    next.splice(Math.max(0, Math.min(toIndex, next.length)), 0, moved);
    orderedRoles.value = next;
}

const priorityReorder = useReorderable<RoleItem>({
    items: orderedRoles,
    keyOf: role => String(role.id),
    onMove: moveRole,
    announce: (role, index, total) => t('roleDashboard.a11y.position', { label: role.name, index: index + 1, total }),
    enabled: canReorder,
    roleDescription: () => t('roleDashboard.a11y.roledescription'),
});

/** The handle lives in a table cell; `listitem` there would be a lie. */
function handleAttrs(role: RoleItem, index: number): Record<string, unknown> {
    return { ...priorityReorder.handleProps(role, index), role: undefined };
}

// The contract is an ordered ID SEQUENCE, highest priority first — never an index.
function savePriority(): void {
    savingOrder.value = true;

    router.post(route('admin.roles.reorder'), { ids: orderedRoles.value.map(r => r.id) }, {
        preserveScroll: true,
        onFinish: () => { savingOrder.value = false; },
    });
}

function discardOrder(): void {
    orderedRoles.value = [...props.roles];
}

/** Ziggy's client-side `app()->bound()`: bundle B's route may not exist yet. */
function hasRoute(name: string): boolean {
    try {
        const ziggy = (route as any)();

        return typeof ziggy?.has === 'function' ? ziggy.has(name) === true : false;
    } catch {
        return false;
    }
}

const canConfigureDashboards = computed(() =>
    props.canManageRoleDashboards === true && hasRoute('admin.role-dashboards.edit'));

function rowActions(role: RoleItem): RowAction[] {
    return [
        { label: 'Edit', icon: Pencil, href: route('admin.roles.edit', role.id), permission: 'roles.edit' },
        {
            label: t('roleDashboard.configure'),
            icon: LayoutDashboard,
            show: canConfigureDashboards.value,
            href: canConfigureDashboards.value ? route('admin.role-dashboards.edit', role.id) : undefined,
            tooltip: t('roleDashboard.configureRole', { role: role.name }),
        },
        { label: 'Clone', icon: Copy, permission: 'roles.create', onClick: () => cloneRole(role.id) },
        {
            label: 'Delete', icon: Trash2, permission: 'roles.delete', destructive: true, separator: true,
            show: !['super-admin', 'admin'].includes(role.name),
            onClick: () => confirmDelete('admin.roles.destroy', role.id, {
                title: 'Delete Role',
                description: 'Are you sure? Users with this role will lose their permissions.',
            }),
        },
    ];
}
// <<< MYRA v2.7 [C] END

const totalModules = computed(() => Object.keys(props.permissionMatrix).length);

const sortedModules = computed(() =>
    Object.keys(props.permissionMatrix).sort()
);

// --- Roles table helpers ---
function getRolePermissionCount(role: RoleItem): string {
    if (role.name === 'super-admin') return 'All';
    return String(role.permissions.length);
}

function getRolePermissionPercent(role: RoleItem): number {
    if (role.name === 'super-admin') return 100;
    if (props.totalPermissions === 0) return 0;
    return Math.round((role.permissions.length / props.totalPermissions) * 100);
}

// --- Module-level matrix ---
const expandedModules = ref<Set<string>>(new Set());
const matrixSearch = ref('');

function toggleModule(module: string) {
    const set = new Set(expandedModules.value);
    if (set.has(module)) {
        set.delete(module);
    } else {
        set.add(module);
    }
    expandedModules.value = set;
}

const filteredModules = computed(() => {
    if (!matrixSearch.value.trim()) return sortedModules.value;
    const q = matrixSearch.value.toLowerCase();
    return sortedModules.value.filter(m => m.toLowerCase().includes(q));
});

function getModuleStatus(roleName: string, module: string): { label: string; class: string } {
    if (roleName === 'super-admin') {
        return { label: 'Full', class: 'text-success font-medium' };
    }
    const modulePerms = props.permissionMatrix[module] || [];
    const rolePerms = props.rolePermissions[roleName] || [];
    const count = modulePerms.filter(p => rolePerms.includes(p)).length;
    const total = modulePerms.length;

    if (count === 0) return { label: '—', class: 'text-muted-foreground/30' };
    if (count === total) return { label: 'Full', class: 'text-success font-medium' };
    return { label: `${count}/${total}`, class: 'text-warning font-medium' };
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'User Management' }, { label: 'Roles & Permissions' }]">
        <Head title="Roles & Permissions" />

        <PageHeader title="Roles & Permissions" description="Manage roles, assign permissions, and review the permission matrix.">
            <template #actions>
                <Button v-if="can('roles.create')" as-child>
                    <Link :href="route('admin.roles.create')">
                        <Plus class="mr-2 size-4" />
                        Add Role
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Inline stat strip -->
        <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
            <span class="flex items-center gap-1.5">
                <Shield class="size-4" />
                <strong class="text-foreground">{{ roles.length }}</strong> roles
            </span>
            <Separator orientation="vertical" class="hidden h-4 sm:block" />
            <span class="flex items-center gap-1.5">
                <KeyRound class="size-4" />
                <strong class="text-foreground">{{ totalPermissions }}</strong> permissions across
                <strong class="text-foreground">{{ totalModules }}</strong> modules
            </span>
            <Separator orientation="vertical" class="hidden h-4 sm:block" />
            <span class="flex items-center gap-1.5">
                <Users class="size-4" />
                <strong class="text-foreground">{{ totalUsersWithRoles }}</strong> users assigned
            </span>
        </div>

        <!-- Roles Table -->
        <Card class="mt-6">
            <CardHeader class="flex-row items-center justify-between space-y-0 pb-3">
                <CardTitle class="text-base">Roles</CardTitle>
                <!-- >>> MYRA v2.7 [C] START -->
                <div v-if="canReorder && orderDirty" class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" :disabled="savingOrder" @click="discardOrder">
                        {{ t('roleDashboard.priority.reset') }}
                    </Button>
                    <Button size="sm" :disabled="savingOrder" @click="savePriority">
                        {{ savingOrder ? t('roleDashboard.priority.saving') : t('roleDashboard.priority.save') }}
                    </Button>
                </div>
                <!-- <<< MYRA v2.7 [C] END -->
            </CardHeader>
            <!-- >>> MYRA v2.7 [C] START -->
            <p v-if="canReorder" class="px-6 pb-3 text-xs text-muted-foreground">
                {{ t('roleDashboard.priority.description') }}
            </p>
            <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">
                {{ priorityReorder.announcement.value }}
            </p>
            <p :id="REORDER_INSTRUCTIONS_ID" class="sr-only">
                {{ t('roleDashboard.a11y.instructions') }}
            </p>
            <!-- <<< MYRA v2.7 [C] END -->
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ t('roleDashboard.priority.title') }}</caption>
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th scope="col" class="px-4 py-3 text-left font-medium">Role</th>
                                <th scope="col" class="px-4 py-3 text-center font-medium">{{ t('roleDashboard.priority.column') }}</th>
                                <th scope="col" class="px-4 py-3 text-center font-medium">Users</th>
                                <th scope="col" class="hidden px-4 py-3 text-left font-medium sm:table-cell">Permissions</th>
                                <th v-if="isSuperAdmin" scope="col" class="px-4 py-3 text-center font-medium">Status</th>
                                <th scope="col" class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(role, index) in orderedRoles"
                                :key="role.id"
                                class="border-b last:border-b-0 hover:bg-muted/20"
                                :class="{ 'bg-accent/40': priorityReorder.grabbed.value === String(role.id) }"
                                data-testid="role-row"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <!-- >>> MYRA v2.7 [C] START -->
                                        <button
                                            v-if="canReorder"
                                            type="button"
                                            v-bind="handleAttrs(role, index)"
                                            class="inline-flex size-7 shrink-0 cursor-grab items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            :aria-label="t('roleDashboard.a11y.grabHandle', { label: role.name })"
                                        >
                                            <GripVertical class="size-4" aria-hidden="true" />
                                        </button>
                                        <!-- <<< MYRA v2.7 [C] END -->
                                        <ShieldAlert v-if="role.name === 'super-admin'" class="size-4 shrink-0 text-destructive" />
                                        <Shield v-else class="size-4 shrink-0 text-muted-foreground" />
                                        <span class="font-medium">{{ role.name }}</span>
                                        <Badge v-if="role.name === 'super-admin'" variant="destructive" class="text-xs">System</Badge>
                                        <Badge v-else-if="role.name === 'admin'" variant="default" class="text-xs">System</Badge>
                                        <!-- >>> MYRA v2.7 [C] START -->
                                        <Badge v-if="role.has_dashboard" variant="outline" class="gap-1 text-xs">
                                            <LayoutDashboard class="size-3" aria-hidden="true" />
                                            {{ t('roleDashboard.configured') }}
                                        </Badge>
                                        <!-- <<< MYRA v2.7 [C] END -->
                                    </div>
                                </td>
                                <!-- >>> MYRA v2.7 [C] START -->
                                <td class="px-4 py-3 text-center tabular-nums text-muted-foreground" data-testid="role-priority">
                                    {{ role.priority }}
                                </td>
                                <!-- <<< MYRA v2.7 [C] END -->
                                <td class="px-4 py-3 text-center">
                                    <Badge variant="secondary" class="text-xs">
                                        {{ role.users_count }}
                                    </Badge>
                                </td>
                                <td class="hidden px-4 py-3 sm:table-cell">
                                    <div class="flex items-center gap-3">
                                        <span class="shrink-0 text-xs text-muted-foreground">
                                            {{ getRolePermissionCount(role) }}
                                        </span>
                                        <Progress
                                            :model-value="getRolePermissionPercent(role)"
                                            class="h-1.5 w-24"
                                        />
                                    </div>
                                </td>
                                <td v-if="isSuperAdmin" class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <Badge v-if="!role.is_active" variant="outline" class="text-xs text-destructive">Disabled</Badge>
                                        <Badge v-if="!role.visible" variant="outline" class="text-xs">Hidden</Badge>
                                        <template v-if="!role.is_locked">
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Button variant="ghost" size="icon" class="size-7" @click="toggleActive(role.id)">
                                                            <Power class="size-3.5" :class="role.is_active ? 'text-success' : 'text-muted-foreground'" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>{{ role.is_active ? 'Disable (block assignment)' : 'Enable role' }}</TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Button variant="ghost" size="icon" class="size-7" @click="toggleVisible(role.id)">
                                                            <component :is="role.visible ? Eye : EyeOff" class="size-3.5" :class="role.visible ? 'text-muted-foreground' : 'text-warning'" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>{{ role.visible ? 'Hide from non-super-admins' : 'Show to everyone' }}</TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </template>
                                        <Lock v-else class="size-3.5 text-muted-foreground/40" />
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <RowActions :actions="rowActions(role)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Module-level Permission Matrix -->
        <Card class="mt-6">
            <CardHeader class="flex-row items-center justify-between space-y-0 pb-3">
                <CardTitle class="text-base">Permission Matrix</CardTitle>
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="matrixSearch"
                        placeholder="Filter modules..."
                        class="h-8 pl-9 text-sm"
                    />
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <ScrollArea class="w-full">
                    <div class="min-w-[600px]">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50">
                                    <th class="sticky left-0 z-10 min-w-[200px] bg-muted/50 px-4 py-3 text-left font-medium">Module</th>
                                    <th v-for="roleName in roleNames" :key="roleName" class="px-3 py-3 text-center font-medium">
                                        <div class="flex flex-col items-center gap-1">
                                            <Shield class="size-4 text-muted-foreground" />
                                            <span class="text-xs">{{ roleName }}</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="module in filteredModules" :key="module">
                                    <!-- Module summary row -->
                                    <tr
                                        class="cursor-pointer border-b hover:bg-muted/20"
                                        @click="toggleModule(module)"
                                    >
                                        <td class="sticky left-0 z-10 bg-background px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <ChevronRight
                                                    class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                                    :class="{ 'rotate-90': expandedModules.has(module) }"
                                                />
                                                <component :is="getModuleIcon(module)" class="size-4 text-muted-foreground" />
                                                <span class="font-medium capitalize">{{ module }}</span>
                                                <span class="text-xs text-muted-foreground">({{ permissionMatrix[module].length }})</span>
                                            </div>
                                        </td>
                                        <td v-for="roleName in roleNames" :key="roleName" class="px-3 py-2.5 text-center">
                                            <span :class="getModuleStatus(roleName, module).class" class="text-xs">
                                                {{ getModuleStatus(roleName, module).label }}
                                            </span>
                                        </td>
                                    </tr>
                                    <!-- Expanded permission rows -->
                                    <template v-if="expandedModules.has(module)">
                                        <tr
                                            v-for="perm in permissionMatrix[module]"
                                            :key="perm"
                                            class="border-b bg-muted/10 last:border-b-0 hover:bg-muted/20"
                                        >
                                            <td class="sticky left-0 z-10 bg-muted/10 py-1.5 pl-14 pr-4 font-mono text-xs text-muted-foreground">
                                                {{ perm.split('.').pop() }}
                                            </td>
                                            <TooltipProvider v-for="roleName in roleNames" :key="roleName">
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <td class="px-3 py-1.5 text-center">
                                                            <div class="flex items-center justify-center">
                                                                <ShieldCheck v-if="roleName === 'super-admin'" class="size-3.5 text-primary" />
                                                                <Check v-else-if="rolePermissions[roleName]?.includes(perm)" class="size-3.5 text-success" />
                                                                <Minus v-else class="size-3.5 text-muted-foreground/30" />
                                                            </div>
                                                        </td>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <span v-if="roleName === 'super-admin'">Bypass — super-admin has all permissions</span>
                                                        <span v-else-if="rolePermissions[roleName]?.includes(perm)">{{ roleName }} has {{ perm }}</span>
                                                        <span v-else>{{ roleName }} does not have {{ perm }}</span>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        </tr>
                                    </template>
                                </template>
                                <tr v-if="filteredModules.length === 0">
                                    <td :colspan="roleNames.length + 1" class="px-4 py-8 text-center text-muted-foreground">
                                        No modules match "{{ matrixSearch }}".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>
            </CardContent>
        </Card>
    </AuthenticatedLayout>
</template>
