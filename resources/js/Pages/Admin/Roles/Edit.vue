<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { Switch } from '@/components/ui/switch';
import { Separator } from '@/components/ui/separator';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { useConfirm } from '@/composables/useConfirm';
import {
    ShieldCheck, Search, UserCog, Shield, Lock, Settings,
    Mail, Activity, Image, HeartPulse, Database, Key, Bell, Flame,
    Smartphone, FileText, Newspaper, FolderOpen, Check, Minus,
} from 'lucide-vue-next';

const props = defineProps<{
    role: {
        id: number;
        name: string;
        permissions: string[];
    } | null;
    permissionGroups: Record<string, string[]>;
}>();

const isEditing = computed(() => !!props.role);
const isSuperAdmin = computed(() => props.role?.name === 'super-admin');
const isSystemRole = computed(() => ['super-admin', 'admin'].includes(props.role?.name ?? ''));

const form = useForm({
    name: props.role?.name || '',
    permissions: [...(props.role?.permissions || [])],
});

// --- Search ---
const searchQuery = ref('');

// --- Module Icons ---
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

// --- Two-panel state ---
interface ModuleInfo {
    name: string;
    permissions: string[];
    selectedCount: number;
    totalCount: number;
    percent: number;
}

const activeModule = ref<string>(Object.keys(props.permissionGroups)[0] || '');

const moduleList = computed<ModuleInfo[]>(() => {
    const q = searchQuery.value.toLowerCase().trim();
    const entries = Object.entries(props.permissionGroups);
    return entries
        .map(([name, perms]) => {
            const filteredPerms = q ? perms.filter(p => p.toLowerCase().includes(q)) : perms;
            if (q && filteredPerms.length === 0) return null;
            const selectedCount = perms.filter(p => form.permissions.includes(p)).length;
            return {
                name,
                permissions: q ? filteredPerms : perms,
                selectedCount,
                totalCount: perms.length,
                percent: perms.length > 0 ? Math.round((selectedCount / perms.length) * 100) : 0,
            };
        })
        .filter((m): m is ModuleInfo => m !== null)
        .sort((a, b) => a.name.localeCompare(b.name));
});

const activeModuleData = computed(() =>
    moduleList.value.find(m => m.name === activeModule.value)
);

// Auto-select first visible module when active is filtered out
watch(moduleList, (list) => {
    if (list.length > 0 && !list.find(m => m.name === activeModule.value)) {
        activeModule.value = list[0].name;
    }
});

// --- Permission toggling ---
function togglePermission(permission: string, checked: boolean) {
    if (checked) {
        form.permissions.push(permission);
    } else {
        form.permissions = form.permissions.filter(p => p !== permission);
    }
}

function toggleGroup(group: string, selectAll: boolean) {
    const groupPerms = props.permissionGroups[group];
    if (selectAll) {
        const newPerms = new Set([...form.permissions, ...groupPerms]);
        form.permissions = [...newPerms];
    } else {
        form.permissions = form.permissions.filter(p => !groupPerms.includes(p));
    }
}

function isGroupChecked(group: string): boolean {
    return props.permissionGroups[group].every(p => form.permissions.includes(p));
}

function selectAll() {
    const allPerms = Object.values(props.permissionGroups).flat();
    form.permissions = [...new Set(allPerms)];
}

function deselectAll() {
    form.permissions = [];
}

// --- Progress ---
const totalPermissions = computed(() => Object.values(props.permissionGroups).flat().length);
const selectedCount = computed(() => form.permissions.length);
const progressPercent = computed(() =>
    totalPermissions.value > 0 ? Math.round((selectedCount.value / totalPermissions.value) * 100) : 0
);

// --- Module badge color ---
function getModuleBadgeVariant(module: ModuleInfo): 'default' | 'secondary' | 'outline' {
    if (module.selectedCount === module.totalCount) return 'default';
    if (module.selectedCount > 0) return 'secondary';
    return 'outline';
}

// --- Unsaved changes warning ---
const isDirty = computed(() => form.isDirty);

