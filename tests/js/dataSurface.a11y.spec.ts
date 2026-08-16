import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { testI18n } from './helpers/i18n';
import DataSurface from '@/components/admin/DataSurface.vue';

function surface(state: string, props: Record<string, any> = {}) {
    return mount(DataSurface, {
        props: { state, labelKey: 'reports.users.title', ...props },
        slots: { default: '<p class="real-content">rendered</p>' },
        global: { plugins: [testI18n()] },
    });
}

describe('DataSurface accessibility', () => {
    it('carries exactly one live region', () => {
        const w = surface('loading');

        const regions = w.findAll('[role="status"]');

        expect(regions).toHaveLength(1);
        expect(regions[0].attributes('aria-live')).toBe('polite');
        expect(regions[0].attributes('aria-atomic')).toBe('true');
        expect(regions[0].classes()).toContain('sr-only');
    });

    it('says exactly one sentence per transition and replaces it, never appends', async () => {
        const w = surface('loading');

        const first = w.find('[role="status"]').text();
        expect(first).not.toBe('');

        await w.setProps({ state: 'ready' });

        const second = w.find('[role="status"]').text();
        expect(w.findAll('[role="status"]')).toHaveLength(1);
        expect(second).not.toBe(first);
        expect(second).not.toContain(first);

        await w.setProps({ state: 'empty' });
        expect(w.find('[role="status"]').text()).not.toBe(second);
    });

    it('marks aria-busy only while loading', async () => {
        const w = surface('loading');
        expect(w.find('[data-surface]').attributes('aria-busy')).toBe('true');

        for (const state of ['idle', 'streaming', 'ready', 'empty', 'error', 'offline']) {
            await w.setProps({ state });
            expect(w.find('[data-surface]').attributes('aria-busy')).toBe('false');
        }
    });

    it('renders a skeleton only in the loading state', async () => {
        const w = surface('loading', { skeleton: 'chart', height: 200 });
        expect(w.find('[data-skeleton]').exists()).toBe(true);
        expect(w.find('.real-content').exists()).toBe(false);

        await w.setProps({ state: 'ready' });
        expect(w.find('[data-skeleton]').exists()).toBe(false);
        expect(w.find('.real-content').exists()).toBe(true);
    });

    it('streams partial content with a caret rather than a skeleton', () => {
        const w = surface('streaming', { skeleton: 'chart' });

        expect(w.find('[data-skeleton]').exists()).toBe(false);
        expect(w.find('.real-content').exists()).toBe(true);
        expect(w.find('[data-caret]').exists()).toBe(true);
    });

    it('offers a real button to retry an error and emits it', async () => {
        const w = surface('error');

        const button = w.find('button');
        expect(button.exists()).toBe(true);

        await button.trigger('click');

        expect(w.emitted('retry')).toHaveLength(1);
    });

    it('suppresses the caret animation under reduced motion', () => {
        const w = surface('streaming');

        expect(w.find('[data-caret]').classes()).toContain('motion-reduce:animate-none');
    });
});
