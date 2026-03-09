<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { RowActions } from '@/components/admin';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { usePermissions } from '@/composables/usePermissions';
import {
    Plus, Shield, ShieldAlert, ShieldCheck, Check, Minus, ChevronDown,
    Pencil, Trash2, Copy, Users, KeyRound, UserCog, Lock, Settings,
    Mail, Activity, Image, HeartPulse, Database, Key, Bell, Flame,
    Smartphone, FileText, Newspaper, FolderOpen,
} from 'lucide-vue-next';

interface RoleItem {
    id: number;
    name: string;
    users_count: number;
    permissions: string[];
    created_at: string;
}

const props = defineProps<{
    roles: RoleItem[];
    permissionMatrix: Record<string, string[]>;
    roleNames: string[];
    rolePermissions: Record<string, string[]>;
    totalUsersWithRoles: number;
    totalPermissions: number;
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

function getTopModules(permissions: string[]): string[] {
    const modules: Record<string, number> = {};
    for (const p of permissions) {
        const mod = p.split('.')[0];
        modules[mod] = (modules[mod] || 0) + 1;
    }
    return Object.entries(modules)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 4)
        .map(([mod]) => mod);
}

function getGroupedPermissions(permissions: string[]): Record<string, string[]> {
    const grouped: Record<string, string[]> = {};
    for (const p of permissions) {
        const mod = p.split('.')[0];
        if (!grouped[mod]) grouped[mod] = [];
        grouped[mod].push(p);
    }
    return grouped;
}

function cloneRole(roleId: number) {
    router.post(route('admin.roles.clone', roleId));
}

const totalModules = computed(() => Object.keys(props.permissionMatrix).length);

const systemRolesCount = computed(() =>
    props.roles.filter(r => ['super-admin', 'admin'].includes(r.name)).length
);

const avgPermissions = computed(() => {
    const nonSystem = props.roles.filter(r => r.name !== 'super-admin');
    if (nonSystem.length === 0) return 0;
    return Math.round(nonSystem.reduce((sum, r) => sum + r.permissions.length, 0) / nonSystem.length);
});

