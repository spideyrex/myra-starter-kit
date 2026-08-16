import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import OfflineBanner from '@/components/admin/OfflineBanner.vue';
import { testI18n } from './helpers/i18n';
import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';

function mountBanner() {
    return mount(OfflineBanner, { global: { plugins: [testI18n()] } });
}

describe('OfflineBanner', () => {
    it('is a status, not an alert — being offline is a state', () => {
        const root = mountBanner().find('[role="status"]');

        expect(root.exists()).toBe(true);
        expect(root.attributes('aria-live')).toBe('polite');
        expect(mountBanner().find('[role="alert"]').exists()).toBe(false);
    });

    it('overrides its border, background and text colour in dark mode', () => {
        const classes = mountBanner().find('[role="status"]').classes();

        for (const prefix of ['dark:border-', 'dark:bg-', 'dark:text-']) {
            expect(classes.some((c) => c.startsWith(prefix))).toBe(true);
        }
    });

    it('renders no numeric data — nothing cached is presented as live', () => {
        expect(mountBanner().text()).not.toMatch(/\d/);
    });

    it('the message is translated in every locale, never hardcoded English', () => {
        const keys = ['banner', 'title', 'description', 'retry'] as const;

        for (const key of keys) {
            expect((en as any).assistant.offline[key]).toBeTruthy();
            expect((ms as any).assistant.offline[key]).toBeTruthy();
            expect((zh as any).assistant.offline[key]).toBeTruthy();
            expect((ms as any).assistant.offline[key]).not.toBe((en as any).assistant.offline[key]);
            expect((zh as any).assistant.offline[key]).not.toBe((en as any).assistant.offline[key]);
        }
    });

    it('the whole assistant namespace has en/ms/zh parity', () => {
        const flatten = (o: any, prefix = ''): string[] => Object.entries(o).flatMap(([k, v]) =>
            v && typeof v === 'object' ? flatten(v, `${prefix}${k}.`) : [`${prefix}${k}`]);

        const enKeys = flatten((en as any).assistant).sort();

        expect(flatten((ms as any).assistant).sort()).toEqual(enKeys);
        expect(flatten((zh as any).assistant).sort()).toEqual(enKeys);
    });
});
