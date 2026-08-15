import { describe, expect, it } from 'vitest';
import {
    CodeEntry,
    ColorEntry,
    type CodeLanguage,
    type EntrySchema,
} from '@/composables/useInfolistSchema';
import type { CodeLanguage as FieldCodeLanguage } from '@/composables/useFormSchema';

describe('ColorEntry', () => {
    it('emits the documented defaults', () => {
        const s = ColorEntry.make('primary_color').toSchema();
        expect(s.type).toBe('color');
        expect(s.copyable).toBe(true);
        expect(s.copyMessage).toBe('Copied to clipboard');
        expect(s.swatchShowValue).toBe(true);
        expect(s.swatchSize).toBe(24);
        expect(s.swatchShape).toBe('square');
    });

    it('swatchOnly() turns the value off and circular() switches the shape', () => {
        const s = ColorEntry.make('accent').swatchOnly().circular().swatchSize(40).toSchema();
        expect(s.swatchShowValue).toBe(false);
        expect(s.swatchShape).toBe('circle');
        expect(s.swatchSize).toBe(40);
    });

    it('circular(false) returns to a square', () => {
        expect(ColorEntry.make('c').circular().circular(false).toSchema().swatchShape).toBe('square');
    });

    it('inherits the shared BaseEntry methods', () => {
        const s = ColorEntry.make('brand_color').label('Brand').colSpan(2).tooltip('Hex').toSchema();
        expect(s.label).toBe('Brand');
        expect(s.colSpan).toBe(2);
        expect(s.tooltip).toBe('Hex');
    });

    it('humanises the label when none is given', () => {
        expect(ColorEntry.make('primary_color').toSchema().label).toBe('Primary Color');
    });
});

describe('CodeEntry', () => {
    it('emits the documented defaults', () => {
        const s = CodeEntry.make('payload').toSchema();
        expect(s.type).toBe('code');
        expect(s.codeLanguage).toBe('plaintext');
        expect(s.codeLineNumbers).toBe(true);
        expect(s.codeWrap).toBe(true);
        expect(s.codeMaxLines).toBe(400);
        expect(s.codeStartLine).toBe(1);
        expect(s.codeHighlightLines).toEqual([]);
        expect(s.codeFilename).toBeUndefined();
        expect(s.copyable).toBe(true);
    });

    it('round-trips every setter', () => {
        const s = CodeEntry.make('response_body')
            .language('json')
            .filename('response.json')
            .lineNumbers(false)
            .wrap(false)
            .maxLines(300)
            .startLine(12)
            .highlightLines([4, 5])
            .copyable(false)
            .toSchema();

        expect(s.codeLanguage).toBe('json');
        expect(s.codeFilename).toBe('response.json');
        expect(s.codeLineNumbers).toBe(false);
        expect(s.codeWrap).toBe(false);
        expect(s.codeMaxLines).toBe(300);
        expect(s.codeStartLine).toBe(12);
        expect(s.codeHighlightLines).toEqual([4, 5]);
        expect(s.copyable).toBe(false);
    });
});

describe('shared vocabulary', () => {
    // C-6: the entry and the field must accept the identical CodeLanguage set.
    it('re-exports CodeLanguage from useFormSchema', () => {
        const fromField: FieldCodeLanguage = 'php';
        const fromInfolist: CodeLanguage = fromField;
        expect(CodeEntry.make('x').language(fromInfolist).toSchema().codeLanguage).toBe('php');
    });

    it('color and code entries widen EntryType, not EntrySchema shape', () => {
        const entries: EntrySchema[] = [
            ColorEntry.make('a').toSchema(),
            CodeEntry.make('b').toSchema(),
        ];
        expect(entries.map(e => e.type)).toEqual(['color', 'code']);
    });
});
