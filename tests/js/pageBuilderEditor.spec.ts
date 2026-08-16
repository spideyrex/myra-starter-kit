import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
    defaultForField, defaultsForListRow, ulid, useSectionList, VARIANT_DEFAULT,
    type SectionSchema,
} from '@/composables/useSectionList';

/**
 * The shape SectionRegistry::toClientSchema() emits, transcribed from the v2.7
 * type declarations. The PHP side proves the server really emits this; here it
 * is the input the editor has to survive.
 */
const SCHEMAS: SectionSchema[] = [
    {
        key: 'hero',
        labelKey: 'pageBuilder.sections.hero.label',
        descriptionKey: 'pageBuilder.sections.hero.description',
        icon: 'Rocket',
        group: 'content',
        titleField: 'title',
        maxPerPage: 1,
        variants: { align: ['center', 'split', 'left'], compact: 'bool' },
        fields: [
            { name: 'title', type: 'text', labelKey: 'pageBuilder.sections.hero.fields.title', required: false, default: '', max: 120 },
            { name: 'subtitle', type: 'textarea', labelKey: 'pageBuilder.sections.hero.fields.subtitle', required: false, default: '', max: 300 },
            { name: 'cta_url', type: 'url', labelKey: 'pageBuilder.sections.hero.fields.ctaUrl', required: false, default: '', max: null },
            { name: 'image_path', type: 'image', labelKey: 'pageBuilder.sections.hero.fields.image', required: false, default: '', max: null },
        ],
    },
    {
        key: 'features',
        labelKey: 'pageBuilder.sections.features.label',
        descriptionKey: 'pageBuilder.sections.features.description',
        icon: 'Grid3x3',
        group: 'content',
        titleField: 'title',
        maxPerPage: 0,
        variants: { columns: ['3', '2'], bare: 'bool' },
        fields: [
            { name: 'title', type: 'text', labelKey: 'pageBuilder.sections.features.fields.title', required: false, default: '', max: 120 },
            {
                name: 'items',
                type: 'list',
                labelKey: 'pageBuilder.sections.features.fields.items',
                required: false,
                default: [],
                max: 12,
                of: [
                    // No `options`: toClientSchema() attaches them for `select` only.
                    { name: 'icon', type: 'icon', labelKey: 'pageBuilder.sections.features.fields.icon', required: false, default: '', max: null },
                    { name: 'title', type: 'text', labelKey: 'pageBuilder.sections.features.fields.itemTitle', required: false, default: '', max: 80 },
                    { name: 'highlighted', type: 'bool', labelKey: 'pageBuilder.sections.features.fields.highlighted', required: false, default: false, max: null },
                ],
            },
        ],
    },
    {
        key: 'divider',
        labelKey: 'pageBuilder.sections.divider.label',
        descriptionKey: 'pageBuilder.sections.divider.description',
        icon: 'Minus',
        group: 'layout',
        titleField: null,
        maxPerPage: 0,
        variants: {},
        fields: [
            { name: 'style', type: 'select', labelKey: 'pageBuilder.sections.divider.fields.style', required: false, default: 'rule', max: null, options: ['rule', 'space'] },
            { name: 'size', type: 'select', labelKey: 'pageBuilder.sections.divider.fields.size', required: false, default: 'md', max: null, options: ['sm', 'md', 'lg'] },
            { name: 'weight', type: 'number', labelKey: 'pageBuilder.sections.divider.fields.weight', required: false, default: 2, max: null },
        ],
    },
];

/** The translator is injected, so the composable never renders English of its own. */
const t = (key: string) => key;

function build(initial: unknown = [], maxBlocks = 100) {
    return useSectionList({ schemas: SCHEMAS, initial, t, maxBlocks });
}

describe('ulid', () => {
    it('is 26 Crockford characters and does not repeat', () => {
        const ids = new Set(Array.from({ length: 500 }, () => ulid()));

        expect(ids.size).toBe(500);
        for (const id of ids) expect(id).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
    });
});

