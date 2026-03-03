<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { KeyRound, Shield } from 'lucide-vue-next';

const props = defineProps<{
    permissionGroups: Record<string, Array<{
        id: number;
        name: string;
        roles: string[];
        created_at: string;
    }>>;
}>();

function totalPermissions(): number {
    return Object.values(props.permissionGroups).reduce((sum, g) => sum + g.length, 0);
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'User Management' }, { label: 'Permissions' }]">
        <Head title="Permissions" />

        <PageHeader title="Permissions" :description="`${totalPermissions()} permissions across ${Object.keys(permissionGroups).length} modules. Permissions are system-defined and assigned via roles.`" />

        <div class="mt-6 space-y-6">
            <Card v-for="(permissions, group) in permissionGroups" :key="group">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base capitalize">
                        <KeyRound class="size-4" />
                        {{ group }}
                        <Badge variant="secondary">{{ permissions.length }}</Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Permission</TableHead>
                                <TableHead>Assigned To Roles</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="permission in permissions" :key="permission.id">
                                <TableCell class="font-medium">
                                    <code class="rounded bg-muted px-1.5 py-0.5 text-sm">{{ permission.name }}</code>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="role in permission.roles"
                                            :key="role"
                                            :variant="role === 'admin' ? 'default' : 'secondary'"
                                            class="text-xs"
                                        >
                                            <Shield class="mr-1 size-3" />
                                            {{ role }}
                                        </Badge>
                                        <span v-if="permission.roles.length === 0" class="text-xs text-muted-foreground">Not assigned</span>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
