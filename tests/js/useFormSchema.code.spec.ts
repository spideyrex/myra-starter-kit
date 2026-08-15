import { describe, expect, it } from 'vitest';
import { CodeEditor } from '@/composables/useFormSchema';

describe('CodeEditor field', () => {
    it('registers the code type', () => {
        expect(CodeEditor.make('payload').toProps().type).toBe('code');
    });

    it('ships the documented defaults', () => {
        const props = CodeEditor.make('payload').toProps();
        expect(props.codeLanguage).toBe('plaintext');
        expect(props.codeLineNumbers).toBe(true);
        expect(props.codeWrap).toBe(true);
        expect(props.codeReadOnly).toBe(false);
        // a11y regression guard: Tab must keep traversing the form by default
        expect(props.codeIndentWithTab).toBe(false);
        expect(props.codeAutocomplete).toBe(false);
        expect(props.codeCopyable).toBe(false);
        expect(props.codeTabSize).toBe(2);
        expect(props.codeMinHeight).toBe('12rem');
        expect(props.codeMaxHeight).toBeUndefined();
        expect(props.codeFilename).toBeUndefined();
    });

    it('round-trips every setter', () => {
        const props = CodeEditor.make('payload')
            .language('json')
            .lineNumbers(false)
            .wrap(false)
            .readOnly()
            .indentWithTab()
            .autocomplete()
            .copyable()
            .tabSize(4)
            .minHeight('20rem')
            .maxHeight('40rem')
            .filename('payload.json')
            .toProps();

        expect(props).toMatchObject({
            codeLanguage: 'json',
            codeLineNumbers: false,
            codeWrap: false,
            codeReadOnly: true,
            codeIndentWithTab: true,
            codeAutocomplete: true,
            codeCopyable: true,
            codeTabSize: 4,
            codeMinHeight: '20rem',
            codeMaxHeight: '40rem',
            codeFilename: 'payload.json',
        });
    });

    it('inherits the hint cluster from BaseField', () => {
        const props = CodeEditor.make('payload').hint('Sent verbatim.').hintColor('info').toProps();
        expect(props.hint).toBe('Sent verbatim.');
        expect(props.hintColor).toBe('info');
    });
});
