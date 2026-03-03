<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import type { PageProps, BreadcrumbItem } from '@/types';
import { usePermissions } from '@/composables/usePermissions';
import {
    SidebarProvider,
    Sidebar,
    SidebarHeader,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuItem,
    SidebarMenuButton,
    SidebarMenuSub,
    SidebarMenuSubItem,
    SidebarMenuSubButton,
    SidebarInset,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Breadcrumb,
    BreadcrumbItem as BreadcrumbItemComponent,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Toaster } from '@/components/ui/sonner';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { useFlashToasts } from '@/composables/useFlashToasts';
import {
    LayoutDashboard,
    Users,
    Shield,
    KeyRound,
    Image,
    Mail,
    FileText,
    MailCheck,
    Settings,
    Activity,
    Database,
    HeartPulse,
    Key,
    ChevronRight,
    Moon,
    Sun,
    LogOut,
    User,
    Bell,
    AlertTriangle,
} from 'lucide-vue-next';

defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const isImpersonating = computed(() => page.props.impersonating);
const impersonatorName = computed(() => page.props.impersonatorName);
const { can } = usePermissions();

useFlashToasts();

const isDark = ref(document.documentElement.classList.contains('dark'));

function toggleDark() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
}

// Restore theme on load
if (localStorage.getItem('theme') === 'dark' ||
    (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
    isDark.value = true;
}

function logout() {
    router.post(route('logout'));
}

function stopImpersonating() {
    router.post(route('admin.stop-impersonate'));
}

function initials(name: string) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

const navGroups = computed(() => [
    {
        label: 'Main',
        items: [
            { title: 'Dashboard', href: route('dashboard'), icon: LayoutDashboard, permission: null },
        ],
    },
    {
        label: 'User Management',
        items: [
            { title: 'Users', href: route('admin.users.index'), icon: Users, permission: 'users.view' },
            { title: 'Roles', href: route('admin.roles.index'), icon: Shield, permission: 'roles.view' },
            { title: 'Permissions', href: route('admin.permissions.index'), icon: KeyRound, permission: 'permissions.view' },
        ],
    },
    {
        label: 'Content',
        items: [
            { title: 'Media Manager', href: route('admin.media.index'), icon: Image, permission: 'media.view' },
        ],
    },
    {
        label: 'Email',
        items: [
            { title: 'Templates', href: route('admin.email-templates.index'), icon: FileText, permission: 'email.view' },
            { title: 'Email Log', href: route('admin.email-logs.index'), icon: Mail, permission: 'email.view' },
            { title: 'Email Settings', href: route('admin.email-settings.index'), icon: MailCheck, permission: 'settings.view' },
        ],
    },
    {
        label: 'System',
        items: [
            { title: 'General Settings', href: route('admin.settings.index'), icon: Settings, permission: 'settings.view' },
            { title: 'Activity Log', href: route('admin.activity-logs.index'), icon: Activity, permission: 'activity-log.view' },
            { title: 'Backups', href: route('admin.backups.index'), icon: Database, permission: 'backups.view' },
            { title: 'System Health', href: route('admin.system-health.index'), icon: HeartPulse, permission: 'system-health.view' },
            { title: 'API Tokens', href: route('admin.api-tokens.index'), icon: Key, permission: 'api-tokens.view' },
            { title: 'Notifications', href: route('admin.notifications.index'), icon: Bell, permission: 'notifications.view' },
        ],
    },
]);

const filteredNavGroups = computed(() =>
    navGroups.value
        .map(group => ({
            ...group,
            items: group.items.filter(item => !item.permission || can(item.permission)),
        }))
        .filter(group => group.items.length > 0)
);

function isActive(href: string) {
    return page.url.startsWith(new URL(href).pathname);
}

// Command palette
const commandOpen = ref(false);

function handleKeydown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        commandOpen.value = !commandOpen.value;
    }
}

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));

function runCommand(callback: () => void) {
    commandOpen.value = false;
    callback();
}
</script>

