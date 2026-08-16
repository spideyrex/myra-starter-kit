import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, effectScope, ref } from 'vue';

const user = ref<{ roles: string[]; permissions: string[] }>({ roles: [], permissions: [] });
const visit = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { auth: { user: user.value }, myraNav: navPayload.value, demos: demosPayload.value } }),
    router: { visit: (href: string) => visit(href) },
}));

const navPayload = ref<any[]>([]);
const demosPayload = ref<any[]>([]);

(globalThis as any).route = (name: string) => `/${name.replace(/\./g, '/')}`;

import {
    flushCommands,
    registerCommands,
    useCommandRegistry,
    useCommandScope,
    type Command,
} from '@/composables/useCommandRegistry';
import { coreCommands, navigateCommandId } from '@/commands/coreCommands';

function cmd(id: string, extra: Partial<Command> = {}): Command {
    return { id, titleKey: 'gallery.commands.shortcuts', groupKey: 'gallery.commands.group.action', run: () => {}, ...extra };
}

describe('useCommandRegistry', () => {
    beforeEach(() => {
        flushCommands();
        user.value = { roles: [], permissions: [] };
        navPayload.value = [];
        demosPayload.value = [];
        visit.mockClear();
    });

    it('dedupes by id — the first registration wins', () => {
        registerCommands(cmd('a', { titleKey: 'gallery.commands.themeToggle' }));
        registerCommands(cmd('a', { titleKey: 'gallery.commands.shortcuts' }), cmd('b'));

        const { commands } = useCommandRegistry();

        expect(commands.value.map(c => c.id)).toEqual(['a', 'b']);
        expect(commands.value[0].titleKey).toBe('gallery.commands.themeToggle');
    });

    it('unregisters exactly what it registered', () => {
        const off = registerCommands(cmd('a'), cmd('b'));
        registerCommands(cmd('c'));

        const { commands } = useCommandRegistry();
        expect(commands.value).toHaveLength(3);

        off();
        expect(commands.value.map(c => c.id)).toEqual(['c']);
    });

    it('hides a command whose permission the actor does not hold', () => {
        registerCommands(cmd('gated', { permission: 'reports.view' }), cmd('open'));

        const { commands } = useCommandRegistry();
        expect(commands.value.map(c => c.id)).toEqual(['open']);

        user.value = { roles: [], permissions: ['reports.view'] };
        expect(commands.value.map(c => c.id)).toEqual(['gated', 'open']);
    });

    it('lets a super-admin through without an explicit permission row', () => {
        registerCommands(cmd('gated', { permission: 'anything.at.all' }));
        user.value = { roles: ['super-admin'], permissions: [] };

        expect(useCommandRegistry().commands.value.map(c => c.id)).toEqual(['gated']);
    });

    it('hides a command whose when() is false and survives one that throws', () => {
        registerCommands(
            cmd('hidden', { when: () => false }),
            cmd('shown', { when: () => true }),
            cmd('boom', { when: () => { throw new Error('nope'); } }),
        );

        expect(useCommandRegistry().commands.value.map(c => c.id)).toEqual(['shown']);
    });

    it('returns highlight runs from match() and filters on keywords too', () => {
        registerCommands(cmd('theme', { titleKey: 'gallery.commands.themeToggle', keywords: ['zebra'] }));

        const { match } = useCommandRegistry();

        expect(match('').map(c => c.id)).toEqual(['theme']);

        const hit = match('dark');
        expect(hit).toHaveLength(1);
        expect(hit[0].runs.some(r => r.mark)).toBe(true);
        expect(hit[0].runs.map(r => r.text).join('')).toBe(hit[0].title);

        expect(match('zebra').map(c => c.id)).toEqual(['theme']);
        expect(match('nothinglikethis')).toEqual([]);
    });

    it('drops a scoped source when its effect scope is disposed', () => {
        const scope = effectScope();
        const local = ref<Command[]>([cmd('scoped')]);

        scope.run(() => useCommandScope(computed(() => local.value)));

        const { commands } = useCommandRegistry();
        expect(commands.value.map(c => c.id)).toEqual(['scoped']);

        local.value = [cmd('scoped'), cmd('scoped-2')];
        expect(commands.value).toHaveLength(2);

        scope.stop();
        expect(commands.value).toEqual([]);
    });
});

describe('coreCommands', () => {
    beforeEach(() => {
        flushCommands();
        user.value = { roles: ['super-admin'], permissions: [] };
        navPayload.value = [];
        demosPayload.value = [];
        visit.mockClear();
    });

    it('turns every navigable nav leaf — including cluster children — into a command', () => {
        navPayload.value = [{
            labelKey: 'navGroups.system',
            sort: 0,
            items: [
                { labelKey: 'nav.users', href: '/admin/users', icon: 'Users', permission: 'users.view', sort: 0, activePrefix: null, items: [] },
                {
                    labelKey: 'clusters.learning', href: null, icon: null, permission: null, sort: 1, activePrefix: null,
                    items: [{ labelKey: 'nav.pages', href: '/admin/pages', icon: null, permission: null, sort: 0, activePrefix: null, items: [] }],
                },
            ],
        }];

        const ids = coreCommands({ toggleTheme: () => {} }).map(c => c.id);

        expect(ids).toContain(navigateCommandId('/admin/users'));
        expect(ids).toContain(navigateCommandId('/admin/pages'));
        // A parent with no href is an expander, not a destination.
        expect(ids).not.toContain(navigateCommandId('null'));
    });

    it('carries the nav item permission onto the command', () => {
        navPayload.value = [{
            labelKey: 'navGroups.system', sort: 0,
            items: [{ labelKey: 'nav.users', href: '/admin/users', icon: 'Users', permission: 'users.view', sort: 0, activePrefix: null, items: [] }],
        }];

        const command = coreCommands({ toggleTheme: () => {} }).find(c => c.id === navigateCommandId('/admin/users'));

        expect(command?.permission).toBe('users.view');
    });

    it('registers the theme toggle, all three locales, and hides the shortcut sheet with no handler', () => {
        const ids = coreCommands({ toggleTheme: () => {} }).map(c => c.id);

        expect(ids).toContain('theme.toggle');
        expect(ids).toEqual(expect.arrayContaining(['locale.en', 'locale.ms', 'locale.zh']));

        registerCommands(...coreCommands({ toggleTheme: () => {} }));
        expect(useCommandRegistry().commands.value.map(c => c.id)).not.toContain('help.shortcuts');

        flushCommands();
        registerCommands(...coreCommands({ toggleTheme: () => {}, onShortcuts: () => {} }));
        expect(useCommandRegistry().commands.value.map(c => c.id)).toContain('help.shortcuts');
    });

    it('turns the demo registry payload into commands when the page ships it', () => {
        demosPayload.value = [{ key: 'playground', titleKey: 'gallery.demos.playground.title', route: 'admin.demo.playground' }];

        const command = coreCommands({ toggleTheme: () => {} }).find(c => c.id === 'demo:playground');

        expect(command).toBeTruthy();
        command!.run({ close: () => {} });
        expect(visit).toHaveBeenCalledWith('/admin/demo/playground');
    });

    it('runs the theme toggle it was handed', () => {
        const toggleTheme = vi.fn();

        coreCommands({ toggleTheme }).find(c => c.id === 'theme.toggle')!.run({ close: () => {} });

        expect(toggleTheme).toHaveBeenCalledOnce();
    });
});
