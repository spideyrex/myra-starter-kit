import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { get: routerGet } }));

import SegmentPopover from '@/components/admin/reportDelivery/SegmentPopover.vue';
import { testI18n } from './helpers/i18n';
import type { ReportRow } from '@/types/report-delivery';

const popoverStubs = {
    Popover: { template: '<div class="stub-popover"><slot /></div>' },
    PopoverAnchor: { template: '<div class="stub-popover-anchor"><slot /></div>' },
    PopoverContent: { template: '<div class="stub-popover-content"><slot /></div>' },
    Tooltip: { template: '<div><slot /></div>' },
    TooltipContent: { template: '<div class="stub-tooltip"><slot /></div>' },
    TooltipProvider: { template: '<div><slot /></div>' },
    TooltipTrigger: { template: '<div><slot /></div>' },
};

function row(overrides: Partial<ReportRow> = {}): ReportRow {
    return {
        key: 'active',
        label: 'Active',
        values: { signups: 812 },
        previous: null,
        deltas: { signups: null },
        isOther: false,
        drill: { url: '/admin/users', params: { query: '{"conjunction":"and","rules":[],"groups":[]}' } },
        ...overrides,
    };
}

function harness(props: Partial<{ row: ReportRow; measureKey: string }> = {}) {
    return mount(SegmentPopover, {
        attachTo: document.body,
        props: { row: row(), measureKey: 'signups', ...props },
        global: { plugins: [testI18n()], stubs: popoverStubs },
    });
}

describe('SegmentPopover', () => {
    it('opens from the keyboard on Enter and on Space', async () => {
        const wrapper = harness();
        const trigger = wrapper.get('button[aria-haspopup="dialog"]');

        expect(trigger.attributes('aria-expanded')).toBe('false');

        await trigger.trigger('keydown', { key: 'Enter' });
        expect(trigger.attributes('aria-expanded')).toBe('true');

        wrapper.unmount();
    });

    it('opens on Space as well as Enter', async () => {
        const wrapper = harness();
        const trigger = wrapper.get('button[aria-haspopup="dialog"]');

        await trigger.trigger('keydown', { key: ' ' });

        expect(trigger.attributes('aria-expanded')).toBe('true');

        wrapper.unmount();
    });

    it('returns focus to the segment when Escape closes the popover', async () => {
        const wrapper = harness();
        const trigger = wrapper.get('button[aria-haspopup="dialog"]');

        await trigger.trigger('click');
        expect(trigger.attributes('aria-expanded')).toBe('true');

        await wrapper.get('.stub-popover-content').trigger('keydown', { key: 'Escape' });
        await nextTick();
        await nextTick();

        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger.element);

        wrapper.unmount();
    });

    it('disables the view action and explains why when the row cannot be drilled', () => {
        const wrapper = harness({ row: row({ drill: null }) });

        expect(wrapper.get('[data-testid="segment-view"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.stub-tooltip').text()).toContain('cannot be opened');

        wrapper.unmount();
    });

    it('never offers a drill on an Other bucket', () => {
        const wrapper = harness({ row: row({ isOther: true }) });

        expect(wrapper.get('[data-testid="segment-view"]').attributes('disabled')).toBeDefined();

        wrapper.unmount();
    });

    it('navigates through the server-built target rather than composing its own', async () => {
        const wrapper = harness();

        await wrapper.get('[data-testid="segment-view"]').trigger('click');

        expect(routerGet).toHaveBeenCalledWith(
            '/admin/users',
            { query: '{"conjunction":"and","rules":[],"groups":[]}' },
            expect.anything(),
        );

        wrapper.unmount();
    });

    it('emits a filter request instead of navigating', async () => {
        const wrapper = harness();

        await wrapper.get('[data-testid="segment-filter"]').trigger('click');

        expect(wrapper.emitted('filter')).toHaveLength(1);

        wrapper.unmount();
    });
});
