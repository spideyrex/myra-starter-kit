import { describe, expect, it } from 'vitest';
import fixture from './fixtures/myra-nav.json';
import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';
import {
    filterNavGroups,
    flattenNavItems,
    hydrateNavItem,
    isNavItemActive,
    mergeServerNav,
    paletteNavGroups,
} from '@/nav/model';
import { NAV_ICONS } from '@/nav/icons';
import type { MyraNavGroupPayload } from '@/types/nav';

/**
 * The payload here is written by tests/Feature/Navigation/NavPayloadTest from a
 * real request through the real HTTP kernel. Nothing in this file is
 * hand-authored — a server-side shape change fails these specs.
 */
const serverNav = fixture as unknown as MyraNavGroupPayload[];

function lookup(messages: any, key: string): string | undefined {
    return key.split('.').reduce((acc: any, part) => (acc == null ? acc : acc[part]), messages);
}

const t = (key: string) => lookup(en, key) ?? key;

const icon = { name: 'FakeIcon' };
const coreGroups = [
    { label: 'Main', items: [{ title: 'Dashboard', href: 'http://localhost/dashboard', icon, permission: null }] },
    { label: 'Demo', items: [{ title: 'Feature Demos', href: 'http://localhost/admin/demo', icon, permission: null }] },
];

const canDemo = (ability: string) => ability === 'demo.view';

describe('server-contributed cluster', () => {
    it('merges into the core group with the same resolved label', () => {
        const merged = mergeServerNav(coreGroups as any, serverNav, t);

        expect(merged.map(g => g.label)).toEqual(['Main', 'Demo']);

        const demo = merged.find(g => g.label === 'Demo')!;
        expect(demo.items.map((i: any) => i.title)).toEqual(['Feature Demos', 'Learning']);
    });

    it('renders the cluster as one entry with two children', () => {
        const groups = filterNavGroups(mergeServerNav(coreGroups as any, serverNav, t), canDemo);
        const cluster = groups.find(g => g.label === 'Demo')!.items.find((i: any) => i.title === 'Learning')!;

        expect(cluster.href).toBeNull();
        expect(cluster.items.map((c: any) => c.title)).toEqual(['Courses', 'Site Identity']);
        expect(cluster.items.map((c: any) => c.href)).toEqual([
            '/admin/learning/courses',
            '/admin/learning/site-identity',
        ]);
    });

    it('disappears entirely for a user without the ability — no empty expander', () => {
        const groups = filterNavGroups(mergeServerNav(coreGroups as any, serverNav, t), () => false);
        const demo = groups.find(g => g.label === 'Demo')!;

        expect(demo.items.map((i: any) => i.title)).toEqual(['Feature Demos']);
    });

    it('is open when the current url is one of its children', () => {
        const cluster = hydrateNavItem(serverNav[0].items[0], t);

        expect(isNavItemActive(cluster, '/admin/learning/courses')).toBe(true);
        expect(isNavItemActive(cluster, '/admin/learning/site-identity')).toBe(true);
        expect(isNavItemActive(cluster, '/admin/users')).toBe(false);
    });

    it('marks only the matching child active', () => {
        const cluster = hydrateNavItem(serverNav[0].items[0], t);

        expect(isNavItemActive(cluster.items![0], '/admin/learning/courses')).toBe(true);
        expect(isNavItemActive(cluster.items![1], '/admin/learning/courses')).toBe(false);
    });

    it('keeps both children reachable from the command palette', () => {
        const groups = filterNavGroups(mergeServerNav(coreGroups as any, serverNav, t), canDemo);

        expect(flattenNavItems(groups).map((i: any) => i.title)).toEqual([
            'Dashboard', 'Feature Demos', 'Courses', 'Site Identity',
        ]);

        const palette = paletteNavGroups(groups).find(g => g.label === 'Demo')!;
        expect(palette.items.map(i => i.title)).toEqual(['Feature Demos', 'Courses', 'Site Identity']);
    });

    it('resolves every icon name the payload carries', () => {
        const names = serverNav.flatMap(g => g.items.flatMap(i => [i.icon, ...i.items.map(c => c.icon)]));

        for (const name of names) {
            if (name === null) continue;
            expect(Object.keys(NAV_ICONS)).toContain(name);
        }
    });

    it('resolves every label key in all three locales', () => {
        const keys: string[] = [];
        const walk = (items: any[]) => items.forEach(i => { keys.push(i.labelKey); walk(i.items ?? []); });
        serverNav.forEach(g => { keys.push(g.labelKey); walk(g.items); });

        for (const key of keys) {
            expect(typeof lookup(en, key), `en: ${key}`).toBe('string');
            expect(typeof lookup(ms, key), `ms: ${key}`).toBe('string');
            expect(typeof lookup(zh, key), `zh: ${key}`).toBe('string');
        }
    });

    it('never renders a raw i18n key', () => {
        const groups = filterNavGroups(mergeServerNav(coreGroups as any, serverNav, t), canDemo);
        const titles = flattenNavItems(groups).map((i: any) => i.title);

        for (const title of titles) {
            expect(title).not.toMatch(/^clusters\./);
            expect(title).not.toMatch(/^navGroups\./);
        }
    });
});
