import { describe, expect, it } from 'vitest';
import {
    CheckboxColumn, ColorColumn, SelectColumn, TextInputColumn, ToggleColumn,
} from '@/composables/useTableSchema';

describe('ColorColumn', () => {
    it('defaults to a copyable square swatch that shows its value', () => {
        const s = ColorColumn.make('brand_color').toSchema() as any;
        expect(s.type).toBe('color');
        expect(s.label).toBe('Brand Color');
        expect(s.copyable).toBe(true);
        expect(s.swatchShowValue).toBe(true);
        expect(s.swatchSize).toBe(16);
        expect(s.swatchShape).toBe('square');
    });

    it('swatchOnly() hides the value and circular() rounds it', () => {
        const s = ColorColumn.make('brand_color').swatchOnly().circular().swatchSize(24).toSchema() as any;
        expect(s.swatchShowValue).toBe(false);
        expect(s.swatchShape).toBe('circle');
        expect(s.swatchSize).toBe(24);
    });
});

describe('CheckboxColumn', () => {
    it('carries the shared inline-editing keys', () => {
        const s = CheckboxColumn.make('is_featured')
            .updateRoute('admin.products.inline-update')
            .permission('products.edit')
            .rowLabel(row => `Feature ${row.name}`)
            .disabledWhen(row => row.archived_at !== null)
            .confirmWhen((row, v) => (v && row.stock === 0 ? 'Sure?' : false))
            .indeterminateWhen(row => row.stock === 0)
            .toSchema() as any;

        expect(s.type).toBe('checkbox');
        expect(s.updateRoute).toBe('admin.products.inline-update');
        expect(s.updateField).toBe('is_featured');
        expect(s.optimistic).toBe(true);
        expect(s.permission).toBe('products.edit');
        expect(s.debounceMs).toBe(500);
        expect(s.rowLabelFn({ name: 'Widget' })).toBe('Feature Widget');
        expect(s.disabledFn({ archived_at: '2026-01-01' })).toBe(true);
        expect(s.confirmFn({ stock: 0 }, true)).toBe('Sure?');
        expect(s.confirmFn({ stock: 4 }, true)).toBe(false);
        expect(s.indeterminateFn({ stock: 0 })).toBe(true);
    });

    it('field() overrides the written column and optimistic(false) is emitted', () => {
        const s = CheckboxColumn.make('featured').field('is_featured').optimistic(false).toSchema() as any;
        expect(s.updateField).toBe('is_featured');
        expect(s.optimistic).toBe(false);
    });
});

describe('inline-editable columns', () => {
    it('toggle, select and text input share the same base', () => {
        const toggle = ToggleColumn.make('is_active').updateRoute('r').toSchema() as any;
        const select = SelectColumn.make('status').options({ a: 'A' }).updateRoute('r').toSchema() as any;
        const input = TextInputColumn.make('stock').debounce(300).toSchema() as any;

        expect(toggle.updateRoute).toBe('r');
        expect(select.updateRoute).toBe('r');
        expect(select.options).toEqual([{ label: 'A', value: 'a' }]);
        expect(input.debounceMs).toBe(300);
    });

    it('keeps onUpdate() as the escape hatch', () => {
        let seen: any = null;
        const s = ToggleColumn.make('is_active').onUpdate((row, v) => { seen = [row.id, v]; }).toSchema() as any;
        expect(s.updateRoute).toBeUndefined();
        s.onUpdateFn({ id: 7 }, true);
        expect(seen).toEqual([7, true]);
    });
});
