<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { useConfirm } from '@/composables/useConfirm';
import {
    ShieldCheck, Search, UserCog, Shield, Lock, Settings,
    Mail, Activity, Image, HeartPulse, Database, Key, Bell, Flame,
    Smartphone, FileText, Newspaper, FolderOpen,
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

const filteredPermissionGroups = computed(() => {
    if (!searchQuery.value.trim()) return props.permissionGroups;
    const q = searchQuery.value.toLowerCase();
    const filtered: Record<string, string[]> = {};
    for (const [group, perms] of Object.entries(props.permissionGroups)) {
        const matched = perms.filter((p: string) => p.toLowerCase().includes(q));
        if (matched.length > 0) filtered[group] = matched;
    }
    return filtered;
});

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

// --- Permission toggling ---
function togglePermission(permission: string, checked: boolean | 'indeterminate') {
    if (checked) {
        form.permissions.push(permission);
    } else {
        form.permissions = form.permissions.filter(p => p !== permission);
    }
}

function toggleGroup(group: string, checked: boolean | 'indeterminate') {
    const groupPerms = props.permissionGroups[group];
    if (checked) {
        const newPerms = new Set([...form.permissions, ...groupPerms]);
        form.permissions = [...newPerms];
    } else {
        form.permissions = form.permissions.filter(p => !groupPerms.includes(p));
    }
}

function isGroupChecked(group: string): boolean {
    return props.permissionGroups[group].every(p => form.permissions.includes(p));
}

function isGroupIndeterminate(group: string): boolean {
    const groupPerms = props.permissionGroups[group];
    const checkedCount = groupPerms.filter(p => form.permissions.includes(p)).length;
    return checkedCount > 0 && checkedCount < groupPerms.length;
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

        <div class="mt-6 max-w-4xl space-y-6">
            <!-- Super-admin notice -->
            <Alert v-if="isSuperAdmin">
                <ShieldCheck class="size-4" />
                <AlertDescription>
                    The super-admin role has full access to all features via system bypass. Permissions cannot be modified.
                </AlertDescription>
            </Alert>

            <form @submit.prevent="submit" class="space-y-6">
                <Card>
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

                    <p v-if="Object.keys(filteredPermissionGroups).length === 0" class="text-sm text-muted-foreground">
                        No permissions match "{{ searchQuery }}".
                    </p>

                    <Card v-for="(permissions, group) in filteredPermissionGroups" :key="group">
                        <CardHeader class="pb-3">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    :model-value="isGroupChecked(group as string) ? true : isGroupIndeterminate(group as string) ? 'indeterminate' : false"
                                    @update:model-value="(v: boolean | 'indeterminate') => toggleGroup(group as string, v)"
                                />
                                <component :is="getModuleIcon(group as string)" class="size-4 text-muted-foreground" />
                                <CardTitle class="text-base capitalize">{{ group }}</CardTitle>
                                <span class="text-xs text-muted-foreground">
                                    ({{ (props.permissionGroups[group as string] || []).filter((p: string) => form.permissions.includes(p)).length }}/{{ (props.permissionGroups[group as string] || []).length }})
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                <label
                                    v-for="permission in permissions"
                                    :key="permission"
                                    class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted"
                                    :class="form.permissions.includes(permission) ? 'bg-primary/5 ring-1 ring-primary/20' : ''"
                                >
                                    <Checkbox
                                        :model-value="form.permissions.includes(permission)"
                                        @update:model-value="(v: boolean | 'indeterminate') => togglePermission(permission, v)"
                                    />
                                    <span>{{ (permission as string).split('.').pop() }}</span>
                                </label>
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
