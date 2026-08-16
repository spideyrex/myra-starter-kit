import { describe, expect, it } from 'vitest';

// THE REAL PAYLOAD: written by
// tests/Feature/Dashboard/DashboardLayoutRealPayloadTest from the props the
// server actually rendered. No hand-written stand-in.
import fixture from './fixtures/dashboard-layout.json';
import {
    applyLayout, hiddenByLayout, toLayoutEntries,
    type DashboardLayoutPayload, type WidgetSchema,
} from '@/composables/useDashboardWidgets';

const layout = fixture.layout as unknown as DashboardLayoutPayload;

function schema(key: string, extra: Partial<WidgetSchema> = {}): WidgetSchema {
    return { key, type: 'stat', colSpan: 1, title: key, ...extra } as WidgetSchema;
}

/** The three widget keys the fixture's entries name, in fixture order. */
const entryKeys = layout.entries.map(e => e.key);

describe('applyLayout against the server payload', () => {
    it('is identity by reference when there is no layout', () => {
        const schemas = [schema('a'), schema('b')];

        expect(applyLayout(schemas, null)).toBe(schemas);
        expect(applyLayout(schemas, undefined)).toBe(schemas);
    });

    it('orders the declared widgets by the stored entry order', () => {
        const schemas = [schema(entryKeys[1]), schema(entryKeys[0])];

        expect(applyLayout(schemas, layout).map(w => w.key)).toEqual([entryKeys[0], entryKeys[1]]);
    });

    it('drops an entry naming a widget that was never declared', () => {
        const rendered = applyLayout([schema(entryKeys[0])], layout);

        expect(rendered.map(w => w.key)).toEqual([entryKeys[0]]);
        expect(rendered.map(w => w.key)).not.toContain(entryKeys[1]);
    });

    it('cannot add a widget, only reorder what survived', () => {
        expect(applyLayout([], layout)).toHaveLength(0);
    });

    it('excludes a hidden widget from the render list and reports it as hidden', () => {
        const hiddenKey = layout.entries.find(e => e.hidden === true)!.key;
        const schemas = [schema(entryKeys[0]), schema(hiddenKey)];

        expect(applyLayout(schemas, layout).map(w => w.key)).toEqual([entryKeys[0]]);
        expect(hiddenByLayout(schemas, layout).map(w => w.key)).toEqual([hiddenKey]);
    });

    it('applies the stored per-breakpoint span from the fixture', () => {
        const entry = layout.entries.find(e => typeof e.colSpan === 'object')!;
        const rendered = applyLayout([schema(entry.key)], layout);

        expect(rendered[0].colSpan).toEqual(entry.colSpan);
    });

    it('keeps a widget with no entry, appended after the entry-ordered ones', () => {
        const schemas = [schema('brand_new'), schema(entryKeys[0])];

        expect(applyLayout(schemas, layout).map(w => w.key)).toEqual([entryKeys[0], 'brand_new']);
    });

    it('clamps a hostile span rather than emitting a class outside the grid', () => {
        const hostile: DashboardLayoutPayload = {
            version: 1,
            entries: [{ key: 'a', order: 0, colSpan: 99, rowSpan: 99 }],
            instances: [],
        };

        const rendered = applyLayout([schema('a')], hostile);

        expect(rendered[0].colSpan).toBe(4);
        expect(rendered[0].rowSpan).toBe(4);
    });

    it('clamps each breakpoint of an object span independently', () => {
        const hostile: DashboardLayoutPayload = {
            version: 1,
            entries: [{ key: 'a', order: 0, colSpan: { sm: 9, lg: 9 } }],
            instances: [],
        };

        expect(applyLayout([schema('a')], hostile)[0].colSpan).toEqual({ sm: 2, lg: 4 });
    });

    it('returns the input array unchanged when a schema throws', () => {
        const exploding = {
            type: 'stat',
            colSpan: 1,
            get key(): string { throw new Error('boom'); },
        } as unknown as WidgetSchema;

        const schemas = [exploding];

        expect(applyLayout(schemas, layout)).toBe(schemas);
        expect(hiddenByLayout(schemas, layout)).toEqual([]);
    });

    it('never mutates the input schemas', () => {
        const original = schema(entryKeys[1]);
        const before = { ...original };

        applyLayout([original], layout);

        expect(original).toEqual(before);
    });
});

describe('toLayoutEntries', () => {
    it('emits one entry per schema in render order, and never an instance', () => {
        const entries = toLayoutEntries([schema('a'), schema('b', { colSpan: 99 })]);

        expect(entries.map(e => e.key)).toEqual(['a', 'b']);
        expect(entries.map(e => e.order)).toEqual([0, 1]);
        expect(entries[1].colSpan).toBe(4);
        expect(entries.every(e => e.hidden === false)).toBe(true);
        expect(JSON.stringify(entries)).not.toContain('catalogue');
    });
});

describe('the fixture itself', () => {
    it('carries a server-resolved instance whose binding names the report', () => {
        expect(layout.instances).toHaveLength(1);
        expect((layout.instances[0] as any).binding.report).toBe('users');
        expect((layout.instances[0] as any).catalogue).toBe('trend');
    });

    it('never exposes a SQL identifier or a table name in the catalogue', () => {
        const json = JSON.stringify(fixture.catalogue);

        expect(json).not.toContain('"column"');
        expect(json).not.toContain('"model"');
        expect(json).not.toContain('"table"');
    });
});
