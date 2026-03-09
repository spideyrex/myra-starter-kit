<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useConfirm } from '@/composables/useConfirm';
import { ShieldCheck } from 'lucide-vue-next';

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

const totalPermissions = computed(() => Object.values(props.permissionGroups).flat().length);
const selectedCount = computed(() => form.permissions.length);

const { confirm } = useConfirm();

async function submit() {
    if (isEditing.value) {
        const confirmed = await confirm({ title: 'Save Role Changes', description: 'Are you sure you want to update this role and its permissions?', confirmText: 'Save' });
        if (confirmed) form.put(route('admin.roles.update', props.role!.id));
    } else {
        const confirmed = await confirm({ title: 'Create Role', description: 'Are you sure you want to create this role?', confirmText: 'Create' });
        if (confirmed) form.post(route('admin.roles.store'));
    }
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'User Management' }, { label: 'Roles', href: route('admin.roles.index') }, { label: isEditing ? 'Edit' : 'Create' }]">
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
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-muted-foreground">{{ selectedCount }} of {{ totalPermissions }} permissions selected</p>
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" size="sm" @click="selectAll">Select All</Button>
                            <Button type="button" variant="outline" size="sm" @click="deselectAll">Deselect All</Button>
                        </div>
                    </div>

                    <Card v-for="(permissions, group) in permissionGroups" :key="group">
                        <CardHeader class="pb-3">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    :model-value="isGroupChecked(group as string) ? true : isGroupIndeterminate(group as string) ? 'indeterminate' : false"
                                    @update:model-value="(v: boolean | 'indeterminate') => toggleGroup(group as string, v)"
                                />
                                <CardTitle class="text-base capitalize">{{ group }}</CardTitle>
                                <span class="text-xs text-muted-foreground">
                                    ({{ permissions.filter((p: string) => form.permissions.includes(p)).length }}/{{ permissions.length }})
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                <label v-for="permission in permissions" :key="permission" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-muted">
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
