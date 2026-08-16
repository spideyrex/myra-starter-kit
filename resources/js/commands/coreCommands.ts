import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { setLocale, SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n';
import { resolveNavIcon } from '@/nav/icons';
import { useCommandScope, type Command } from '@/composables/useCommandRegistry';
import type { MyraNavGroupPayload, MyraNavItemPayload } from '@/types/nav';

export interface CoreCommandOptions {
    toggleTheme: () => void;
    /** Optional — the shortcut sheet command hides itself when nothing handles it. */
    onShortcuts?: () => void;
}

/** Stable id so a host that already renders a nav link can dedupe against it. */
export function navigateCommandId(href: string): string {
    return `navigate:${href}`;
}

function flattenNav(groups: MyraNavGroupPayload[]): Array<{ group: string; item: MyraNavItemPayload }> {
    const out: Array<{ group: string; item: MyraNavItemPayload }> = [];

    for (const group of groups ?? []) {
        for (const item of group.items ?? []) {
            const children = item.items ?? [];
            if (children.length > 0) {
                for (const child of children) {
                    if (child.href) out.push({ group: group.labelKey, item: child });
                }
                continue;
            }
            if (item.href) out.push({ group: group.labelKey, item });
        }
    }

    return out;
}

/**
 * Every core command, derived from props that are ALREADY on the page — the
 * server nav (permission-filtered by NavRegistry::forUser) and, where the page
 * ships it, the demo registry. Zero requests, zero new payload.
 *
 * Deliberately icon-light: only resolveNavIcon is used, because it is already
 * in the main chunk. Pulling the gallery's 27-icon map in here would put it in
 * the entry bundle for every page.
 */
export function coreCommands(options: CoreCommandOptions): Command[] {
    const page = usePage();
    const props = page.props as any;

    const commands: Command[] = [];

    for (const { group, item } of flattenNav(props.myraNav ?? [])) {
        commands.push({
            id: navigateCommandId(item.href!),
            titleKey: item.labelKey,
            groupKey: 'gallery.commands.group.navigate',
            icon: resolveNavIcon(item.icon),
            keywords: [group],
            permission: item.permission ?? undefined,
            run: () => router.visit(item.href!),
        });
    }

    for (const demo of (props.demos ?? []) as Array<{ key: string; titleKey: string; route: string }>) {
        let href: string | null = null;
        try {
            href = (globalThis as any).route(demo.route) as string;
        } catch {
            href = null;
        }
        if (!href) continue;

        commands.push({
            id: `demo:${demo.key}`,
            titleKey: demo.titleKey,
            groupKey: 'gallery.commands.group.demo',
            keywords: ['demo', demo.key],
            run: () => router.visit(href!),
        });
    }

    commands.push({
        id: 'theme.toggle',
        titleKey: 'gallery.commands.themeToggle',
        groupKey: 'gallery.commands.group.theme',
        keywords: ['dark', 'light', 'theme'],
        run: () => options.toggleTheme(),
    });

    for (const locale of SUPPORTED_LOCALES) {
        commands.push({
            id: `locale.${locale.code}`,
            titleKey: `gallery.commands.locale.${locale.code}`,
            groupKey: 'gallery.commands.group.theme',
            keywords: ['language', 'locale', locale.label],
            run: () => {
                void setLocale(locale.code as SupportedLocale);
            },
        });
    }

    commands.push({
        id: 'help.shortcuts',
        titleKey: 'gallery.commands.shortcuts',
        groupKey: 'gallery.commands.group.action',
        shortcut: '?',
        keywords: ['help', 'keyboard'],
        when: () => typeof options.onShortcuts === 'function',
        run: () => options.onShortcuts?.(),
    });

    return commands;
}

/** Registers the core set for the calling scope; unregisters with it. */
export function useCoreCommands(options: CoreCommandOptions): void {
    useCommandScope(computed(() => coreCommands(options)));
}