describe('defaultsFor', () => {
    it('is type-aware and never returns an empty string for bool, list, number or select', () => {
        const list = build();

        expect(list.defaultsFor('hero')).toEqual({
            title: '', subtitle: '', cta_url: '', image_path: '',
        });

        const features = list.defaultsFor('features');
        expect(features.items).toEqual([]);
        expect(Array.isArray(features.items)).toBe(true);

        const divider = list.defaultsFor('divider');
        expect(divider.style).toBe('rule');
        expect(divider.size).toBe('md');
        expect(divider.weight).toBe(2);
        expect(divider.style).not.toBe('');
        expect(divider.size).not.toBe('');
    });

    it('falls back to the FIRST option when a select declares no usable default', () => {
        expect(defaultForField({
            name: 'style', type: 'select', labelKey: 'x', required: false, default: '', max: null,
            options: ['rule', 'space'],
        })).toBe('rule');

        expect(defaultForField({
            name: 'style', type: 'select', labelKey: 'x', required: false, default: 'nope', max: null,
            options: ['rule', 'space'],
        })).toBe('rule');
    });

    it('gives bool false and list [] even when the server sent nothing usable', () => {
        expect(defaultForField({ name: 'b', type: 'bool', labelKey: 'x', required: false, default: null, max: null })).toBe(false);
        expect(defaultForField({ name: 'l', type: 'list', labelKey: 'x', required: false, default: 'oops', max: null })).toEqual([]);
        expect(defaultForField({ name: 'n', type: 'number', labelKey: 'x', required: false, default: 'oops', max: null })).toBe(0);
    });

    it('builds a list row from the sub-declaration', () => {
        const items = SCHEMAS[1].fields[1];

        // `icon` defaults to '' exactly as SectionField::defaultValue() does;
        // SectionFieldControl maps that to its own sentinel, so reka-ui never
        // sees an empty SelectItem value.
        expect(defaultsForListRow(items)).toEqual({ icon: '', title: '', highlighted: false });
    });

    it('returns [] for an unknown type rather than throwing', () => {
        expect(build().defaultsFor('nope_not_real')).toEqual({});
    });
});

describe('add', () => {
    it('assigns a ULID, starts enabled and leaves variant empty so the template still decides', () => {
        const list = build();
        const row = list.add('hero');

        expect(row).not.toBeNull();
        expect(row!.id).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
        expect(row!.enabled).toBe(true);
        expect(row!.variant).toEqual({});
        expect(list.selectedId.value).toBe(row!.id);
    });

    it('honours maxPerPage', () => {
        const list = build();

        expect(list.canAdd('hero')).toBe(true);
        list.add('hero');
        expect(list.canAdd('hero')).toBe(false);
        expect(list.add('hero')).toBeNull();
        expect(list.rows.value).toHaveLength(1);

        // maxPerPage 0 means unlimited.
        list.add('features');
        list.add('features');
        expect(list.countOf('features')).toBe(2);
    });

    it('honours the page cap', () => {
        const list = build([], 2);

        list.add('features');
        list.add('features');

        expect(list.atLimit.value).toBe(true);
        expect(list.add('features')).toBeNull();
    });

    it('inserts at the requested index', () => {
        const list = build();
        const a = list.add('features')!;
        const b = list.add('features')!;
        const inserted = list.add('divider', 1)!;

        expect(list.rows.value.map(r => r.id)).toEqual([a.id, inserted.id, b.id]);
    });
});

describe('duplicate', () => {
    it('deep-clones directly below with a NEW id and no shared reference', () => {
        const list = build();
        const source = list.add('features')!;
        list.update(source.id, 'items', [{ icon: 'Zap', title: 'One', highlighted: false }]);

        const copy = list.duplicate(source.id)!;

        expect(copy.id).not.toBe(source.id);
        expect(list.rows.value.map(r => r.id)).toEqual([source.id, copy.id]);
        expect(copy.data.items).toEqual([{ icon: 'Zap', title: 'One', highlighted: false }]);

        list.update(copy.id, 'items', []);
        expect((list.rows.value[0].data.items as unknown[]).length).toBe(1);
    });

    it('refuses when the type is already at maxPerPage', () => {
        const list = build();
        const hero = list.add('hero')!;

        expect(list.duplicate(hero.id)).toBeNull();
    });
});