function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value) {
        e.preventDefault();
        e.returnValue = '';
    }
}

let removeInertiaListener: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    removeInertiaListener = router.on('before', (event) => {
        if (isDirty.value) {
            if (!confirm('You have unsaved changes. Are you sure you want to leave?')) {
                event.preventDefault();
            }
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    removeInertiaListener?.();
});

// --- Submit ---
const { confirm: confirmAction } = useConfirm();

async function submit() {
    if (isEditing.value) {
        const confirmed = await confirmAction({ title: 'Save Role Changes', description: 'Are you sure you want to update this role and its permissions?', confirmText: 'Save' });
        if (confirmed) form.put(route('admin.roles.update', props.role!.id));
    } else {
        const confirmed = await confirmAction({ title: 'Create Role', description: 'Are you sure you want to create this role?', confirmText: 'Create' });
        if (confirmed) form.post(route('admin.roles.store'));
    }
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'User Management' }, { label: 'Roles & Permissions', href: route('admin.roles.index') }, { label: isEditing ? 'Edit' : 'Create' }]">
        <Head :title="isEditing ? `Edit Role: ${role!.name}` : 'Create Role'" />

        <PageHeader :title="isEditing ? `Edit Role: ${role!.name}` : 'Create Role'" :description="isEditing ? 'Manage permissions for this role.' : 'Create a new role and assign permissions.'" />

        <div class="mt-6 space-y-6">
            <!-- Super-admin notice -->
            <Alert v-if="isSuperAdmin">
                <ShieldCheck class="size-4" />
                <AlertDescription>
                    The super-admin role has full access to all features via system bypass. Permissions cannot be modified.
                </AlertDescription>
            </Alert>

            <form @submit.prevent="submit" class="space-y-6">
                <Card class="max-w-4xl">
                    <CardContent class="pt-6">
                        <div class="space-y-2">
                            <Label for="name">Role Name</Label>
                            <Input id="name" v-model="form.name" required :disabled="isSystemRole" />
                            <p v-if="isSystemRole && isEditing" class="text-xs text-muted-foreground">System role names cannot be changed.</p>
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Permissions section (hidden for super-admin) -->
                <template v-if="!isSuperAdmin">
                    <!-- Search + Summary bar -->
                    <div class="space-y-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="relative w-full sm:max-w-xs">
                                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Filter permissions..."
                                    class="pl-9"
                                />
                            </div>
                            <div class="flex gap-2">
                                <Button type="button" variant="outline" size="sm" @click="selectAll">Select All</Button>
                                <Button type="button" variant="outline" size="sm" @click="deselectAll">Deselect All</Button>
                            </div>
                        </div>

                        <!-- Progress bar -->
                        <div class="flex items-center gap-3">
                            <Progress :model-value="progressPercent" class="flex-1" />
                            <span class="shrink-0 text-sm text-muted-foreground">
                                {{ selectedCount }} / {{ totalPermissions }} ({{ progressPercent }}%)
                            </span>
                        </div>
                    </div>

                    <p v-if="moduleList.length === 0" class="text-sm text-muted-foreground">
                        No permissions match "{{ searchQuery }}".
                    </p>

                    <!-- Two-panel layout -->
                    <Card v-if="moduleList.length > 0">
                        <CardContent class="p-0">
                            <!-- Mobile: horizontal scrollable pills -->
                            <div class="flex gap-2 overflow-x-auto border-b p-3 md:hidden">
                                <Button
                                    v-for="mod in moduleList"
                                    :key="mod.name"
                                    type="button"
                                    :variant="activeModule === mod.name ? 'default' : 'outline'"
                                    size="sm"
                                    class="shrink-0 gap-1.5 capitalize"
                                    @click="activeModule = mod.name"
                                >
                                    <component :is="getModuleIcon(mod.name)" class="size-3.5" />
                                    {{ mod.name }}
                                    <Badge
                                        :variant="getModuleBadgeVariant(mod)"
                                        class="ml-1 h-5 min-w-5 rounded-full px-1.5 text-xs"
                                        :class="activeModule === mod.name ? 'bg-background/20 text-primary-foreground' : ''"
                                    >
                                        {{ mod.selectedCount }}/{{ mod.totalCount }}
                                    </Badge>
                                </Button>
                            </div>

                            <div class="flex">
                                <!-- Desktop sidebar -->
                                <div class="hidden w-64 shrink-0 border-r md:block">
                                    <ScrollArea class="h-[500px]">
                                        <div class="p-1">
                                            <button
                                                v-for="mod in moduleList"
                                                :key="mod.name"
                                                type="button"
                                                class="flex w-full items-center gap-2 rounded-md px-3 py-2.5 text-left text-sm transition-colors"
                                                :class="activeModule === mod.name ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/50'"
                                                @click="activeModule = mod.name"
                                            >
                                                <component :is="getModuleIcon(mod.name)" class="size-4 shrink-0 text-muted-foreground" />
                                                <span class="flex-1 truncate capitalize">{{ mod.name }}</span>
                                                <div class="flex items-center gap-2">
                                                    <Badge
                                                        :variant="getModuleBadgeVariant(mod)"
                                                        class="h-5 min-w-5 rounded-full px-1.5 text-xs"
                                                    >
                                                        {{ mod.selectedCount }}/{{ mod.totalCount }}
                                                    </Badge>
                                                </div>
                                            </button>
                                        </div>
                                    </ScrollArea>
                                </div>

                                <!-- Right panel: permissions for active module -->
                                <div class="flex-1">
                                    <template v-if="activeModuleData">
                                        <!-- Module header -->
                                        <div class="flex items-center justify-between border-b px-4 py-3 sm:px-6">
                                            <div class="flex items-center gap-2">
                                                <component :is="getModuleIcon(activeModuleData.name)" class="size-5 text-muted-foreground" />
                                                <h3 class="text-sm font-semibold capitalize">{{ activeModuleData.name }}</h3>
                                                <Badge :variant="getModuleBadgeVariant(activeModuleData)" class="text-xs">
                                                    {{ activeModuleData.selectedCount }}/{{ activeModuleData.totalCount }}
                                                </Badge>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="toggleGroup(activeModuleData.name, !isGroupChecked(activeModuleData.name))"
                                            >
                                                {{ isGroupChecked(activeModuleData.name) ? 'Deselect Module' : 'Select Module' }}
                                            </Button>
                                        </div>

                                        <!-- Fill bar for module -->
                                        <div class="px-4 pt-3 sm:px-6">
                                            <Progress :model-value="activeModuleData.percent" class="h-1.5" />
                                        </div>

                                        <!-- Permissions list with switches -->
                                        <div class="p-4 sm:p-6">
                                            <div class="space-y-1">
                                                <label
                                                    v-for="permission in activeModuleData.permissions"
                                                    :key="permission"
                                                    class="flex cursor-pointer items-center justify-between rounded-md px-3 py-2.5 transition-colors hover:bg-muted/50"
                                                    :class="form.permissions.includes(permission) ? 'bg-primary/5' : ''"
                                                >
                                                    <div class="flex items-center gap-2.5">
                                                        <Check v-if="form.permissions.includes(permission)" class="size-4 text-success" />
                                                        <Minus v-else class="size-4 text-muted-foreground/30" />
                                                        <span class="text-sm">{{ permission.split('.').pop() }}</span>
                                                    </div>
                                                    <Switch
                                                        :model-value="form.permissions.includes(permission)"
                                                        @update:model-value="(v: boolean) => togglePermission(permission, v)"
                                                    />
                                                </label>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </template>

                <div class="flex gap-2">
                    <LoadingButton v-if="!isSuperAdmin" :loading="form.processing">{{ isEditing ? 'Save Changes' : 'Create Role' }}</LoadingButton>
                    <Button variant="outline" as-child>
                        <Link :href="route('admin.roles.index')">{{ isSuperAdmin ? 'Back' : 'Cancel' }}</Link>
                    </Button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
