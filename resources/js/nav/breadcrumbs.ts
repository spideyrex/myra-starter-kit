import type { BreadcrumbItem } from '@/types';
import type { ServerCrumb } from '@/types/nav';

/** Static segments arrive as i18n keys, record titles as literals. */
export function hydrateCrumbs(crumbs: ServerCrumb[] | undefined, t: (key: string) => string): BreadcrumbItem[] {
    return (crumbs ?? []).map(crumb => ({
        label: crumb.labelKey ? t(crumb.labelKey) : (crumb.label ?? ''),
        href: crumb.href ?? undefined,
    }));
}
