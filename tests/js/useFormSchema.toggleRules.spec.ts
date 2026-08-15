import { describe, expect, it } from 'vitest';
import { CheckboxList, TextInput, ToggleButtons, ToggleGroupField } from '@/composables/useFormSchema';

describe('toggle min/max produce Laravel rules', () => {
    it('derives array + min + max for a multiple toggle group', () => {
        const props = ToggleGroupField.make('channels').multiple().min(1).max(2).toProps();

        expect(props.rules).toEqual(['array', 'min:1', 'max:2']);
    });

    it('appends explicit rules after the auto-derived ones', () => {
        const props = ToggleButtons.make('channels')
            .multiple()
            .min(1)
            .max(2)
            .rules(['required'])
            .toProps();

        expect(props.rules).toEqual(['array', 'min:1', 'max:2', 'required']);
    });

    it('omits `array` for a single-value toggle group', () => {
        expect(ToggleGroupField.make('status').min(1).toProps().rules).toEqual(['min:1']);
    });

    it('emits nothing when no bounds and no explicit rules are set', () => {
        expect(ToggleGroupField.make('status').toProps().rules).toBeUndefined();
    });

    it('always treats a checkbox list as an array and carries its bounds', () => {
        const props = CheckboxList.make('tags').min(2).max(4).toProps();

        expect(props.rules).toEqual(['array', 'min:2', 'max:4']);
        expect(props.toggleMin).toBe(2);
        expect(props.toggleMax).toBe(4);
    });

    it('exposes rules() on every field, not just selection fields', () => {
        expect(TextInput.make('slug').rules(['required', 'max:64']).toProps().rules)
            .toEqual(['required', 'max:64']);
    });
});
