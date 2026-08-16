import { describe, expect, it, vi } from 'vitest';
import { defineComponent, effectScope, h } from 'vue';
import { mount } from '@vue/test-utils';
import { useThemeColors } from '@/composables/useThemeColors';

const page = { props: { siteSettings: { theme: 'blue' } } as Record<string, any> };

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

const Host = defineComponent({
    setup() {
        useThemeColors();

        return () => h('div');
    },
});

describe('useThemeColors', () => {
    it('disconnects its MutationObserver when the scope is disposed', () => {
        const disconnect = vi.fn();
        const observe = vi.fn();
        const original = globalThis.MutationObserver;

        globalThis.MutationObserver = class {
            observe = observe;
            disconnect = disconnect;
            takeRecords = () => [];
        } as unknown as typeof MutationObserver;

        try {
            const wrapper = mount(Host);

            expect(observe).toHaveBeenCalledTimes(1);
            expect(disconnect).not.toHaveBeenCalled();

            wrapper.unmount();

            expect(disconnect).toHaveBeenCalledTimes(1);
        } finally {
            globalThis.MutationObserver = original;
        }
    });

    it('one observer per scope, so N mounts do not leak N-1 observers', () => {
        const disconnect = vi.fn();
        const original = globalThis.MutationObserver;

        globalThis.MutationObserver = class {
            observe = vi.fn();
            disconnect = disconnect;
            takeRecords = () => [];
        } as unknown as typeof MutationObserver;

        try {
            const wrappers = [mount(Host), mount(Host), mount(Host)];
            wrappers.forEach((w) => w.unmount());

            expect(disconnect).toHaveBeenCalledTimes(3);
        } finally {
            globalThis.MutationObserver = original;
        }
    });

    it('yields to the server-emitted tokens when <style id="myra-brand"> is present', () => {
        document.documentElement.removeAttribute('style');
        document.head.innerHTML = '<style id="myra-brand">:root{--primary:oklch(0.5 0.2 300)}</style>';

        const scope = effectScope();
        scope.run(() => useThemeColors());

        // applyTheme() bailed, so nothing was written to the inline style map.
        expect(document.documentElement.style.getPropertyValue('--primary')).toBe('');

        scope.stop();
        document.head.innerHTML = '';
    });

    it('still applies the legacy preset when the server emitted nothing', () => {
        document.documentElement.removeAttribute('style');
        document.head.innerHTML = '';

        const scope = effectScope();
        scope.run(() => useThemeColors());

        expect(document.documentElement.style.getPropertyValue('--primary')).toContain('oklch(');

        scope.stop();
    });
});