describe('identity', () => {
    it('keeps collapse state on the SAME row when an earlier row is removed', () => {
        const list = build();
        const first = list.add('features')!;
        const second = list.add('features')!;

        list.toggleCollapsed(second.id);
        expect(list.isCollapsed(second.id)).toBe(true);
        expect(list.isCollapsed(first.id)).toBe(false);

        list.remove(first.id);

        // An index-keyed collapse map would now report the surviving row as open.
        expect(list.rows.value.map(r => r.id)).toEqual([second.id]);
        expect(list.isCollapsed(second.id)).toBe(true);
    });

    it('keeps collapse state on the SAME row across a move', () => {
        const list = build();
        const a = list.add('features')!;
        const b = list.add('divider')!;

        list.toggleCollapsed(a.id);
        list.move(a.id, 1);

        expect(list.rows.value.map(r => r.id)).toEqual([b.id, a.id]);
        expect(list.isCollapsed(a.id)).toBe(true);
        expect(list.isCollapsed(b.id)).toBe(false);
    });

    it('forgets a removed row so a later row can never inherit its state', () => {
        const list = build();
        const a = list.add('features')!;

        list.toggleCollapsed(a.id);
        list.remove(a.id);
        const b = list.add('features')!;

        expect(list.isCollapsed(b.id)).toBe(false);
    });

    it('clamps a move rather than throwing, and ignores an unknown id', () => {
        const list = build();
        const a = list.add('features')!;
        const b = list.add('divider')!;

        list.move(a.id, 99);
        expect(list.rows.value.map(r => r.id)).toEqual([b.id, a.id]);

        list.move('not-a-row', 0);
        expect(list.rows.value.map(r => r.id)).toEqual([b.id, a.id]);
    });
});

describe('undo / redo', () => {
    it('restores the exact array', () => {
        const list = build();
        list.add('hero');
        const afterOne = JSON.stringify(list.rows.value);

        list.add('features');
        const afterTwo = JSON.stringify(list.rows.value);

        expect(list.canUndo.value).toBe(true);
        list.undo();
        expect(JSON.stringify(list.rows.value)).toBe(afterOne);

        expect(list.canRedo.value).toBe(true);
        list.redo();
        expect(JSON.stringify(list.rows.value)).toBe(afterTwo);
    });

    it('is a no-op at either end', () => {
        const list = build();

        expect(list.canUndo.value).toBe(false);
        list.undo();
        expect(list.rows.value).toEqual([]);

        expect(list.canRedo.value).toBe(false);
        list.redo();
        expect(list.rows.value).toEqual([]);
    });

    it('rings at 50 entries', () => {
        const list = build();
        for (let i = 0; i < 60; i++) list.add('features');

        let undone = 0;
        while (list.canUndo.value) {
            list.undo();
            undone++;
            if (undone > 200) break;
        }

        expect(undone).toBe(50);
        expect(list.rows.value).toHaveLength(10);
    });

    it('drops the redo stack once a new edit lands', () => {
        const list = build();
        list.add('hero');
        list.add('features');
        list.undo();

        expect(list.canRedo.value).toBe(true);
        list.add('divider');
        expect(list.canRedo.value).toBe(false);
    });
});

describe('dirty tracking', () => {
    it('goes dirty on an edit and clean again on undo', () => {
        const list = build();

        expect(list.dirty.value).toBe(false);
        const row = list.add('hero')!;
        expect(list.dirty.value).toBe(true);

        list.undo();
        expect(list.dirty.value).toBe(false);

        list.add('hero');
        expect(row.id).not.toBe('');
    });

    it('discard returns to the last saved list', () => {
        const list = build();
        const kept = list.add('features')!;
        list.markSaved();

        list.add('divider');
        expect(list.rows.value).toHaveLength(2);

        list.discard();
        expect(list.rows.value.map(r => r.id)).toEqual([kept.id]);
        expect(list.dirty.value).toBe(false);
    });

    it('markSaved adopts the server payload, so server-assigned ids win', () => {
        const list = build();
        list.add('hero');

        list.markSaved([{ id: 'SERVERID', type: 'hero', enabled: true, variant: {}, data: { title: 'From the server' } }]);

        expect(list.rows.value.map(r => r.id)).toEqual(['SERVERID']);
        expect(list.dirty.value).toBe(false);
    });
});

