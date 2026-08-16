import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { nextTick, ref } from 'vue';

const user = ref({ roles: ['super-admin'], permissions: [] as string[] });
const visit = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { auth: { user: user.value } } }),
    router: { visit: (href: string) => visit(href) },
}));

// reka-ui's Dialog teleports and traps focus; neither is what this spec is about.
vi.mock('@/components/ui/dialog', () => ({
    Dialog: { template: '<div><slot /></div>' },
    DialogContent: { template: '<div><slot /></div>' },
    DialogTitle: { template: '<h2><slot /></h2>' },
    DialogDescription: { template: '<p><slot /></p>' },
}));

(globalThis as any).route = (name: string) => `/${name.replace(/\./g, '/')}`;

import en from '@/i18n/locales/en.json';
import CommandPalette from '@/components/admin/CommandPalette.vue';
import { flushCommands, registerCommands } from '@/composables/useCommandRegistry';

const LAYOUT = resolve(__dirname, '../../resources/js/Layouts/AuthenticatedLayout.vue');

function mountPalette() {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    return mount(CommandPalette, {
        props: { open: true, bindShortcut: false },
        global: { plugins: [i18n] },
    });
}

describe('layout palette wiring (regression guard)', () => {
    const source = readFileSync(LAYOUT, 'utf8');

    it('keeps exactly ONE Cmd/Ctrl+K handler in the layout', () => {
        expect(source.match(/addEventListener\('keydown'/g) ?? []).toHaveLength(1);
        expect(source.match(/metaKey \|\| e\.ctrlKey/g) ?? []).toHaveLength(1);
    });

    it('never mounts the standalone CommandPalette inside the layout', () => {
        // Two dialogs bound to the same shortcut is the failure this guards.
        expect(source).not.toContain('CommandPalette');
    });

    it('renders the registered commands through the EXISTING CommandDialog', () => {
        expect(source).toContain('paletteCommandGroups');
        expect(source).toContain('runRegistered');
        expect(source.match(/>>> MYRA v2\.5 \[C\] START/g) ?? []).toHaveLength(2);
        expect(source.match(/<<< MYRA v2\.5 \[C\] END/g) ?? []).toHaveLength(2);
    });
});

describe('standalone command palette', () => {
    beforeEach(() => {
        flushCommands();
        visit.mockClear();
    });

    it('renders commands as options inside the existing listbox', async () => {
        registerCommands({
            id: 'theme.toggle',
            titleKey: 'gallery.commands.themeToggle',
            groupKey: 'gallery.commands.group.theme',
            run: () => {},
        });

        const w = mountPalette();
        await nextTick();

        const listbox = w.find('[role="listbox"]');
        expect(listbox.exists()).toBe(true);

        const options = listbox.findAll('[role="option"]');
        expect(options.length).toBe(1);
        expect(options[0].text()).toContain(en.gallery.commands.themeToggle);
    });

    it('keeps the combobox pointed at the active option', async () => {
        registerCommands(
            { id: 'a', titleKey: 'gallery.commands.themeToggle', groupKey: 'gallery.commands.group.theme', run: () => {} },
            { id: 'b', titleKey: 'gallery.commands.shortcuts', groupKey: 'gallery.commands.group.action', run: () => {} },
        );

        const w = mountPalette();
        await nextTick();

        const input = w.find('[role="combobox"]');
        const first = input.attributes('aria-activedescendant');
        expect(first).toBeTruthy();

        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(input.attributes('aria-activedescendant')).not.toBe(first);

        const options = w.findAll('[role="option"]');
        expect(options[1].attributes('aria-selected')).toBe('true');
    });

    it('runs a command on Enter instead of navigating', async () => {
        const run = vi.fn();
        registerCommands({
            id: 'theme.toggle',
            titleKey: 'gallery.commands.themeToggle',
            groupKey: 'gallery.commands.group.theme',
            run,
        });

        const w = mountPalette();
        await nextTick();
        await w.find('[role="combobox"]').trigger('keydown', { key: 'Enter' });

        expect(run).toHaveBeenCalledOnce();
        expect(visit).not.toHaveBeenCalled();
    });

    it('keeps exactly one polite announcer', () => {
        const statuses = mountPalette().findAll('[role="status"]');

        expect(statuses).toHaveLength(1);
        expect(statuses[0].attributes('aria-live')).toBe('polite');
        expect(statuses[0].attributes('aria-atomic')).toBe('true');
    });

    it('never renders a command title through v-html', () => {
        expect(readFileSync(resolve(__dirname, '../../resources/js/components/admin/CommandPalette.vue'), 'utf8'))
            .not.toContain('v-html');
    });
});
