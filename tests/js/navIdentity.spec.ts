import { describe, expect, it } from 'vitest';
import {
    filterNavGroups,
    filterNavItems,
    mergeServerNav,
    paletteNavGroups,
} from '@/nav/model';

/**
 * THE SHIP GATE. `myraNav: []` must render exactly today's sidebar.
 *
 * These are the three pipeline stages AuthenticatedLayout.vue runs, in order,
 * over the nine hardcoded core groups. Nothing else stands between the payload
 * and the DOM, so an identity here is an identity on screen.
 */

const icon = { name: 'FakeIcon' };

const coreGroups = [
    {
        label: 'Main',
        items: [{ title: 'Dashboard', href: 'http://localhost/dashboard', icon, permission: null }],
    },
    {
        label: 'User Management',
        items: [
            { title: 'Users', href: 'http://localhost/admin/users', icon, permission: 'users.view' },
            { title: 'Roles', href: 'http://localhost/admin/roles', icon, permission: 'roles.view' },
        ],
    },
    {
        label: 'Demo',
        items: [{ title: 'Feature Demos', href: 'http://localhost/admin/demo', icon, permission: null }],
    },
];

const t = (key: string) => key;
const allow = () => true;

describe('additive identity', () => {
    it('merging an empty server list changes nothing', () => {
        expect(mergeServerNav(coreGroups as any, [], t)).toEqual(coreGroups);
    });

    it('the prop being absent is the same as the prop being empty', () => {
        const absent = mergeServerNav(coreGroups as any, undefined as any, t);
        const empty = mergeServerNav(coreGroups as any, [], t);

        expect(absent).toEqual(empty);
        expect(absent).toEqual(coreGroups);
    });

    it('renders the identical group/item list through the whole pipeline', () => {
        const before = coreGroups
            .map(group => ({ ...group, items: group.items.filter(i => !i.permission || allow()) }))
            .filter(group => group.items.length > 0);

        const after = filterNavGroups(mergeServerNav(coreGroups as any, [], t), allow);

        expect(after).toEqual(before);
    });

    it('keeps the core group order', () => {
        const merged = mergeServerNav(coreGroups as any, [], t);

        expect(merged.map(g => g.label)).toEqual(['Main', 'User Management', 'Demo']);
    });

    it('leaves the command palette unchanged for flat core items', () => {
        const groups = filterNavGroups(mergeServerNav(coreGroups as any, [], t), allow);

        expect(paletteNavGroups(groups)).toEqual(groups);
    });

    it('filters exactly as the previous one-line filter did', () => {
        const can = (ability: string) => ability === 'users.view';
        const items = coreGroups[1].items;

        expect(filterNavItems(items, can)).toEqual(items.filter(i => !i.permission || can(i.permission)));
    });
});
