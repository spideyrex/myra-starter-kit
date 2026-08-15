import { describe, expect, it } from 'vitest';
import { TextInput, Toggle } from '@/composables/useFormSchema';

const Icon = { name: 'Icon', render: () => null };

describe('BaseField hint cluster', () => {
    it('defaults hintColor to muted and leaves the rest undefined', () => {
        const props = TextInput.make('slug').toProps();
        expect(props.hintColor).toBe('muted');
        expect(props.hint).toBeUndefined();
        expect(props.hintIcon).toBeUndefined();
        expect(props.hintAction).toBeUndefined();
    });

    it('emits hint, hintIcon, hintIconTooltip, hintColor and hintAction', () => {
        const onClick = (form: Record<string, any>) => { form.slug = 'x'; };
        const props = TextInput.make('slug')
            .hint('Lowercase, hyphen-separated.')
            .hintIcon(Icon as any, 'Used in the public URL')
            .hintColor('warning')
            .hintAction({ label: 'Regenerate', onClick })
            .toProps();

        expect(props.hint).toBe('Lowercase, hyphen-separated.');
        expect(props.hintIcon).toBe(Icon);
        expect(props.hintIconTooltip).toBe('Used in the public URL');
        expect(props.hintColor).toBe('warning');
        expect(props.hintAction?.label).toBe('Regenerate');

        const form: Record<string, any> = {};
        props.hintAction?.onClick(form);
        expect(form.slug).toBe('x');
    });

    it('hintIcon without a tooltip leaves hintIconTooltip unset', () => {
        const props = TextInput.make('slug').hintIcon(Icon as any).toProps();
        expect(props.hintIcon).toBe(Icon);
        expect(props.hintIconTooltip).toBeUndefined();
    });

    it('the hint cluster is available on every field type, not just text', () => {
        const props = Toggle.make('newsletter').hint('Weekly digest').hintColor('info').toProps();
        expect(props.type).toBe('switch');
        expect(props.hint).toBe('Weekly digest');
        expect(props.hintColor).toBe('info');
    });

    it('disabled() is back-compatible and disabled(false) re-enables', () => {
        expect(TextInput.make('a').toProps().disabled).toBe(false);
        expect(TextInput.make('a').disabled().toProps().disabled).toBe(true);
        expect(TextInput.make('a').disabled(true).disabled(false).toProps().disabled).toBe(false);
    });
});