<template>
    <SidebarProvider>
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child>
                            <Link :href="route('dashboard')">
                                <div v-if="page.props.siteSettings?.logo_url" class="flex aspect-square size-8 items-center justify-center rounded-lg overflow-hidden">
                                    <img :src="page.props.siteSettings.logo_url" alt="Logo" class="size-full object-contain" />
                                </div>
                                <div v-else class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <LayoutDashboard class="size-4" />
                                </div>
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ page.props.siteSettings?.site_name || 'Admin' }}</span>
                                    <span class="truncate text-xs text-muted-foreground">Dashboard</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup v-for="group in filteredNavGroups" :key="group.label">
                    <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                                <SidebarMenuButton as-child :is-active="isActive(item.href)">
                                    <Link :href="item.href">
                                        <component :is="item.icon" class="size-4" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <SidebarMenuButton size="lg">
                                    <Avatar class="size-8">
                                        <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                                        <AvatarFallback>{{ initials(user.name) }}</AvatarFallback>
                                    </Avatar>
                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ user.name }}</span>
                                        <span class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
                                    </div>
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent side="top" class="w-56">
                                <DropdownMenuItem as-child>
                                    <Link :href="route('profile.edit')">
                                        <User class="mr-2 size-4" />
                                        Profile
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="toggleDark">
                                    <Moon v-if="!isDark" class="mr-2 size-4" />
                                    <Sun v-else class="mr-2 size-4" />
                                    {{ isDark ? 'Light Mode' : 'Dark Mode' }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="logout">
                                    <LogOut class="mr-2 size-4" />
                                    Log Out
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>

        <SidebarInset>
            <!-- Impersonation Banner -->
            <div v-if="isImpersonating" class="flex items-center justify-center gap-3 bg-blue-900 px-4 py-2 text-sm font-medium text-white">
                <AlertTriangle class="size-4 shrink-0" />
                <span>You are impersonating <strong>{{ user.name }}</strong></span>
                <Button size="sm" variant="secondary" class="ml-2 h-7 text-xs" @click="stopImpersonating">
                    Stop Impersonating
                </Button>
            </div>

            <header class="flex h-16 shrink-0 items-center gap-2 border-b px-4">
                <SidebarTrigger class="-ml-1" />
                <Separator orientation="vertical" class="mr-2 h-4" />
                <Breadcrumb v-if="breadcrumbs?.length">
                    <BreadcrumbList>
                        <template v-for="(crumb, index) in breadcrumbs" :key="index">
                            <BreadcrumbItemComponent>
                                <BreadcrumbLink v-if="crumb.href" as-child>
                                    <Link :href="crumb.href">{{ crumb.label }}</Link>
                                </BreadcrumbLink>
                                <BreadcrumbPage v-else>{{ crumb.label }}</BreadcrumbPage>
                            </BreadcrumbItemComponent>
                            <BreadcrumbSeparator v-if="index < breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
                <div class="ml-auto flex items-center gap-2">
                    <NotificationBell />
                </div>
            </header>

            <main class="flex-1 p-4 md:p-6">
                <div :key="page.url" class="animate-fade-in-up">
                    <slot />
                </div>
            </main>
        </SidebarInset>
    </SidebarProvider>

    <!-- Command Palette -->
    <CommandDialog v-model:open="commandOpen">
        <CommandInput placeholder="Type a command or search..." />
        <CommandList>
            <CommandEmpty>No results found.</CommandEmpty>
            <CommandGroup v-for="group in filteredNavGroups" :key="group.label" :heading="group.label">
                <CommandItem
                    v-for="item in group.items"
                    :key="item.title"
                    :value="item.title"
                    @select="runCommand(() => router.visit(item.href))"
                >
                    <component :is="item.icon" class="mr-2 size-4" />
                    {{ item.title }}
                </CommandItem>
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup heading="Actions">
                <CommandItem value="Profile" @select="runCommand(() => router.visit(route('profile.edit')))">
                    <User class="mr-2 size-4" />
                    Profile
                </CommandItem>
                <CommandItem value="Toggle Dark Mode" @select="runCommand(toggleDark)">
                    <Moon v-if="!isDark" class="mr-2 size-4" />
                    <Sun v-else class="mr-2 size-4" />
                    {{ isDark ? 'Light Mode' : 'Dark Mode' }}
                </CommandItem>
                <CommandItem value="Log Out" @select="runCommand(logout)">
                    <LogOut class="mr-2 size-4" />
                    Log Out
                </CommandItem>
            </CommandGroup>
        </CommandList>
    </CommandDialog>

    <Toaster />
    <ConfirmDialog />
</template>
