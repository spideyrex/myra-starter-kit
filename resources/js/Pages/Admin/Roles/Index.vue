<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Roles</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-3 text-left font-medium">Role</th>
                                <th class="px-4 py-3 text-center font-medium">Users</th>
                                <th class="hidden px-4 py-3 text-left font-medium sm:table-cell">Permissions</th>
                                <th v-if="isSuperAdmin" class="px-4 py-3 text-center font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in roles" :key="role.id" class="border-b last:border-b-0 hover:bg-muted/20">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <ShieldAlert v-if="role.name === 'super-admin'" class="size-4 shrink-0 text-destructive" />
                                        <Shield v-else class="size-4 shrink-0 text-muted-foreground" />
                                        <span class="font-medium">{{ role.name }}</span>
                                        <Badge v-if="role.name === 'super-admin'" variant="destructive" class="text-xs">System</Badge>
                                        <Badge v-else-if="role.name === 'admin'" variant="default" class="text-xs">System</Badge>
                                    </div>
                                </td>
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
                                    <RowActions :actions="[
                                        { label: 'Edit', icon: Pencil, href: route('admin.roles.edit', role.id), permission: 'roles.edit' },
                                        { label: 'Clone', icon: Copy, permission: 'roles.create', onClick: () => cloneRole(role.id) },
                                        { label: 'Delete', icon: Trash2, permission: 'roles.delete', destructive: true, separator: true, show: !['super-admin', 'admin'].includes(role.name), onClick: () => confirmDelete('admin.roles.destroy', role.id, { title: 'Delete Role', description: 'Are you sure? Users with this role will lose their permissions.' }) },
                                    ]" />
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
