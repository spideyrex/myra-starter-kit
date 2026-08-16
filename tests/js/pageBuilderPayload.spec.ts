import { describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<span />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { siteSettings: { site_name: 'Acme Corp', logo_url: null } } }),
    router: { get: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@/composables/useThemeColors', () => ({ useThemeColors: () => ({}) }));

import en from '@/i18n/locales/en.json';
import Home from '@/Pages/Home.vue';

/**
 * THE REAL PAYLOAD.
 *
 * tests/Feature/PageBuilder/EndToEndPageBuilderTest saves a block list through
 * the real admin endpoint, reads the props the real public homepage shipped,
 * and ASSERTS this committed file against them: every value here is a value the
 * server emitted for that path (row ids excepted — those are ULIDs, restated as
 * row-N so the file is reproducible). The PHP suite fails if the two diverge.
 * Regenerate the full payload with MYRA_WRITE_FIXTURES=1.
 *
 * Every expectation below is DERIVED from the file — nothing is hardcoded — so
 * the spec keeps holding when the payload changes.
 */
import fixture from './fixtures/page-builder-blocks.json';

interface Row {
    id: string;
    type: string;
    variant?: Record<string, unknown>;
    data: Record<string, unknown>;
}

const rows = fixture.blocks as unknown as Row[];

// Warm every lazily-loaded template chunk once so the dispatcher's async
// wrapper resolves in a microtask instead of racing a module transform.
await Promise.all(
    Object.values(import.meta.glob('@/Pages/Public/Templates/*.vue')).map(load => load()),
);

async function settle(w: { html: () => string }): Promise<void> {
    for (let i = 0; i < 50 && w.html().trim() === ''; i++) {
        await flushPromises();
    }

    await flushPromises();
}

function mountHome(overrides: Record<string, unknown> = {}) {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    return mount(Home, {
        props: {
            settings: fixture.settings,
            authenticated: false,
            template: fixture.template,
            templateOptions: fixture.templateOptions,
            sectionOrder: fixture.sectionOrder,
            blocks: rows,
            ...overrides,
        } as any,
        global: { plugins: [i18n] },
    });
}

/** Text an author typed, as opposed to a URL, a storage path or an icon name. */
function authoredStrings(data: Record<string, unknown>): string[] {
    const out: string[] = [];

    const walk = (value: unknown, key: string) => {
        if (key === 'icon' || key.endsWith('_url') || key.endsWith('_path')) return;

        if (typeof value === 'string') {
            const text = value.trim();
            if (text !== '' && !text.startsWith('<')) out.push(text);

            return;
        }

        if (Array.isArray(value)) {
            for (const item of value) walk(item, key);

            return;
        }

        if (value && typeof value === 'object') {
            for (const [k, v] of Object.entries(value as Record<string, unknown>)) walk(v, k);
        }
    };

    walk(data, 'data');

    return out;
}

/** The row's headline, used to prove the saved ORDER survived to the DOM. */
function headline(row: Row): string {
    for (const key of ['title', 'heading', 'caption', 'alt']) {
        const value = row.data?.[key];
        if (typeof value === 'string' && value.trim() !== '') return value.trim();
    }

    return '';
}

describe('page builder — the saved payload renders on the public page', () => {
    it('has a payload to assert against at all', () => {
        expect(Array.isArray(rows)).toBe(true);
        expect(rows.length).toBeGreaterThan(0);

        for (const row of rows) {
            expect(typeof row.id, `row [${row.type}] has no id`).toBe('string');
            expect(row.id).not.toBe('');
            expect(typeof row.type).toBe('string');
        }
    });

    it('renders every authored string the server shipped', async () => {
        const w = mountHome();
        await settle(w);

        const text = w.text();

        expect(text.trim()).not.toBe('');

        for (const row of rows) {
            for (const authored of authoredStrings(row.data)) {
                expect(text, `[${row.type}] lost the authored string "${authored}"`).toContain(authored);
            }
        }
    });

    it('keeps the saved order', async () => {
        const w = mountHome();
        await settle(w);

        const text = w.text();
        const positions = rows
            .map(headline)
            .filter(h => h !== '')
            .map(h => text.indexOf(h));

        expect(positions.length).toBeGreaterThan(1);
        expect(positions.every(p => p >= 0)).toBe(true);

        const sorted = [...positions].sort((a, b) => a - b);

        expect(positions).toEqual(sorted);
    });

    it('renders one section element per saved row', async () => {
        const w = mountHome();
        await settle(w);

        expect(w.findAll('section').length).toBeGreaterThanOrEqual(rows.length);
    });

    it('does not blank the page when an unknown type is mixed into the real payload', async () => {
        const w = mountHome({
            blocks: [
                ...rows,
                { id: 'unknown-1', type: 'acme_not_installed', variant: {}, data: { title: 'Do not render me' } },
            ],
        });
        await settle(w);

        const text = w.text();

        expect(text.trim()).not.toBe('');
        expect(text).not.toContain('Do not render me');

        for (const row of rows) {
            const h = headline(row);
            if (h !== '') expect(text).toContain(h);
        }
    });

    it('falls back to the legacy settings path rather than a blank page for an empty list', async () => {
        const w = mountHome({ blocks: [] });
        await settle(w);

        expect(w.text().trim()).not.toBe('');
        expect(w.text()).toContain(fixture.settings.hero_title);
    });

    /**
     * Only shapes the server could actually ship. normalize() is total, so the
     * client never sees a non-list; what it CAN see is a row whose fields are
     * all at their defaults, or a row a future normaliser left thin.
     */
    it('survives a thin or empty list without blanking', async () => {
        const shapes: unknown[] = [
            undefined,
            [],
            [{}],
            [{ type: 'hero', data: {} }],
            [{ id: 'a', type: 'hero', variant: {}, data: {} }],
        ];

        for (const blocks of shapes) {
            const w = mountHome({ blocks });
            await settle(w);

            expect(w.html().trim(), `blocks=${JSON.stringify(blocks)} blanked the page`).not.toBe('');
        }
    });
});
