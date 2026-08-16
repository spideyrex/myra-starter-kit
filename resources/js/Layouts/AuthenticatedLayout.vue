<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import type { PageProps, BreadcrumbItem } from '@/types';
import type { MyraNavGroupPayload } from '@/types/nav';
import {
    filterNavGroups,
    isNavItemActive,
    mergeServerNav,
    paletteNavGroups,
} from '@/nav/model';
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
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { useI18n } from 'vue-i18n';
import { useFlashToasts } from '@/composables/useFlashToasts';
import { useGlobalSearch } from '@/composables/useGlobalSearch';
import { useEcho } from '@/composables/useEcho';
import { useFirebaseMessaging } from '@/composables/useFirebaseMessaging';
import { useThemeColors } from '@/composables/useThemeColors';
// >>> MYRA v2.6 [C] START
import { useBrand } from '@/composables/useBrand';
import BrandMark from '@/components/brand/BrandMark.vue';
// <<< MYRA v2.6 [C] END
import {
    LayoutDashboard,
    Users,
    Shield,
    Search as SearchIcon,

    Image,
    Mail,
    FileText,
    Newspaper,
    FolderOpen,
    MailCheck,
    Settings,
    Activity,
    Database,
    HeartPulse,
    Key,
    ChevronRight,
    ChevronsLeft,
    Moon,
    Sun,
    LogOut,
    User,
    Bell,
    Smartphone,
    AlertTriangle,
    FlaskConical,
    LayoutGrid,
    LayoutTemplate,
    BarChart3,
    // >>> MYRA v2.6 [C] START
    Palette,
    // <<< MYRA v2.6 [C] END
} from 'lucide-vue-next';

defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const isImpersonating = computed(() => page.props.impersonating);
const impersonatorName = computed(() => page.props.impersonatorName);
const { can } = usePermissions();
// >>> MYRA v2.6 [C] START
const { brand } = useBrand();
const logoPosition = computed(() => brand.value.logo_position || 'header');
// <<< MYRA v2.6 [C] END

useFlashToasts();
useEcho();
useFirebaseMessaging();
useThemeColors();
// >>> MYRA v2.5 [D] START
import { useServiceWorker } from '@/composables/useServiceWorker';
import OfflineBanner from '@/components/admin/OfflineBanner.vue';

// Inert unless myra.pwa.enabled is true: it registers nothing and, with the
// flag off, actively unregisters a stale myra-sw.js and purges its caches.
const sw = useServiceWorker();
// <<< MYRA v2.5 [D] END

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

const { t } = useI18n();

