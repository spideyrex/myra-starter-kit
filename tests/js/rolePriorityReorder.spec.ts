import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const post = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    usePage: () => ({ props: { auth: { user: { roles: ['super-admin'], permissions: [] } } } }),
    router: { post, put: vi.fn(), delete: vi.fn(), get: vi.fn() },
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import { testI18n } from './helpers/i18n';
import { REORDER_INSTRUCTIONS_ID } from '@/composables/useReorderable';

const knownRoutes = new Set<string>();

const routeStub: any = (name?: string, params?: any) => (name === undefined
    ? { has: (candidate: string) => knownRoutes.has(candidate) }
    : `/${name}/${params ?? ''}`);

(globalThis as any).route = routeStub;

const RolesPage = (await import('@/Pages/Admin/Roles/Index.vue')).default;

const layoutStub = { name: 'AuthenticatedLayout', props: ['breadcrumbs'], template: '<div><slot /></div>' };

/** The shape RoleController::index maps, field for field. */
function role(id: number, name: string, priority: number, hasDashboard = false) {
    return {
        id, name, priority,
        users_count: 1,
        permissions: ['users.view'],
        is_active: true,
        visible: true,
        is_locked: name === 'super-admin',
        is_privileged: ['super-admin', 'admin'].includes(name),
        has_dashboard: hasDashboard,
        created_at: '2026-08-01 00:00:00',
    };
}

function mountPage(overrides: Record<string, any> = {}) {
    return mount(RolesPage, {
        props: {
            roles: [role(1, 'admin', 40, true), role(2, 'manager', 30), role(3, 'viewer', 10)],
            permissionMatrix: {},
            roleNames: [],
            rolePermissions: {},
            totalUsersWithRoles: 3,
            totalPermissions: 0,
            isSuperAdmin: true,
            canManageRoleDashboards: true,
            ...overrides,
        } as any,
        global: {
            plugins: [testI18n()],
            mocks: { route: routeStub },
            stubs: { AuthenticatedLayout: layoutStub },
        },
    });
}

function press(handle: any, key: string) {
    return handle.trigger('keydown', { key });
}

beforeEach(() => {
    post.mockReset();
    knownRoutes.clear();
    knownRoutes.add('admin.role-dashboards.edit');
});

describe('role priority ordering', () => {
    it('renders the roles in server order with their priority', () => {
        const w = mountPage();

        expect(w.findAll('[data-testid="role-priority"]').map(c => c.text())).toEqual(['40', '30', '10']);
    });

    it('reorders with the keyboard and emits an ordered ID SEQUENCE, never an index', async () => {
        const w = mountPage();
        const handles = w.findAll('[data-reorder-key]');

        expect(handles).toHaveLength(3);

        await press(handles[0], ' ');
        await press(handles[0], 'ArrowDown');

        const save = w.findAll('button').find(b => b.text().includes('Save order'));
        expect(save).toBeTruthy();
        await save!.trigger('click');

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/admin.roles.reorder/');
        expect(post.mock.calls[0][1]).toEqual({ ids: [2, 1, 3] });
        expect(post.mock.calls[0][2].preserveScroll).toBe(true);
    });

    it('fires no request until the new order is saved', async () => {
        const w = mountPage();
        const handles = w.findAll('[data-reorder-key]');

        await press(handles[0], ' ');
        await press(handles[0], 'ArrowDown');

        expect(post).not.toHaveBeenCalled();
    });

    it('discards a draft order without touching the server', async () => {
        const w = mountPage();
        const handles = w.findAll('[data-reorder-key]');

        await press(handles[0], ' ');
        await press(handles[0], 'ArrowDown');

        const discard = w.findAll('button').find(b => b.text().includes('Discard'));
        await discard!.trigger('click');

        expect(post).not.toHaveBeenCalled();
        expect(w.findAll('[data-testid="role-priority"]').map(c => c.text())).toEqual(['40', '30', '10']);
    });

    it('offers no drag handle to a non-super-admin', () => {
        const w = mountPage({ isSuperAdmin: false });

        expect(w.findAll('[data-reorder-key]')).toHaveLength(0);
    });

    it('announces every keyboard move and points the handles at the shared instructions', async () => {
        const w = mountPage();
        const handle = w.findAll('[data-reorder-key]')[0];

        expect(handle.attributes('aria-label')).toContain('admin');
        expect(handle.attributes('aria-describedby')).toBe(REORDER_INSTRUCTIONS_ID);
        // The handle sits in a table cell: `listitem` there would be a lie.
        expect(handle.attributes('role')).toBeUndefined();
        expect(w.find(`#${REORDER_INSTRUCTIONS_ID}`).exists()).toBe(true);

        await press(handle, ' ');
        await press(handle, 'ArrowDown');

        expect(w.find('[role="status"]').text()).toContain('admin');
    });
});

describe('the role dashboard row action', () => {
    function actionLabels(w: any, index = 0): string[] {
        return w.findAllComponents({ name: 'RowActions' })[index]
            .props('actions')
            .filter((action: any) => action.show !== false)
            .map((action: any) => action.label);
    }

    it('is offered when the ability and bundle B\'s route are both present', () => {
        expect(actionLabels(mountPage())).toContain('Dashboard');
    });

    it('is withheld without the ability', () => {
        expect(actionLabels(mountPage({ canManageRoleDashboards: false }))).not.toContain('Dashboard');
    });

    it('is withheld when the authoring route does not exist', () => {
        knownRoutes.clear();

        expect(actionLabels(mountPage())).not.toContain('Dashboard');
    });

    it('names the role it configures', () => {
        const actions = mountPage().findAllComponents({ name: 'RowActions' })[0].props('actions') as any[];
        const dashboard = actions.find(action => action.label === 'Dashboard');

        expect(dashboard.tooltip).toContain('admin');
        expect(dashboard.href).toBe('/admin.role-dashboards.edit/1');
    });
});
