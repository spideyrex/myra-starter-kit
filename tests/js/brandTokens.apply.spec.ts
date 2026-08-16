import { describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { brandScopeStyle, readBrandMeta, useBrand } from '@/composables/useBrand';
import payload from './fixtures/brand-tokens.json';

/**
 * The fixture is written by tests/Feature/Brand/BrandFixtureTest from the REAL
 * server resolver, so this consumes an actual payload, not a hand-authored one.
 */

const page = { props: {} as Record<string, unknown> };

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

describe('brand tokens reach the client intact', () => {
    it('every emitted light token is a resolved colour, never a raw hex', () => {
        for (const [name, value] of Object.entries(payload.light)) {
            if (name === '--radius' || name.startsWith('--font-')) continue;

            expect(value, name).toMatch(/^oklch\(/);
        }
    });

    it('the dark set covers the same tokens as the light set', () => {
        expect(Object.keys(payload.dark).sort()).toEqual(Object.keys(payload.light).sort());
    });

    it('reads the brand out of the server-embedded meta tag', () => {
        document.head.innerHTML = `<meta name="brand" content='${JSON.stringify(payload.brand)}'>`;

        expect(readBrandMeta()?.name).toBe(payload.brand.name);

        document.head.innerHTML = '';
        expect(readBrandMeta()).toBeNull();
    });

    it('survives a malformed meta tag without throwing', () => {
        document.head.innerHTML = '<meta name="brand" content="{not json">';

        expect(readBrandMeta()).toBeNull();

        document.head.innerHTML = '';
    });

    it('useBrand prefers the shared Inertia prop', () => {
        page.props = { brand: payload.brand };

        const scope = effectScope();
        scope.run(() => {
            const { name, initial } = useBrand();

            expect(name.value).toBe(payload.brand.name);
            expect(initial.value).toBe(payload.brand.initial);
        });
        scope.stop();
    });

    it('brandScopeStyle scopes the same tokens the server emits', () => {
        const style = brandScopeStyle(payload.brand as any);

        expect(style['--primary']).toBe(payload.brand.palette.primary);
        expect(style['--radius']).toBe(payload.brand.radius);
    });

    it('the fixture carries a non-default brand, so a regression is visible', () => {
        expect(payload.brand.enabled).toBe(true);
        expect(payload.brand.palette.primary).not.toBe('#18181b');
    });
});
