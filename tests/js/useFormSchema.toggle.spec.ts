import { describe, expect, it } from 'vitest';
import { ToggleButtons, ToggleGroupField } from '@/composables/useFormSchema';

const Icon = { name: 'Icon', render: () => null };

describe('ToggleGroupField / ToggleButtons', () => {
    it('keeps the toggle-group type literal — no new literal is introduced', () => {
        expect(ToggleButtons.make('status').toProps().type).toBe('toggle-group');
        expect(ToggleGroupField.make('status').toProps().type).toBe('toggle-group');
        expect(ToggleButtons.make('status')).toBeInstanceOf(ToggleGroupField);
    });

    it('normalises a record, a SelectOption[] and a ToggleOption[] to the same shape', () => {
        const fromRecord = ToggleButtons.make('a').options({ a: 'A' }).toProps().toggleOptions;
        const fromPairs = ToggleButtons.make('a').options([{ value: 'a', label: 'A' }]).toProps().toggleOptions;
        const fromRich = ToggleButtons.make('a')
            .options([{ value: 'a', label: 'A', icon: Icon as any, color: 'success' }])
            .toProps().toggleOptions;

        expect(fromRecord).toEqual([{ value: 'a', label: 'A' }]);
        expect(fromPairs?.[0]).toMatchObject({ value: 'a', label: 'A' });
        expect(fromRich?.[0]).toMatchObject({ value: 'a', label: 'A', color: 'success' });
        expect(typeof fromRich?.[0].value).toBe('string');
    });

    it('coerces numeric option values to strings', () => {
        const opts = ToggleButtons.make('a').options([{ value: 1 as any, label: 'One' }]).toProps().toggleOptions;
        expect(opts?.[0].value).toBe('1');
    });

    it('drops hidden options', () => {
        const opts = ToggleButtons.make('a')
            .options([{ value: 'a', label: 'A', hidden: true }, { value: 'b', label: 'B' }])
            .toProps().toggleOptions;
        expect(opts).toHaveLength(1);
        expect(opts?.[0].value).toBe('b');
    });

    it('boolean() writes two options with success/danger colours', () => {
        const props = ToggleButtons.make('is_featured').boolean('On', 'Off').toProps();
        expect(props.toggleOptions).toHaveLength(2);
        expect(props.toggleOptions?.[0]).toMatchObject({ value: '1', label: 'On', color: 'success' });
        expect(props.toggleOptions?.[1]).toMatchObject({ value: '0', label: 'Off', color: 'danger' });
        expect(props.toggleVariant).toBe('outline');
    });

    it('still populates the legacy options array (BC guard)', () => {
        const props = ToggleButtons.make('a').options({ a: 'A', b: 'B' }).toProps();
        expect(props.options).toEqual([{ label: 'A', value: 'a' }, { label: 'B', value: 'b' }]);
    });

    it('carries multiple, size, inline, columns, hideLabels, min and max', () => {
        const props = ToggleButtons.make('channels')
            .options({ email: 'Email', sms: 'SMS' })
            .multiple()
            .size('sm')
            .inline()
            .columns(3)
            .hideLabels()
            .min(1)
            .max(2)
            .toProps();

        expect(props).toMatchObject({
            toggleMultiple: true,
            toggleSize: 'sm',
            toggleInline: true,
            toggleColumns: 3,
            toggleHideLabels: true,
            toggleMin: 1,
            toggleMax: 2,
        });
    });

    it('defaults multiple to false and size to default', () => {
        const props = ToggleButtons.make('a').options({ a: 'A' }).toProps();
        expect(props.toggleMultiple).toBe(false);
        expect(props.toggleSize).toBe('default');
        expect(props.toggleHideLabels).toBe(false);
    });
});