const coreNavGroups = computed(() => [
    {
        label: t('navGroups.main'),
        items: [
            { title: t('nav.dashboard'), href: route('dashboard'), icon: LayoutDashboard, permission: null },
        ],
    },
    {
        label: t('navGroups.userManagement'),
        items: [
            { title: t('nav.users'), href: route('admin.users.index'), icon: Users, permission: 'users.view' },
            { title: t('nav.roles'), href: route('admin.roles.index'), icon: Shield, permission: 'roles.view' },
        ],
    },
    {
        label: t('navGroups.content'),
        items: [
            { title: t('nav.pages'), href: route('admin.pages.index'), icon: FileText, permission: 'pages.view' },
            { title: t('nav.articles'), href: route('admin.articles.index'), icon: Newspaper, permission: 'articles.view' },
            { title: t('nav.categories'), href: route('admin.categories.index'), icon: FolderOpen, permission: 'categories.view' },
            { title: t('nav.media'), href: route('admin.media.index'), icon: Image, permission: 'media.view' },
        ],
    },
    {
        label: t('navGroups.email'),
        items: [
            { title: t('nav.templates'), href: route('admin.email-templates.index'), icon: FileText, permission: 'email.view' },
            { title: t('nav.emailLog'), href: route('admin.email-logs.index'), icon: Mail, permission: 'email.view' },
            { title: t('nav.emailSettings'), href: route('admin.email-settings.index'), icon: MailCheck, permission: 'settings.view' },
        ],
    },
    {
        label: t('navGroups.pushNotifications'),
        items: [
            { title: t('nav.firebaseSettings'), href: route('admin.firebase-settings.index'), icon: Smartphone, permission: 'firebase.view' },
        ],
    },
    // >>> MYRA v2.3 [B] START
    {
        label: t('navGroups.reports'),
        items: [
            { title: t('nav.reports'), href: route('admin.reports.index'), icon: BarChart3, permission: 'reports.view' },
            // The schedules entry ships with the report-delivery bundle, which
            // registers admin.report-schedules.index.
        ],
    },
    // <<< MYRA v2.3 [B] END
    {
        label: t('navGroups.system'),
        items: [
            { title: t('nav.generalSettings'), href: route('admin.settings.index'), icon: Settings, permission: 'settings.view' },
            // >>> MYRA v2.6 [C] START
            ...((route as any)().has('admin.brand.index')
                ? [{ title: t('brand.title'), href: route('admin.brand.index'), icon: Palette, permission: 'brand.view' }]
                : []),
            // <<< MYRA v2.6 [C] END
            { title: t('nav.activityLog'), href: route('admin.activity-logs.index'), icon: Activity, permission: 'activity-log.view' },
            { title: t('nav.backups'), href: route('admin.backups.index'), icon: Database, permission: 'backups.view' },
            { title: t('nav.systemHealth'), href: route('admin.system-health.index'), icon: HeartPulse, permission: 'system-health.view' },
            { title: t('nav.apiTokens'), href: route('admin.api-tokens.index'), icon: Key, permission: 'api-tokens.view' },
            { title: t('nav.notifications'), href: route('admin.notifications.index'), icon: Bell, permission: 'notifications.view' },
            // >>> MYRA v2.6 [D] START
            { title: t('landing.title'), href: route('admin.landing.index'), icon: LayoutTemplate, permission: 'settings.edit' },
            // <<< MYRA v2.6 [D] END
        ],
    },
    {
        label: t('navGroups.demo'),
        items: [
            { title: t('nav.featureDemos'), href: route('admin.demo.index'), icon: FlaskConical, permission: null },
        ],
    },
    {
        // Scaffolded pages (make:myra-* commands insert items at the marker below).
        label: 'Custom',
        items: [
            /* myra:nav */
        ],
    },
]);

// >>> MYRA v2.4 [B] START
// Server-contributed navigation. `[]` when nothing is registered, and
// mergeServerNav([]) is the identity operation on the groups above.
const serverNav = computed<MyraNavGroupPayload[]>(() => (page.props as any).myraNav ?? []);

const translate = (key: string) => t(key);
const navGroups = computed(() => mergeServerNav(coreNavGroups.value as any, serverNav.value, translate));

const filteredNavGroups = computed(() => filterNavGroups(navGroups.value, can));

function isActive(item: any) {
    return isNavItemActive(item, page.url, window.location.origin);
}

/** Cluster children stay reachable from the ⌘K palette. */
const paletteGroups = computed(() => paletteNavGroups(filteredNavGroups.value));
// <<< MYRA v2.4 [B] END

// Command palette
const commandOpen = ref(false);
const { query: searchQuery, results: searchResults, hasSearched: hasSearchResults, reset: resetSearch } = useGlobalSearch();

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
    resetSearch();
    callback();
}

function handleCommandInput(value: string) {
    searchQuery.value = value;
}

// >>> MYRA v2.5 [C] START
import { Kbd } from '@/components/ui/kbd';
import { useCommandRegistry, type Command } from '@/composables/useCommandRegistry';
import { navigateCommandId, useCoreCommands } from '@/commands/coreCommands';

useCoreCommands({ toggleTheme: toggleDark });

const { commands: registeredCommands } = useCommandRegistry();

/**
 * This dialog already renders every navigable nav leaf and the dark-mode
 * action, so the same command from the registry would appear twice. Dedupe
 * HERE, in the host that owns the duplicate — the registry itself stays
 * complete for other hosts (the standalone CommandPalette has no nav groups).
 */
const alreadyRendered = computed(() => new Set([
    'theme.toggle',
    ...paletteGroups.value.flatMap((group: any) => group.items.map((item: any) => navigateCommandId(String(item.href)))),
]));

const paletteCommandGroups = computed(() => {
    const grouped = new Map<string, Command[]>();

    for (const command of registeredCommands.value) {
        if (alreadyRendered.value.has(command.id)) continue;
        if (!grouped.has(command.groupKey)) grouped.set(command.groupKey, []);
        grouped.get(command.groupKey)!.push(command);
    }

    return [...grouped.entries()].map(([groupKey, items]) => ({ groupKey, items }));
});

