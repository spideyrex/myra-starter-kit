import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { auth: { user: { roles: ['viewer'], permissions: [] } } } }),
    router: { put: vi.fn(), delete: vi.fn() },
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));

import { testI18n, uiStubs } from './helpers/i18n';
import DashboardSourceBadge from '@/components/admin/DashboardSourceBadge.vue';
import DashboardEditorBar from '@/components/admin/DashboardEditorBar.vue';
import type { DashboardLayoutSource } from '@/composables/useDashboardLayout';

function badge(source: DashboardLayoutSource | null) {
    return mount(DashboardSourceBadge, {
        props: { source },
        global: { plugins: [testI18n()], stubs: uiStubs },
    });
}

function bar(props: Record<string, unknown> = {}) {
    return mount(DashboardEditorBar, {
        props: {
            editing: false, dirty: false, saving: false, customised: false, announcement: '',
            ...props,
        } as any,
        global: { plugins: [testI18n()], stubs: uiStubs },
    });
}

describe('dashboard source badge', () => {
    it('renders nothing at all when nothing is configured', () => {
        expect(badge({ source: 'none', role: null, hasRoleDefault: false }).text()).toBe('');
        expect(badge(null).text()).toBe('');
        expect(badge(null).find('[role="status"]').exists()).toBe(false);
    });

    it('names the role behind a role default, in the accessibility tree', () => {
        const w = badge({ source: 'role', role: 'manager', hasRoleDefault: true });
        const status = w.find('[role="status"]');

        expect(status.exists()).toBe(true);
        expect(status.text()).toContain('manager');
        // Text plus icon, never colour alone.
        expect(status.find('svg').exists()).toBe(true);
    });

    it('marks a personal arrangement as the viewer\'s own', () => {
        const w = badge({ source: 'personal', role: 'manager', hasRoleDefault: true });

        expect(w.find('[role="status"]').text()).not.toContain('manager');
        expect(w.text().length).toBeGreaterThan(0);
    });
});

describe('editor bar reset affordance', () => {
    it('stays exactly as it is today when nothing is configured', () => {
        const w = bar();

        expect(w.findAll('button')).toHaveLength(1);
        expect(w.find('[data-slot="badge"]').exists()).toBe(false);
    });

    it('shows no reset while a role default is on screen', () => {
        const w = bar({ customised: false, source: { source: 'role', role: 'manager', hasRoleDefault: true } });

        expect(w.findAll('button')).toHaveLength(1);
        expect(w.text()).toContain('manager');
    });

    it('names the role in the reset control once the layout is the user\'s own', () => {
        const w = bar({
            customised: true,
            roleDefault: 'manager',
            source: { source: 'personal', role: 'manager', hasRoleDefault: true },
        });

        const reset = w.findAll('button').find(b => b.text().includes('manager') && b.text().toLowerCase().includes('reset'));

        expect(reset).toBeTruthy();
    });

    it('falls back to the generic reset label with no role default behind it', () => {
        const w = bar({ customised: true, source: { source: 'personal', role: null, hasRoleDefault: false } });
        const labels = w.findAll('button').map(b => b.text());

        expect(labels.some(text => text.includes('Reset'))).toBe(true);
        expect(labels.every(text => !text.includes('manager'))).toBe(true);
    });
});