describe('variants', () => {
    it('stores an override and removes the key on the default sentinel', () => {
        const list = build();
        const row = list.add('hero')!;

        list.updateVariant(row.id, 'align', 'split');
        expect(list.rows.value[0].variant).toEqual({ align: 'split' });

        list.updateVariant(row.id, 'compact', true);
        expect(list.rows.value[0].variant).toEqual({ align: 'split', compact: true });

        list.updateVariant(row.id, 'align', VARIANT_DEFAULT);
        expect(list.rows.value[0].variant).toEqual({ compact: true });
    });
});

describe('adopting a stored list', () => {
    it('drops malformed rows, synthesises missing ids and never throws', () => {
        const list = build([
            null,
            'a string',
            { type: '' },
            { type: 'hero', data: 'not a map' },
            { type: 'features', enabled: false, variant: 'nope', data: { title: 'Kept' } },
            { id: '', type: 'divider' },
        ]);

        expect(list.rows.value).toHaveLength(3);
        expect(list.rows.value.map(r => r.type)).toEqual(['hero', 'features', 'divider']);
        expect(list.rows.value[0].data).toEqual({});
        expect(list.rows.value[1].enabled).toBe(false);
        expect(list.rows.value[1].variant).toEqual({});
        for (const row of list.rows.value) expect(row.id).not.toBe('');
    });

    it('survives a non-list entirely', () => {
        expect(build(null).rows.value).toEqual([]);
        expect(build('nope').rows.value).toEqual([]);
        expect(build({ hero: true }).rows.value).toEqual([]);
        expect(build(42).rows.value).toEqual([]);
    });

    it('truncates at the page cap', () => {
        const many = Array.from({ length: 500 }, () => ({ type: 'features' }));

        expect(build(many).rows.value).toHaveLength(100);
    });

    it('keeps an unknown type so a temporarily absent package cannot destroy content', () => {
        const list = build([{ id: 'X', type: 'from_a_package', data: { anything: 1 } }]);

        expect(list.rows.value).toHaveLength(1);
        expect(list.schemaOf('from_a_package')).toBeUndefined();
        expect(list.payload()[0].data).toEqual({ anything: 1 });
    });
});

describe('titleOf', () => {
    it('falls back to the type label and truncates a long title', () => {
        const list = build();
        const row = list.add('hero')!;

        expect(list.titleOf(list.rows.value[0])).toBe('pageBuilder.sections.hero.label');

        list.update(row.id, 'title', 'x'.repeat(200));
        const title = list.titleOf(list.rows.value[0]);

        expect(title.length).toBeLessThanOrEqual(48);
        expect(title.endsWith('…')).toBe(true);
    });

    it('uses the bare type for an unknown section', () => {
        const list = build([{ id: 'X', type: 'from_a_package' }]);

        expect(list.titleOf(list.rows.value[0])).toBe('from_a_package');
    });

    it('ignores a titleField whose value is not a string', () => {
        const list = build([{ id: 'X', type: 'hero', data: { title: { nested: true } } }]);

        expect(list.titleOf(list.rows.value[0])).toBe('pageBuilder.sections.hero.label');
    });
});

/**
 * Source-level guards, in the shape commandPalette.a11y.spec.ts already uses for
 * the layout: both defects here are about what the page must NOT do, and about a
 * link existing at all — neither survives a mount-based assertion.
 */
describe('builder shortcut and entry point (regression guard)', () => {
    const builder = readFileSync(resolve(__dirname, '../../resources/js/Pages/Admin/Landing/Builder.vue'), 'utf8');
    const layout = readFileSync(resolve(__dirname, '../../resources/js/Layouts/AuthenticatedLayout.vue'), 'utf8');

    it('leaves Cmd/Ctrl+K to the layout palette', () => {
        expect(builder).not.toMatch(/=== 'k'/);
        expect(builder.match(/addEventListener\('keydown'/g) ?? []).toHaveLength(1);
    });

    it('reaches the catalogue through the shared command registry instead', () => {
        expect(builder).toContain('useCommandScope');
        expect(builder).toContain('pagebuilder.addSection');
    });

    it('is reachable: the sidebar links at the builder route', () => {
        expect(layout).toContain('admin.landing.builder.index');
    });
});
