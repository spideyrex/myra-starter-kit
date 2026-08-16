import { createI18n } from 'vue-i18n';
import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';

/**
 * The example shells mount real reka-ui primitives (splitters, carousels,
 * scroll areas) that observe layout. jsdom ships none of those observers, so
 * they are stubbed once per spec rather than the components being stubbed out —
 * a stubbed splitter would make the pane a11y assertions meaningless.
 */
export function installExampleEnv(): void {
    class Observer {
        observe(): void {}
        unobserve(): void {}
        disconnect(): void {}
        takeRecords(): unknown[] { return []; }
    }

    for (const name of ['ResizeObserver', 'IntersectionObserver', 'MutationObserver'] as const) {
        if (!(name in globalThis)) {
            (globalThis as Record<string, unknown>)[name] = Observer;
        }
    }

    if (!window.matchMedia) {
        window.matchMedia = ((query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addListener() {},
            removeListener() {},
            addEventListener() {},
            removeEventListener() {},
            dispatchEvent: () => false,
        })) as typeof window.matchMedia;
    }

    if (!Element.prototype.scrollIntoView) {
        Element.prototype.scrollIntoView = () => {};
    }
}

export function exampleI18n(locale = 'en') {
    return createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en, ms, zh } });
}
