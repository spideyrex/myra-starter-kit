import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import RowActions from '@/components/admin/RowActions.vue';
import type { RowAction, RowActionsConfig } from '@/types/admin';

vi.mock('@/composables/usePermissions', () => ({
    usePermissions: () => ({ can: (p: string) => p !== 'denied' }),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const baseConfig: RowActionsConfig = {
    label: 'Actions',
    size: 'sm',
    asButton: false,
    buttonGroup: false,
    placement: 'bottom-end',
    width: 'md',
    maxHeight: '20rem',
};

function mountWith(actions: RowAction[], config?: Partial<RowActionsConfig>) {
    return mount(RowActions, {
        props: { actions, config: config ? { ...baseConfig, ...config } : undefined },
        global: { stubs: { Tooltip: true, TooltipContent: true, TooltipProvider: true, TooltipTrigger: true } },
    });
}

describe('RowActions', () => {
    it('gives the icon-only trigger a non-empty accessible name', () => {
        const wrapper = mountWith([{ label: 'Edit', href: '/edit' }]);

        expect(wrapper.find('[aria-label]').attributes('aria-label')).toBeTruthy();
    });

    it('renders inline buttons below the collapseAfter threshold', () => {
        const wrapper = mountWith(
            [{ label: 'Edit', href: '/edit' }, { label: 'Delete' }],
            { collapseAfter: 3 },
        );

        expect(wrapper.findAll('button, a').length).toBeGreaterThanOrEqual(2);
        expect(wrapper.text()).toContain('Edit');
        expect(wrapper.text()).toContain('Delete');
    });

    it('collapses to a dropdown at or above the collapseAfter threshold', () => {
        const wrapper = mountWith(
            [
                { label: 'One' }, { label: 'Two' }, { label: 'Three' }, { label: 'Four' },
            ],
            { collapseAfter: 3 },
        );

        // The dropdown content is portalled and closed, so no item text is rendered.
        expect(wrapper.text()).not.toContain('Four');
    });

    it('renders an external action as a new-tab anchor, not an Inertia Link', () => {
        const wrapper = mountWith(
            [{ label: 'View on site', href: 'https://example.test', external: true }],
            { collapseAfter: 3 },
        );

        const anchor = wrapper.find('a');
        expect(anchor.attributes('target')).toBe('_blank');
        expect(anchor.attributes('rel')).toBe('noopener noreferrer');
        expect(wrapper.text()).toContain('opens in a new tab');
    });

    it('hides actions the user has no permission for', () => {
        const wrapper = mountWith(
            [{ label: 'Allowed' }, { label: 'Blocked', permission: 'denied' }],
            { collapseAfter: 5 },
        );

        expect(wrapper.text()).toContain('Allowed');
        expect(wrapper.text()).not.toContain('Blocked');
    });

    it('renders nothing when every action is filtered out', () => {
        const wrapper = mountWith([{ label: 'Blocked', permission: 'denied' }]);

        expect(wrapper.text()).toBe('');
    });

    it('drops a divider that would be left orphaned by permission filtering', () => {
        const wrapper = mountWith(
            [
                { kind: 'divider', label: '' },
                { label: 'Blocked', permission: 'denied' },
                { kind: 'divider', label: '' },
            ],
        );

        // Only structural items survive, so the component renders nothing at all.
        expect(wrapper.text()).toBe('');
    });
});
