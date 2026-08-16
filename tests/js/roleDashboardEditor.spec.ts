import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', template: '<span />' },
    Link: { name: 'Link', template: '<a><slot /></a>' },
    usePage: () => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } }),
    router: { get: vi.fn(), put: vi.fn(), delete: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

// The chrome is not what this spec is about and drags sonner/echo into jsdom.
vi.mock('@/Layouts/AuthenticatedLayout.vue', () => ({
    default: { name: 'AuthenticatedLayout', props: ['breadcrumbs'], template: '<div><slot /></div>' },
}));
vi.mock('@/components/admin/DateCell.vue', () => ({
    default: { name: 'DateCell', props: ['value', 'format'], template: '<time class="stub-date" />' },
}));

const route = (name: string, param?: unknown) => `/${name.replace(/\./g, '/')}${param === undefined ? '' : `/${param}`}`;

(globalThis as any).route = route;
const ziggy = { install: (app: any) => { app.config.globalProperties.route = route; } };

import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';
import RoleDashboardsIndex from '@/Pages/Admin/RoleDashboards/Index.vue';
import RoleAuthoringBanner from '@/components/admin/RoleAuthoringBanner.vue';

/** Mirrors the row contract RoleDashboardControllerTest pins on the server. */
function row(overrides: Record<string, unknown> = {}) {
    return {
        id: 3,
        name: 'manager',
        priority: 30,
        is_active: true,
        is_locked: false,
        configured: true,
        widget_count: 4,
        updated_at: '2026-08-16T10:00:00.000000Z',
        ...overrides,
    };
}

function i18n(locale = 'en') {
    return createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en, ms, zh } });
}

function mountIndex(props: Record<string, unknown> = {}, locale = 'en') {
    return mount(RoleDashboardsIndex, {
        props: {
            roles: [row()],
            dashboardKey: 'admin.dashboard',
            isSuperAdmin: true,
            ...props,
        } as any,
        global: { plugins: [i18n(locale), ziggy] },
    });
}

describe('role dashboards index', () => {
    it('renders one row per role and names the role in every action', () => {
        const w = mountIndex({
            roles: [row(), row({ id: 4, name: 'editor', priority: 20, configured: false, widget_count: 0, updated_at: null })],
        });

        expect(w.findAll('tbody tr')).toHaveLength(2);

        const configure = w.findAll('[aria-label]').map(n => n.attributes('aria-label'));

        expect(configure).toContain('Configure the manager dashboard');
        expect(configure).toContain('Configure the editor dashboard');
        expect(configure).toContain('Clear the manager dashboard');
        // Nothing to clear on a role with no dashboard.
        expect(configure).not.toContain('Clear the editor dashboard');
    });

    it('distinguishes configured from not configured in text, never colour alone', () => {
        const w = mountIndex({
            roles: [row(), row({ id: 4, name: 'editor', configured: false, widget_count: 0, updated_at: null })],
        });

        expect(w.text()).toContain('Configured');
        expect(w.text()).toContain('Not configured');
    });

    it('links Configure at the role authoring route', () => {
        const link = mountIndex().find('a[href*="role-dashboards"]');

        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toContain('admin/role-dashboards/edit');
    });

    it('gives the table an sr-only caption and column-scoped headers', () => {
        const w = mountIndex();
        const caption = w.find('caption');

        expect(caption.exists()).toBe(true);
        expect(caption.classes()).toContain('sr-only');
        expect(caption.text().length).toBeGreaterThan(10);

        const heads = w.findAll('th');

        expect(heads.length).toBeGreaterThan(0);
        expect(heads.every(th => th.attributes('scope') === 'col')).toBe(true);
    });

    it('shows the empty state until some role has a dashboard', () => {
        const empty = mountIndex({ roles: [row({ configured: false, widget_count: 0, updated_at: null })] });

        expect(empty.text()).toContain('No role has a dashboard yet');
        expect(mountIndex().text()).not.toContain('No role has a dashboard yet');
    });

    it('offers no authoring action on the locked role to a non-super-admin', () => {
        const locked = row({ id: 1, name: 'super-admin', priority: 50, is_locked: true });

        const denied = mountIndex({ roles: [locked], isSuperAdmin: false });
        const allowed = mountIndex({ roles: [locked], isSuperAdmin: true });

        expect(denied.findAll('[aria-label]').map(n => n.attributes('aria-label')))
            .not.toContain('Configure the super-admin dashboard');
        expect(allowed.findAll('[aria-label]').map(n => n.attributes('aria-label')))
            .toContain('Configure the super-admin dashboard');
    });

    it('translates in every shipped locale without falling back to English', () => {
        const notConfigured = [row({ configured: false, widget_count: 0, updated_at: null })];

        expect(mountIndex({ roles: notConfigured }, 'ms').text())
            .toContain(ms.roleDashboardAdmin.notConfigured);
        expect(mountIndex({ roles: notConfigured }, 'zh').text())
            .toContain(zh.roleDashboardAdmin.notConfigured);
        expect(mountIndex({ roles: notConfigured }, 'ms').text())
            .not.toContain(en.roleDashboardAdmin.notConfigured);
    });
});

describe('role authoring banner', () => {
    function banner(props: Record<string, unknown> = {}) {
        return mount(RoleAuthoringBanner, {
            props: { role: 'manager', backHref: '/admin/role-dashboards', ...props } as any,
            global: { plugins: [i18n(), ziggy] },
        });
    }

    it('announces politely that the layout on screen belongs to a role', () => {
        const w = banner();
        const status = w.find('[role="status"]');

        expect(status.exists()).toBe(true);
        expect(status.text()).toContain('manager');
        expect(status.text()).toContain('Editing the manager dashboard');
    });

    it('says plainly that the preview is the role\'s own view', () => {
        expect(banner().text()).toContain('You are seeing exactly what a manager sees');
    });

    it('offers a way back only when one was supplied', () => {
        expect(banner().text()).toContain('Back to role dashboards');
        expect(banner({ backHref: null }).text()).not.toContain('Back to role dashboards');
    });
});
