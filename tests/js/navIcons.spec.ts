import { describe, expect, it } from 'vitest';
import { NAV_ICONS, resolveNavIcon } from '@/nav/icons';
import { hydrateNavItem } from '@/nav/model';

describe('nav icon allowlist', () => {
    it('resolves a known name to its component', () => {
        expect(resolveNavIcon('GraduationCap')).toBe(NAV_ICONS.GraduationCap);
        expect(resolveNavIcon('BookOpen')).toBe(NAV_ICONS.BookOpen);
    });

    it('falls back to LayoutGrid for an unknown name instead of throwing', () => {
        expect(() => resolveNavIcon('NoSuchIcon')).not.toThrow();
        expect(resolveNavIcon('NoSuchIcon')).toBe(NAV_ICONS.LayoutGrid);
    });

    it('falls back for null, undefined and an empty string', () => {
        expect(resolveNavIcon(null)).toBe(NAV_ICONS.LayoutGrid);
        expect(resolveNavIcon(undefined)).toBe(NAV_ICONS.LayoutGrid);
        expect(resolveNavIcon('')).toBe(NAV_ICONS.LayoutGrid);
    });

    it('cannot be used to reach an inherited Object property', () => {
        expect(resolveNavIcon('constructor')).toBe(NAV_ICONS.LayoutGrid);
        expect(resolveNavIcon('toString')).toBe(NAV_ICONS.LayoutGrid);
    });

    it('hydration always yields a renderable component', () => {
        const item = hydrateNavItem(
            {
                labelKey: 'x', href: '/x', icon: 'DefinitelyNotAnIcon',
                permission: null, sort: 0, activePrefix: null, items: [],
            },
            k => k,
        );

        expect(item.icon).toBe(NAV_ICONS.LayoutGrid);
    });
});
