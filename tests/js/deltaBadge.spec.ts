import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { testI18n } from './helpers/i18n';
import DeltaBadge from '@/components/admin/charts/DeltaBadge.vue';
import { formatDeltaPercent, formatMeasure } from '@/components/admin/charts/format';
import type { Delta } from '@/components/admin/charts/types';

function badge(delta: Delta | null, props: Record<string, any> = {}) {
    return mount(DeltaBadge, {
        props: { delta, ...props },
        global: { plugins: [testI18n()] },
    });
}

describe('DeltaBadge', () => {
    it('renders an em dash when the percentage is not derivable', () => {
        const w = badge({ absolute: 5, percent: null, direction: 'up', good: true });

        expect(w.text()).toContain('—');
        expect(w.text()).not.toMatch(/Infinity|NaN/);
    });

    it('colours by verdict, not by direction, so an inverted trend reads as bad', () => {
        const rising = badge({ absolute: 0.9, percent: 23.1, direction: 'up', good: false });

        expect(rising.classes().join(' ')).toContain('text-destructive');
        expect(rising.classes().join(' ')).not.toContain('text-success');
    });

    it('colours a good rise positively', () => {
        const w = badge({ absolute: 141, percent: 12.3, direction: 'up', good: true });

        expect(w.classes().join(' ')).toContain('text-success');
        expect(w.text()).toContain('+12.3%');
    });

    it('renders a flat delta neutrally', () => {
        const w = badge({ absolute: 0, percent: 0, direction: 'flat', good: true });

        expect(w.classes().join(' ')).toContain('text-muted-foreground');
    });

    it('falls back to a written label when there is no comparison at all', () => {
        expect(badge(null).text()).toBe('No comparison');
    });

    it('carries a full sentence for screen readers', () => {
        const w = badge({ absolute: 141, percent: 12.3, direction: 'up', good: true });

        expect(w.attributes('aria-label')).toContain('Up');
        expect(w.attributes('aria-label')).toContain('better');
    });
});

describe('measure formatting', () => {
    it('renders a null value as an em dash, never zero', () => {
        expect(formatMeasure(null)).toBe('—');
        expect(formatMeasure(undefined, 'currency', 2)).toBe('—');
        expect(formatMeasure(Number.NaN)).toBe('—');
    });

    it('honours the declared format', () => {
        expect(formatMeasure(1284)).toBe('1,284');
        expect(formatMeasure(12.5, 'percent', 1)).toBe('12.5%');
        expect(formatMeasure(2048, 'bytes')).toBe('2.0 KB');
        expect(formatMeasure(3725, 'duration')).toBe('1h 2m');
    });

    it('signs a delta percentage and refuses to invent one', () => {
        expect(formatDeltaPercent(12.3)).toBe('+12.3%');
        expect(formatDeltaPercent(-4)).toBe('-4%');
        expect(formatDeltaPercent(-4.28)).toBe('-4.3%');
        expect(formatDeltaPercent(null)).toBe('—');
        expect(formatDeltaPercent(Number.POSITIVE_INFINITY)).toBe('—');
    });
});
