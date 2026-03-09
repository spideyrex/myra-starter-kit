<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { ArrowLeft, GripVertical } from 'lucide-vue-next';
import { VueDraggable } from 'vue-draggable-plus';
import { toast } from 'vue-sonner';

const props = defineProps<{
    tasks: Array<{
        id: number;
        title: string;
        priority: string;
        sort_order: number;
    }>;
}>();

const localTasks = ref([...props.tasks]);

watch(() => props.tasks, (newTasks) => {
    localTasks.value = [...newTasks];
});

function handleReorder() {
    const ids = localTasks.value.map(t => t.id);
    toast.success('Order saved');
    router.post(route('admin.demo.reorder'), { ids }, {
        preserveState: true,
        preserveScroll: true,
    });
}

const priorityColors: Record<string, string> = {
    high: 'destructive',
    medium: 'secondary',
    low: 'outline',
};
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: 'Reordering' },
    ]">
        <Head title="Drag & Drop Reordering Demo" />

        <PageHeader title="Drag-and-Drop Reordering" description="Reorder table rows with drag-and-drop, persist to backend.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6">
            <Card>
                <CardHeader>
                    <CardTitle>Task List</CardTitle>
                    <CardDescription>Drag rows using the grip handle to reorder. Order is persisted on drop.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-10" />
                                    <TableHead class="w-16">#</TableHead>
                                    <TableHead>Task</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead class="text-right">Sort Order</TableHead>
                                </TableRow>
                            </TableHeader>
                            <VueDraggable
                                v-model="localTasks"
                                target="tbody"
                                handle=".drag-handle"
                                :animation="200"
                                ghost-class="opacity-50"
                                @end="handleReorder"
                            >
                                <TableBody>
                                    <TableRow
                                        v-for="(task, index) in localTasks"
                                        :key="task.id"
                                        class="transition-colors hover:bg-muted/50"
                                    >
                                        <TableCell class="w-10">
                                            <div class="drag-handle cursor-grab active:cursor-grabbing">
                                                <GripVertical class="size-4 text-muted-foreground" />
                                            </div>
                                        </TableCell>
                                        <TableCell class="font-mono text-xs text-muted-foreground">
                                            {{ task.id }}
                                        </TableCell>
                                        <TableCell class="font-medium">
                                            {{ task.title }}
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="(priorityColors[task.priority] || 'secondary') as any" class="capitalize">
                                                {{ task.priority }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-right font-mono text-xs text-muted-foreground">
                                            {{ index + 1 }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </VueDraggable>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
