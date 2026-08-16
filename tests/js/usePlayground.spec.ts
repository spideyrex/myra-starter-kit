import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';

import en from '@/i18n/locales/en.json';
import { usePlayground, definePlayground, VIEWPORT_WIDTHS } from '@/composables/usePlayground';
import statCard from '@/demo/playgrounds/statCard';
import button from '@/demo/playgrounds/button';
import badge from '@/demo/playgrounds/badge';
import emptyState from '@/demo/playgrounds/emptyState';
import PlaygroundSnippet from '@/components/admin/playground/PlaygroundSnippet.vue';
import PlaygroundControls from '@/components/admin/playground/PlaygroundControls.vue';

const SPECS = [statCard, button, badge, emptyState];

function i18n(locale = 'en') {
    return createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { en } });
}

describe('usePlayground', () => {
    it('populates values from every control default', () => {
        const pg = usePlayground(button);

        expect(pg.values.value).toEqual({
            label: 'Save changes',
            variant: 'default',
            size: 'default',
            disabled: false,
        });
    });

    it('flows an edited control through bound and into the snippet', () => {
        const pg = usePlayground(button);

        pg.values.value = { ...pg.values.value, variant: 'destructive', disabled: true };

        expect(pg.bound.value.variant).toBe('destructive');
        expect(pg.snippet.value).toContain('variant="destructive"');
        expect(pg.snippet.value).toContain('disabled');
    });

    it('coerces a hostile value back inside the control it came from', () => {
        const pg = usePlayground(button);

        pg.values.value = { ...pg.values.value, variant: 'onerror=alert(1)' } as any;
        expect(pg.bound.value.variant).toBe('default');

        pg.values.value = { ...pg.values.value, label: 'x'.repeat(200) } as any;
        expect(String(pg.bound.value.label)).toHaveLength(40);
    });

    it('clamps a number control to its declared range', () => {
        const spec = definePlayground<{ n: number }>({
            key: 'n',
            titleKey: 'gallery.playground.props',
            component: { template: '<i />' },
            controls: [{ name: 'n', labelKey: 'gallery.playground.control.size', kind: 'number', default: 2, min: 1, max: 4 }],
            snippet: v => `n=${v.n}`,
        });
        const pg = usePlayground(spec);

        pg.values.value = { n: 99 };
        expect(pg.bound.value.n).toBe(4);

        pg.values.value = { n: -5 };
        expect(pg.bound.value.n).toBe(1);
    });

    it('resets every control back to its default', () => {
        const pg = usePlayground(statCard);
        const original = { ...pg.values.value };

        pg.values.value = { ...pg.values.value, title: 'Changed', color: 'rose' };
        expect(pg.values.value).not.toEqual(original);

        pg.reset();
        expect(pg.values.value).toEqual(original);
    });

    it('emits a stable snippet per language for a known control set', () => {
        const pg = usePlayground(badge);

        expect(pg.snippet.value).toBe('<Badge variant="default">Active</Badge>');

        pg.lang.value = 'ts';
        expect(pg.snippet.value).toContain("import { Badge } from '@/components/ui/badge';");

        pg.lang.value = 'php';
        expect(pg.snippet.value).toContain('->badge()');
    });

    it('never lets a throwing snippet function break the panel', () => {
        const spec = definePlayground<{ a: string }>({
            key: 'boom',
            titleKey: 'gallery.playground.props',
            component: { template: '<i />' },
            controls: [{ name: 'a', labelKey: 'gallery.playground.control.label', kind: 'text', default: 'x', maxLength: 4 }],
            snippet: () => { throw new Error('nope'); },
        });

        expect(usePlayground(spec).snippet.value).toBe('');
    });

    it('exposes the three real viewport widths', () => {
        expect(VIEWPORT_WIDTHS).toEqual({ sm: 375, md: 768, lg: 1280 });
    });
});

describe('playground specs', () => {
    it('every shipped spec mounts a real component and declares only known control kinds', () => {
        for (const spec of SPECS) {
            expect(spec.component).toBeTruthy();
            expect(spec.controls.length).toBeGreaterThan(0);

            for (const control of spec.controls) {
                expect(['boolean', 'number', 'text', 'select']).toContain(control.kind);
                expect(control.labelKey.startsWith('gallery.playground.')).toBe(true);
            }
        }
    });

    it('every control label key resolves in en.json', () => {
        for (const spec of SPECS) {
            for (const control of spec.controls) {
                const value = control.labelKey.split('.').reduce<any>((node, part) => node?.[part], en);
                expect(typeof value, `${control.labelKey} is missing`).toBe('string');
            }
        }
    });
});

describe('playground rendering', () => {
    it('renders the snippet as text, never as HTML', () => {
        const w = mount(PlaygroundSnippet, {
            props: { code: '<img src=x onerror="alert(1)">', lang: 'vue', copied: false },
            global: { plugins: [i18n()] },
        });

        expect(w.find('pre').text()).toContain('<img src=x onerror="alert(1)">');
        expect(w.find('pre img').exists()).toBe(false);
    });

    it('uses no v-html anywhere in the playground component tree', () => {
        const dir = resolve(__dirname, '../../resources/js/components/admin/playground');

        for (const file of ['PlaygroundSnippet.vue', 'PlaygroundControls.vue', 'PlaygroundStage.vue', 'PlaygroundPanel.vue']) {
            expect(readFileSync(resolve(dir, file), 'utf8')).not.toContain('v-html');
        }
    });

    it('gives every auto-rendered control a real label bound to its input', () => {
        const w = mount(PlaygroundControls, {
            props: { controls: badge.controls, values: { label: 'Active', variant: 'default' }, idPrefix: 'pg-badge' },
            global: { plugins: [i18n()], stubs: { Select: true, SelectTrigger: true, SelectContent: true, SelectItem: true, SelectValue: true } },
        });

        const labels = w.findAll('label');
        expect(labels.length).toBe(badge.controls.length);

        for (const label of labels) {
            const target = label.attributes('for');
            expect(target).toBeTruthy();
            expect(target!.startsWith('pg-badge-')).toBe(true);
        }
    });

    it('emits the edited control name and value rather than mutating the prop', async () => {
        const w = mount(PlaygroundControls, {
            props: { controls: badge.controls, values: { label: 'Active', variant: 'default' }, idPrefix: 'pg-badge' },
            global: { plugins: [i18n()], stubs: { Select: true, SelectTrigger: true, SelectContent: true, SelectItem: true, SelectValue: true } },
        });

        await w.find('input[type="text"]').setValue('Archived');

        expect(w.emitted('update')?.[0]).toEqual(['label', 'Archived']);
    });
});