const sortedModules = computed(() =>
    Object.keys(props.permissionMatrix).sort()
);
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

        <!-- Stat cards -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                title="Total Roles"
                :value="roles.length"
                :icon="Shield"
                :description="`${systemRolesCount} system roles`"
            />
            <StatCard
                title="Total Permissions"
                :value="totalPermissions"
                :icon="KeyRound"
                :description="`Across ${totalModules} modules`"
            />
            <StatCard
                title="Users with Roles"
                :value="totalUsersWithRoles"
                :icon="Users"
                description="Assigned at least one role"
            />
            <StatCard
                title="Avg. Permissions"
                :value="avgPermissions"
                :icon="Lock"
                description="Per non-system role"
            />
        </div>

        <!-- Tabs -->
        <Tabs default-value="roles" class="mt-6">
            <TabsList>
                <TabsTrigger value="roles" class="gap-1.5">
                    Roles
                    <Badge variant="secondary" class="ml-1 h-5 min-w-5 rounded-full px-1.5 text-xs">{{ roles.length }}</Badge>
                </TabsTrigger>
                <TabsTrigger value="matrix" class="gap-1.5">
                    Permission Matrix
                    <Badge variant="secondary" class="ml-1 h-5 min-w-5 rounded-full px-1.5 text-xs">{{ totalModules }}</Badge>
                </TabsTrigger>
            </TabsList>

            <!-- Tab 1: Roles -->
            <TabsContent value="roles" class="space-y-4">
                <Card v-for="role in roles" :key="role.id">
                    <Collapsible>
                        <div class="flex items-center justify-between px-4 py-3 sm:px-6 sm:py-4">
                            <CollapsibleTrigger class="flex flex-1 items-center gap-3 text-left">
                                <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform duration-200 [[data-state=open]_&]:rotate-180" />
                                <div class="flex flex-wrap items-center gap-2">
                                    <ShieldAlert v-if="role.name === 'super-admin'" class="size-4 text-destructive" />
                                    <Shield v-else class="size-4 text-muted-foreground" />
                                    <span class="font-semibold">{{ role.name }}</span>
                                    <Badge v-if="role.name === 'super-admin'" variant="destructive" class="text-xs">System</Badge>
                                    <Badge v-else-if="role.name === 'admin'" variant="default" class="text-xs">System</Badge>
                                    <Badge variant="secondary" class="text-xs">
                                        <Users class="mr-1 size-3" />
                                        {{ role.users_count }}
                                    </Badge>
                                    <Badge variant="outline" class="text-xs">
                                        <KeyRound class="mr-1 size-3" />
                                        {{ role.name === 'super-admin' ? 'All' : role.permissions.length }}
                                    </Badge>
                                    <!-- Top module badges -->
                                    <template v-if="role.name !== 'super-admin'">
                                        <Badge v-for="mod in getTopModules(role.permissions)" :key="mod" variant="outline" class="hidden text-xs capitalize sm:inline-flex">
                                            <component :is="getModuleIcon(mod)" class="mr-1 size-3" />
                                            {{ mod }}
                                        </Badge>
                                    </template>
                                </div>
                            </CollapsibleTrigger>
                            <RowActions :actions="[
                                { label: 'Edit', icon: Pencil, href: route('admin.roles.edit', role.id), permission: 'roles.edit' },
                                { label: 'Clone', icon: Copy, permission: 'roles.create', onClick: () => cloneRole(role.id) },
                                { label: 'Delete', icon: Trash2, permission: 'roles.delete', destructive: true, separator: true, show: !['super-admin', 'admin'].includes(role.name), onClick: () => confirmDelete('admin.roles.destroy', role.id, { title: 'Delete Role', description: 'Are you sure? Users with this role will lose their permissions.' }) },
                            ]" />
                        </div>
                        <CollapsibleContent>
                            <div class="border-t px-4 py-4 sm:px-6">
                                <div v-if="role.name === 'super-admin'" class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <ShieldCheck class="size-4" />
                                    <span>Super-admin bypasses all permission checks — full access to everything.</span>
                                </div>
                                <div v-else-if="role.permissions.length === 0" class="text-sm text-muted-foreground">
                                    No permissions assigned to this role.
                                </div>
                                <div v-else class="space-y-3">
                                    <div v-for="(perms, mod) in getGroupedPermissions(role.permissions)" :key="mod">
                                        <div class="mb-1 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            <component :is="getModuleIcon(mod as string)" class="size-3.5" />
                                            {{ mod }}
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span v-for="perm in perms" :key="perm" class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs">
                                                <Check class="size-3 text-success" />
                                                {{ perm.split('.').pop() }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>
            </TabsContent>

            <!-- Tab 2: Permission Matrix -->
            <TabsContent value="matrix">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Permission Matrix</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <ScrollArea class="w-full">
                            <div class="min-w-[600px]">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b bg-muted/50">
                                            <th class="sticky left-0 z-10 min-w-[200px] bg-muted/50 px-4 py-3 text-left font-medium">Permission</th>
                                            <th v-for="roleName in roleNames" :key="roleName" class="px-3 py-3 text-center font-medium">
                                                <div class="flex flex-col items-center gap-1">
                                                    <Shield class="size-4 text-muted-foreground" />
                                                    <span class="text-xs">{{ roleName }}</span>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="module in sortedModules" :key="module">
                                            <!-- Module group header -->
                                            <tr class="bg-muted/30">
                                                <td :colspan="roleNames.length + 1" class="sticky left-0 z-10 bg-muted/30 px-4 py-2">
                                                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                                        <component :is="getModuleIcon(module)" class="size-3.5" />
                                                        {{ module }}
                                                    </div>
                                                </td>
                                            </tr>
                                            <!-- Permission rows -->
                                            <tr v-for="perm in permissionMatrix[module]" :key="perm" class="border-b last:border-b-0 hover:bg-muted/20">
                                                <td class="sticky left-0 z-10 bg-background px-4 py-2 font-mono text-xs">
                                                    {{ perm }}
                                                </td>
                                                <TooltipProvider v-for="roleName in roleNames" :key="roleName">
                                                    <Tooltip>
                                                        <TooltipTrigger as-child>
                                                            <td class="px-3 py-2 text-center">
                                                                <div class="flex items-center justify-center">
                                                                    <ShieldCheck v-if="roleName === 'super-admin'" class="size-4 text-primary" />
                                                                    <Check v-else-if="rolePermissions[roleName]?.includes(perm)" class="size-4 text-success" />
                                                                    <Minus v-else class="size-4 text-muted-foreground/30" />
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
                                    </tbody>
                                </table>
                            </div>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </AuthenticatedLayout>
</template>
