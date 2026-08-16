import { describe, expect, it, beforeAll } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

import en from '@/i18n/locales/en.json';
import BlockViewportBar from '@/components/admin/blocks/BlockViewportBar.vue';

beforeAll(() => {
    // reka-ui's roving focus reads element geometry; jsdom ships no observer.
    (globalThis as any).ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    };
});

function mountBar(props: Record<string, unknown> = {}) {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    return mount(BlockViewportBar, {
        props: { modelValue: 'full', dark: false, viewports: ['full', '1024', '768', '375'], ...props },
        global: { plugins: [i18n] },
    });
}

describe('block viewport bar', () => {
    it('gives the toggle group an accessible name from a visible label', () => {
        const w = mountBar();
        const group = w.find('[data-slot="toggle-group"]');

        expect(group.exists()).toBe(true);

        const labelledBy = group.attributes('aria-labelledby');
        expect(labelledBy).toBeTruthy();
        expect(w.find(`#${labelledBy}`).text()).toBe(en.blocks.preview.viewport);
    });

    it('names every viewport option', () => {
        const items = mountBar().findAll('[data-slot="toggle-group-item"]');

        expect(items.length).toBe(4);
        for (const item of items) expect(item.attributes('aria-label')).toBeTruthy();
    });

    it('associates the dark switch with a real label', () => {
        const w = mountBar();
        const control = w.find('[role="switch"]');

        expect(control.exists()).toBe(true);

        const labelledBy = control.attributes('aria-labelledby');
        expect(labelledBy).toBeTruthy();
        expect(w.find(`#${labelledBy}`).text()).toBe(en.blocks.preview.dark);
    });

    it('announces the viewport politely', async () => {
        const w = mountBar();
        const status = w.find('[role="status"]');

        expect(status.attributes('aria-live')).toBe('polite');
        expect(status.text()).toContain(en.blocks.preview.viewportFull);

        await w.setProps({ modelValue: '375' });

        expect(w.find('[role="status"]').text()).toContain('375');
    });

    it('emits the chosen viewport rather than mutating the prop', async () => {
        const w = mountBar();

        await w.findAll('[data-slot="toggle-group-item"]')[3].trigger('click');

        expect(w.emitted('update:modelValue')?.[0]).toEqual(['375']);
    });
});
