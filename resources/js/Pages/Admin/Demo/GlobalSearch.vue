<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    ArrowLeft,
    Search,
    Command,
    Keyboard,
    Zap,
    Users,
    Shield,
    Activity,
    Info,
} from 'lucide-vue-next';

function openCommandPalette() {
    // Dispatch keyboard event to trigger Cmd+K
    document.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'k',
        metaKey: true,
        ctrlKey: navigator.platform.includes('Mac') ? false : true,
    }));
}

const features = [
    {
        icon: Search,
        title: 'Live Search',
        description: 'Debounced (300ms) API search across all resources as you type.',
    },
    {
        icon: Zap,
        title: 'Grouped Results',
        description: 'Results organized by resource type — Users, Roles, Activity Log.',
    },
    {
        icon: Keyboard,
        title: 'Keyboard-First',
        description: 'Open with Cmd+K (Mac) or Ctrl+K (Windows/Linux). Navigate with arrow keys.',
    },
    {
        icon: Shield,
        title: 'Permission-Aware',
        description: 'Only searches resources the current user has permission to access.',
    },
];

const searchableResources = [
    { icon: Users, name: 'Users', fields: 'Name, Email' },
    { icon: Shield, name: 'Roles', fields: 'Role Name' },
    { icon: Activity, name: 'Activity Log', fields: 'Description, Subject' },
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo', href: route('admin.demo.index') }, { label: 'Global Search' }]">
        <Head title="Global Search Demo" />

        <PageHeader title="Global Search" description="Command palette (Cmd+K) with debounced API search.">
            <template #actions>
                <Button @click="openCommandPalette">
                    <Command class="mr-2 size-4" />
                    Open Search (Cmd+K)
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6">
            <Alert>
                <Info class="size-4" />
                <AlertDescription>
                    Press <kbd class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs">Cmd+K</kbd> (Mac) or
                    <kbd class="mx-1 rounded bg-muted px-1.5 py-0.5 font-mono text-xs">Ctrl+K</kbd> (Windows/Linux)
                    to open the global search at any time. Try searching for a user name or role.
                </AlertDescription>
            </Alert>

            <!-- Features Grid -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card v-for="feature in features" :key="feature.title">
                    <CardContent class="pt-6">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <component :is="feature.icon" class="size-5" />
                        </div>
                        <h3 class="mt-3 font-semibold">{{ feature.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ feature.description }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Searchable Resources -->
            <Card>
                <CardHeader>
                    <CardTitle>Searchable Resources</CardTitle>
                    <CardDescription>The global search queries these resources (limited to 5 results per group).</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="resource in searchableResources"
                            :key="resource.name"
                            class="flex items-center gap-3 rounded-lg border p-3"
                        >
                            <div class="flex size-9 items-center justify-center rounded-md bg-muted">
                                <component :is="resource.icon" class="size-4 text-muted-foreground" />
                            </div>
                            <div class="flex-1">
                                <p class="font-medium">{{ resource.name }}</p>
                                <p class="text-sm text-muted-foreground">Searches: {{ resource.fields }}</p>
                            </div>
                            <Badge variant="secondary">5 max</Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- How It Works -->
            <Card>
                <CardHeader>
                    <CardTitle>How It Works</CardTitle>
                    <CardDescription>Architecture overview of the global search feature.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4 text-sm">
                        <div class="rounded-lg bg-muted p-4 font-mono text-xs leading-relaxed">
                            <p class="text-muted-foreground">// Frontend: useGlobalSearch composable</p>
                            <p>const { query, results, loading, hasSearched } = useGlobalSearch();</p>
                            <p class="mt-2 text-muted-foreground">// Debounced 300ms, auto-fetches from:</p>
                            <p>GET /admin/search?q=query</p>
                            <p class="mt-2 text-muted-foreground">// Returns grouped results:</p>
                            <p>{{ '{ results: [{ group: "Users", items: [...] }, ...] }' }}</p>
                        </div>
                        <div class="rounded-lg bg-muted p-4 font-mono text-xs leading-relaxed">
                            <p class="text-muted-foreground">// Backend: SearchController</p>
                            <p>// Searches Users, Roles, Activity Log</p>
                            <p>// Checks permissions before each query</p>
                            <p>// Returns id, title, description, url per result</p>
                            <p>// Minimum 2 characters required</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