function runRegistered(command: Command) {
    commandOpen.value = false;
    resetSearch();
    void command.run({ close: () => { commandOpen.value = false; } });
}
// <<< MYRA v2.5 [C] END
</script>

<template>
    <SidebarProvider>
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <!-- >>> MYRA v2.6 [C] START -->
                        <SidebarMenuButton v-if="logoPosition === 'sidebar'" size="lg" as-child class="hover:bg-transparent hover:text-sidebar-foreground active:bg-transparent">
                            <Link :href="route('dashboard')">
                                <BrandMark :subtitle="t('nav.dashboard')" />
                            </Link>
                        </SidebarMenuButton>
                        <!-- <<< MYRA v2.6 [C] END -->
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup v-for="group in filteredNavGroups" :key="group.label">
                    <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                                <!-- >>> MYRA v2.4 [B] START — cluster (one level of children) -->
                                <template v-if="item.items?.length">
                                    <Collapsible
                                        :default-open="isActive(item)"
                                        class="group/collapsible group-data-[collapsible=icon]:hidden"
                                    >
                                        <CollapsibleTrigger as-child>
                                            <SidebarMenuButton>
                                                <component :is="item.icon" class="size-4" />
                                                <span>{{ item.title }}</span>
                                                <ChevronRight class="ml-auto size-4 transition-transform group-data-[state=open]/collapsible:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                <SidebarMenuSubItem v-for="child in item.items" :key="child.title">
                                                    <SidebarMenuSubButton as-child :is-active="isActive(child)">
                                                        <Link :href="child.href!">{{ child.title }}</Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </Collapsible>
                                    <!-- Icon mode: the same children, reachable from a menu. -->
                                    <div class="hidden group-data-[collapsible=icon]:block">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <SidebarMenuButton :is-active="isActive(item)" :aria-label="item.title">
                                                    <component :is="item.icon" class="size-4" />
                                                    <span>{{ item.title }}</span>
                                                </SidebarMenuButton>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent side="right" align="start" class="min-w-48">
                                                <DropdownMenuItem v-for="child in item.items" :key="child.title" as-child>
                                                    <Link :href="child.href!">{{ child.title }}</Link>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </template>
                                <SidebarMenuButton v-else as-child :is-active="isActive(item)">
                                    <Link :href="item.href">
                                        <component :is="item.icon" class="size-4" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                                <!-- <<< MYRA v2.4 [B] END -->
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem class="hidden group-data-[collapsible=icon]:flex justify-center">
                        <SidebarTrigger class="text-sidebar-foreground/50 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                            <ChevronsLeft class="size-4 rotate-180" />
                        </SidebarTrigger>
                    </SidebarMenuItem>
                    <SidebarMenuItem class="flex items-center gap-1">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <SidebarMenuButton size="lg" class="min-w-0 flex-1">
                                    <Avatar class="size-8 shrink-0">
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
                                    <Link :href="route('profile.show')">
                                        <User class="mr-2 size-4" />
                                        {{ $t('common.profile') }}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link :href="route('profile.security')">
                                        <Shield class="mr-2 size-4" />
                                        {{ $t('common.security') }}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="toggleDark">
                                    <Moon v-if="!isDark" class="mr-2 size-4" />
                                    <Sun v-else class="mr-2 size-4" />
                                    {{ isDark ? $t('common.lightMode') : $t('common.darkMode') }}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="logout">
                                    <LogOut class="mr-2 size-4" />
                                    {{ $t('common.logout') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <SidebarTrigger class="shrink-0 text-sidebar-foreground/50 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground group-data-[collapsible=icon]:hidden">
                            <ChevronsLeft class="size-4" />
                        </SidebarTrigger>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>

        <SidebarInset>
            <!-- Impersonation Banner -->
            <!-- >>> MYRA v2.6 [C] START — brand token, not a literal blue -->
            <div v-if="isImpersonating" class="flex flex-wrap items-center justify-center gap-2 bg-primary px-4 py-2 text-xs font-medium text-primary-foreground sm:gap-3 sm:text-sm">
            <!-- <<< MYRA v2.6 [C] END -->
                <AlertTriangle class="size-4 shrink-0" />
                <span>Impersonating <strong>{{ user.name }}</strong></span>
                <Button size="sm" variant="secondary" class="h-7 text-xs" @click="stopImpersonating">
                    Stop
                </Button>
            </div>

            <header class="sticky top-0 z-10 relative flex h-14 shrink-0 items-center border-b bg-background px-3 sm:h-16 sm:px-4">
                <SidebarTrigger class="-ml-1 md:hidden" />
                <!-- >>> MYRA v2.6 [C] START -->
                <Link v-if="logoPosition === 'header'" :href="route('dashboard')" class="absolute left-1/2 -translate-x-1/2 flex items-center gap-2">
                    <BrandMark size="sm" />
                </Link>
                <!-- <<< MYRA v2.6 [C] END -->
                <div class="ml-auto flex items-center gap-2">
                    <TeamSwitcher v-if="(page.props.teams as any)?.length > 0" />
                    <LanguageSwitcher />
                    <NotificationBell />
                </div>
            </header>

            <main class="flex-1 p-3 sm:p-4 md:p-6">
                <div :key="page.url" class="animate-fade-in-up">
                    <Breadcrumb v-if="breadcrumbs?.length" class="mb-4">
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
                    <slot />
                </div>
            </main>
        </SidebarInset>
    </SidebarProvider>

    <!-- Command Palette with Global Search -->
    <CommandDialog v-model:open="commandOpen">
        <CommandInput placeholder="Type a command or search..." @input="handleCommandInput(($event.target as HTMLInputElement).value)" />
        <CommandList>
            <CommandEmpty>No results found.</CommandEmpty>

            <!-- Search results when query is 2+ chars -->
            <template v-if="hasSearchResults && searchResults.length > 0">
                <CommandGroup v-for="group in searchResults" :key="group.group" :heading="group.group">
                    <CommandItem
                        v-for="item in group.items"
                        :key="item.id"
                        :value="`search-${group.group}-${item.id}-${item.title}`"
                        @select="runCommand(() => router.visit(item.url))"
                    >
                        <SearchIcon class="mr-2 size-4 text-muted-foreground" />
                        <div>
                            <span>{{ item.title }}</span>
                            <span class="ml-2 text-xs text-muted-foreground">{{ item.description }}</span>
                        </div>
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
            </template>

            <!-- Navigation (shown when empty query or alongside results) -->
            <CommandGroup v-for="group in paletteGroups" :key="group.label" :heading="group.label">
                <CommandItem
                    v-for="item in group.items"
                    :key="item.title"
                    :value="item.title"
                    @select="runCommand(() => router.visit(item.href!))"
                >
                    <component :is="item.icon" class="mr-2 size-4" />
                    {{ item.title }}
                </CommandItem>
            </CommandGroup>
            <CommandSeparator />
            <!-- >>> MYRA v2.5 [C] START -->
            <template v-for="group in paletteCommandGroups" :key="group.groupKey">
                <CommandGroup :heading="$t(group.groupKey)">
                    <CommandItem
                        v-for="cmd in group.items"
                        :key="cmd.id"
                        :value="`myra-cmd-${cmd.id}`"
                        @select="runRegistered(cmd)"
                    >
                        <component :is="cmd.icon" v-if="cmd.icon" class="mr-2 size-4" />
                        {{ $t(cmd.titleKey) }}
                        <Kbd v-if="cmd.shortcut" class="ml-auto">{{ cmd.shortcut }}</Kbd>
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
            </template>
            <!-- <<< MYRA v2.5 [C] END -->
            <CommandGroup :heading="$t('common.actions')">
                <CommandItem value="Profile" @select="runCommand(() => router.visit(route('profile.show')))">
                    <User class="mr-2 size-4" />
                    {{ $t('common.profile') }}
                </CommandItem>
                <CommandItem value="Toggle Dark Mode" @select="runCommand(toggleDark)">
                    <Moon v-if="!isDark" class="mr-2 size-4" />
                    <Sun v-else class="mr-2 size-4" />
                    {{ isDark ? $t('common.lightMode') : $t('common.darkMode') }}
                </CommandItem>
                <CommandItem value="Log Out" @select="runCommand(logout)">
                    <LogOut class="mr-2 size-4" />
                    {{ $t('common.logout') }}
                </CommandItem>
            </CommandGroup>
        </CommandList>
    </CommandDialog>

    <Toaster />
    <ConfirmDialog />
    <!-- >>> MYRA v2.5 [D] START -->
    <OfflineBanner v-if="sw.offline.value" />
    <!-- <<< MYRA v2.5 [D] END -->
</template>
