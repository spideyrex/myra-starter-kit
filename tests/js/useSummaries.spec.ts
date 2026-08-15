import { describe, expect, it } from 'vitest';
import { TextColumn } from '@/composables/useTableSchema';
import { computeSummary, formatSummary } from '@/composables/useSummaries';

const rows = (values: any[]) => values.map((v, i) => ({ id: i + 1, v }));

describe('summaries', () => {
    it('computes min, max and median', () => {
        const data = rows([3, 1, 2]);
        expect(computeSummary(TextColumn.make('v').summarize('min').toSchema(), data)).toBe(1);
        expect(computeSummary(TextColumn.make('v').summarize('max').toSchema(), data)).toBe(3);
        expect(computeSummary(TextColumn.make('v').summarize('median').toSchema(), data)).toBe(2);
    });

    it('averages the two middle values for an even count', () => {
        const col = TextColumn.make('v').summarize('median').toSchema();
        expect(computeSummary(col, rows([4, 1, 3, 2]))).toBe(2.5);
    });

    it('renders an em dash for an empty column', () => {
        const col = TextColumn.make('v').summarize('median').toSchema();
        expect(computeSummary(col, [])).toBe('—');
        expect(computeSummary(TextColumn.make('v').summarize('range').toSchema(), [])).toBe('—');
    });

    it('honours the range separator', () => {
        const col = TextColumn.make('v').summary({ type: 'range', separator: ' to ', decimals: 0 }).toSchema();
        expect(computeSummary(col, rows([1, 3, 2]))).toBe('1 to 3');
    });

    it('does not blow the stack on a large page', () => {
        const big = rows(Array.from({ length: 100000 }, (_, i) => i));
        expect(() => computeSummary(TextColumn.make('v').summarize('range').toSchema(), big)).not.toThrow();
    });

    it('counts nulls only when excludeNull is false', () => {
        const data = rows([1, null, 3]);
        expect(computeSummary(TextColumn.make('v').summarize('count').toSchema(), data)).toBe(2);
        const col = TextColumn.make('v').summary({ type: 'count', excludeNull: false }).toSchema();
        expect(computeSummary(col, data)).toBe('3');
    });

    it('formats currency through Intl', () => {
        expect(formatSummary(1234.5, { type: 'sum', currency: 'MYR' })).toContain('1,234.50');
    });

    it('applies prefix, suffix and decimals', () => {
        expect(formatSummary(2, { type: 'sum', prefix: '~', suffix: ' kg', decimals: 1 })).toBe('~2.0 kg');
    });

    it('passes raw values to a custom summary fn', () => {
        const col = TextColumn.make('v').summarize('custom', values => `${values.length} rows`).toSchema();
        expect(computeSummary(col, rows([1, null, 3]))).toBe('2 rows');
    });
});
