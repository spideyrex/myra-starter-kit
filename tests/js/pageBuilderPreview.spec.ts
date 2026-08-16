import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

const post = vi.fn();

vi.mock('axios', () => ({ default: { post: (...args: unknown[]) => post(...args) } }));

import { testI18n } from './helpers/i18n';
import SectionPreview from '@/components/admin/pagebuilder/SectionPreview.vue';

/** Fake only the debounce timer; flushPromises still needs a real setImmediate. */
function useDebounceTimers(): void {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
}

async function settle(times = 6): Promise<void> {
    for (let i = 0; i < times; i++) {
        await Promise.resolve();
        await nextTick();
    }
}

function draftRow(heading: string) {
    return { id: '01JB', type: 'rich_text', enabled: true, variant: {}, data: { heading, body: '<p>x</p>' } };
}

function mountPreview(props: Record<string, unknown> = {}) {
    return mount(SectionPreview, {
        props: { blocks: [draftRow('One')], template: 'saas', ...props },
        global: { plugins: [testI18n()] },
    });
}

let issued = 0;

beforeEach(() => {
    issued = 0;
    post.mockReset();
    post.mockImplementation(async () => ({ data: { token: `tok-${++issued}` } }));
    useDebounceTimers();
});

afterEach(() => {
    vi.useRealTimers();
    document.documentElement.classList.remove('dark');
});

describe('SectionPreview — the draft reaches the real public page', () => {
    it('publishes the draft on mount and points the frame at the public homepage', async () => {
        const wrapper = mountPreview();
        await settle();

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/dashboard/landing/builder/preview');
        expect(post.mock.calls[0][1]).toEqual({ blocks: [draftRow('One')] });

        expect(wrapper.get('iframe').attributes('src')).toBe('/?preview=tok-1&template=saas');
        wrapper.unmount();
    });

    it('sends the rows verbatim rather than a reshaped copy', async () => {
        const rows = [draftRow('One'), { id: '02', type: 'divider', enabled: false, variant: {}, data: { style: 'space', size: 'lg' } }];
        const wrapper = mountPreview({ blocks: rows });
        await settle();

        expect(post.mock.calls[0][1]).toEqual({ blocks: rows });
        wrapper.unmount();
    });

    it('debounces a burst of edits into a single request', async () => {
        const wrapper = mountPreview();
        await settle();
        expect(post).toHaveBeenCalledTimes(1);

        await wrapper.setProps({ blocks: [draftRow('Two')] });
        await wrapper.setProps({ blocks: [draftRow('Three')] });
        await wrapper.setProps({ blocks: [draftRow('Four')] });

        expect(post).toHaveBeenCalledTimes(1);

        vi.advanceTimersByTime(700);
        await settle();

        expect(post).toHaveBeenCalledTimes(2);
        expect(post.mock.calls[1][1]).toEqual({ blocks: [draftRow('Four')] });
        expect(wrapper.get('iframe').attributes('src')).toBe('/?preview=tok-2&template=saas');
        wrapper.unmount();
    });

    it('republishes when the template changes so the chooser can be previewed unsaved', async () => {
        const wrapper = mountPreview();
        await settle();

        await wrapper.setProps({ template: 'docs' });
        vi.advanceTimersByTime(700);
        await settle();

        expect(post).toHaveBeenCalledTimes(2);
        expect(wrapper.get('iframe').attributes('src')).toBe('/?preview=tok-2&template=docs');
        wrapper.unmount();
    });

    it('omits the template parameter when none was chosen', async () => {
        const wrapper = mountPreview({ template: '' });
        await settle();

        expect(wrapper.get('iframe').attributes('src')).toBe('/?preview=tok-1');
        wrapper.unmount();
    });

    it('never mounts a frame and reports the failure when the slot cannot be written', async () => {
        post.mockRejectedValue(new Error('network'));

        const wrapper = mountPreview();
        await settle();

        expect(wrapper.find('iframe').exists()).toBe(false);
        expect(wrapper.text()).toContain('Preview unavailable');
        wrapper.unmount();
    });

    it('keeps the last good frame when a later publish fails', async () => {
        const wrapper = mountPreview();
        await settle();

        post.mockRejectedValue(new Error('network'));
        await wrapper.setProps({ blocks: [draftRow('Two')] });
        vi.advanceTimersByTime(700);
        await settle();

        expect(wrapper.get('iframe').attributes('src')).toBe('/?preview=tok-1&template=saas');
        wrapper.unmount();
    });

    it('ignores a response with no token rather than pointing the frame at nothing', async () => {
        post.mockResolvedValue({ data: { token: 42 } });

        const wrapper = mountPreview();
        await settle();

        expect(wrapper.find('iframe').exists()).toBe(false);
        wrapper.unmount();
    });

    it('offers the same url as an external link', async () => {
        const wrapper = mountPreview();
        await settle();

        const link = wrapper.get('a[target="_blank"]');

        expect(link.attributes('href')).toBe('/?preview=tok-1&template=saas');
        expect(link.attributes('rel')).toBe('noopener');
        wrapper.unmount();
    });

    it('exposes refresh() so the editor can republish immediately after a save', async () => {
        const wrapper = mountPreview();
        await settle();

        (wrapper.vm as unknown as { refresh: () => void }).refresh();
        await settle();

        expect(post).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it('drops an in-flight response once the component is gone', async () => {
        const wrapper = mountPreview();
        wrapper.unmount();
        await settle();

        expect(post).toHaveBeenCalledTimes(1);
    });

    it('announces its state to assistive technology', async () => {
        const wrapper = mountPreview();
        await settle();

        const regions = wrapper.findAll('[role="status"]');
        const own = regions[regions.length - 1];

        expect(regions.length).toBeGreaterThan(0);
        expect(own.attributes('aria-live')).toBe('polite');
        expect(own.classes()).toContain('sr-only');
        wrapper.unmount();
    });
});
