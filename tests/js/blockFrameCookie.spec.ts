import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

import en from '@/i18n/locales/en.json';
import { SIDEBAR_COOKIE_NAME } from '@/components/ui/sidebar/utils';
import BlockFrame from '@/components/admin/blocks/BlockFrame.vue';

/**
 * The frame runs in an iframe that shares the admin's cookie jar, and every
 * sidebar block mounts a SidebarProvider that persists SIDEBAR_COOKIE_NAME at
 * path=/. If that write reached the jar, previewing a block would silently
 * re-collapse the real admin sidebar.
 */
function mountFrame() {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    return mount(BlockFrame, {
        props: { entryFile: 'not-a-block/Page.vue' },
        global: { plugins: [i18n] },
    });
}

describe('block frame cookie shield', () => {
    it('drops the sidebar cookie the previewed block writes', () => {
        document.cookie = 'unrelated_pref=keep-me; path=/';

        const w = mountFrame();

        document.cookie = `${SIDEBAR_COOKIE_NAME}=false; path=/; max-age=604800`;

        expect(document.cookie).not.toContain(`${SIDEBAR_COOKIE_NAME}=`);
        expect(document.cookie).toContain('unrelated_pref=keep-me');

        w.unmount();
    });

    it('shields that one name only, and only while it is mounted', () => {
        const w = mountFrame();

        document.cookie = 'another_pref=still-written; path=/';
        expect(document.cookie).toContain('another_pref=still-written');

        w.unmount();

        document.cookie = `${SIDEBAR_COOKIE_NAME}=false; path=/`;
        expect(document.cookie).toContain(`${SIDEBAR_COOKIE_NAME}=false`);

        // Leave the jar as it was found.
        document.cookie = `${SIDEBAR_COOKIE_NAME}=; path=/; max-age=0`;
    });
});
