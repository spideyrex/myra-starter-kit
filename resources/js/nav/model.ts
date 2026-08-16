import type { MyraNavGroupPayload, MyraNavItemPayload, NavGroupVm, NavItemVm } from '@/types/nav';
import { resolveNavIcon } from './icons';

type Translate = (key: string) => string;

export function hydrateNavItem(item: MyraNavItemPayload, t: Translate): NavItemVm {
    return {
        title: t(item.labelKey),
        href: item.href ?? null,
        icon: resolveNavIcon(item.icon),
        permission: item.permission ?? null,
        activePrefix: item.activePrefix ?? null,
        items: item.items?.length ? item.items.map(child => hydrateNavItem(child, t)) : undefined,
    };
}

/**
 * Server groups merge into a core group with the same RESOLVED label, otherwise
 * they are appended. With an empty server list this is the identity operation —
 * that is what keeps a live sidebar byte-identical.
 */
export function mergeServerNav(
    core: NavGroupVm[],
    server: MyraNavGroupPayload[],
    t: Translate,
): NavGroupVm[] {
    const merged = core.map(group => ({ ...group, items: [...group.items] }));

    for (const group of server ?? []) {
        const label = t(group.labelKey);
        const items = (group.items ?? []).map(item => hydrateNavItem(item, t));
        const target = merged.find(candidate => candidate.label === label);

        if (target) {
            target.items.push(...items);
        } else {
            merged.push({ label, items });
        }
    }

    return merged;
}

export function filterNavItems(items: any[], can: (ability: string) => boolean): any[] {
    return items
        .map(item => (item.items?.length ? { ...item, items: filterNavItems(item.items, can) } : item))
        .filter(item => (!item.permission || can(item.permission))
            && (item.items ? item.items.length > 0 || !!item.href : true));
}

export function filterNavGroups(groups: any[], can: (ability: string) => boolean): any[] {
    return groups
        .map(group => ({ ...group, items: filterNavItems(group.items, can) }))
        .filter(group => group.items.length > 0);
}

/** Self-or-descendant, tolerant of a null href. */
export function isNavItemActive(item: any, url: string, origin = 'http://localhost'): boolean {
    if (item.activePrefix) {
        return url.startsWith(item.activePrefix);
    }
    if (item.items?.length) {
        return item.items.some((child: any) => isNavItemActive(child, url, origin));
    }
    if (!item.href) {
        return false;
    }
    try {
        return url.startsWith(new URL(item.href, origin).pathname);
    } catch {
        return false;
    }
}

/** Flattened, navigable leaves — the command palette must reach cluster children. */
export function flattenNavItems(groups: any[]): any[] {
    return groups
        .flatMap(group => group.items.flatMap((item: any) => (item.items?.length ? item.items : [item])))
        .filter((item: any) => !!item.href);
}

/** The same flattening, but keeping the palette's group headings. */
export function paletteNavGroups(groups: any[]): NavGroupVm[] {
    return groups
        .map(group => ({
            label: group.label,
            items: group.items
                .flatMap((item: any) => (item.items?.length ? item.items : [item]))
                .filter((item: any) => !!item.href),
        }))
        .filter(group => group.items.length > 0);
}
