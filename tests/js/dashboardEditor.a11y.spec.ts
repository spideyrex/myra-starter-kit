import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const inertiaPage = vi.hoisted(() => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } as any }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => inertiaPage,
    router: { put: vi.fn(), delete: vi.fn() },
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));

import { testI18n, uiStubs } from './helpers/i18n';
import DashboardEditorBar from '@/components/admin/DashboardEditorBar.vue';
import WidgetTileChrome from '@/components/admin/WidgetTileChrome.vue';
import DashboardGrid from '@/components/admin/DashboardGrid.vue';
import { StatWidget } from '@/composables/useDashboardWidgets';
import { REORDER_INSTRUCTIONS_ID } from '@/composables/useReorderable';

function bar(props: Record<string, unknown> = {}) {
    return mount(DashboardEditorBar, {
        props: {
            editing: false, dirty: false, saving: false, customised: false, announcement: '',
            ...props,
        } as any,
        global: { plugins: [testI18n()], stubs: uiStubs },
    });
}

function chrome(editing: boolean) {
    return mount(WidgetTileChrome, {
        props: {
            widget: { key: 'total_users', type: 'stat', colSpan: 1, title: 'Total users' },
            editing,
            removable: true,
            handleProps: { role: 'listitem', tabindex: 0, 'aria-describedby': REORDER_INSTRUCTIONS_ID },
        } as any,
        global: { plugins: [testI18n()], stubs: uiStubs },
    });
}

describe('editor bar accessibility', () => {
    it('exposes exactly one polite atomic status announcer', () => {
        const w = bar({ editing: true, announcement: 'Total users, position 1 of 4.' });
        const announcers = w.findAll('[role="status"]');

        expect(announcers).toHaveLength(1);
        expect(announcers[0].attributes('aria-live')).toBe('polite');
        expect(announcers[0].attributes('aria-atomic')).toBe('true');
        expect(announcers[0].text()).toContain('position 1 of 4');
    });

    it('publishes the keyboard instructions node the handles point at', () => {
        const node = bar({ editing: true }).find(`#${REORDER_INSTRUCTIONS_ID}`);

        expect(node.exists()).toBe(true);
        expect(node.text().length).toBeGreaterThan(20);
        expect(node.classes()).toContain('sr-only');
    });

    it('offers only the Customise affordance while not editing', () => {
        const w = bar();

        expect(w.findAll('button')).toHaveLength(1);
        expect(w.text()).not.toContain('Undo');
    });

    it('lists hidden widgets so a hidden tile can be restored', async () => {
        const w = bar({
            editing: true,
            hidden: [{ key: 'pending', type: 'stat', colSpan: 1, title: 'Pending' }],
        });

        const restore = w.findAll('button').find(b => b.text().includes('Pending'));

        expect(restore).toBeTruthy();
        await restore!.trigger('click');
        expect(w.emitted('restore')?.[0]).toEqual(['pending']);
    });
});

describe('tile chrome', () => {
    it('is absent from the accessibility tree when editing is false', () => {
        const w = chrome(false);

        expect(w.findAll('button')).toHaveLength(0);
        expect(w.findAll('[tabindex]')).toHaveLength(0);
        expect(w.text()).toBe('');
    });

    it('renders the drag handle as a real button with an accessible name', () => {
        const handle = chrome(true).find('button');

        expect(handle.element.tagName).toBe('BUTTON');
        expect(handle.attributes('type')).toBe('button');
        expect(handle.attributes('aria-label')).toContain('Total users');
        expect(handle.attributes('aria-describedby')).toBe(REORDER_INSTRUCTIONS_ID);
        expect(handle.attributes('tabindex')).toBe('0');
    });

    it('never emits the deprecated drag ARIA attributes', () => {
        const html = chrome(true).html();

        expect(html).not.toContain('aria-grabbed');
        expect(html).not.toContain('aria-dropeffect');
    });
});

describe('grid edit mode', () => {
    function grid(editing: boolean) {
        return mount(DashboardGrid, {
            props: {
                widgets: [StatWidget.make('total_users').value(() => 1)],
                pageProps: {},
                editing,
            } as any,
            global: { plugins: [testI18n()], stubs: uiStubs },
        });
    }

    it('adds no list semantics and no chrome when not editing', () => {
        const w = grid(false);

        expect(w.find('[role="list"]').exists()).toBe(false);
        expect(w.find('[data-reorder-key]').exists()).toBe(false);
    });

    it('marks the container as a list while editing', () => {
        expect(grid(true).find('[role="list"]').exists()).toBe(true);
    });
});
