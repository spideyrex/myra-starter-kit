import { describe, expect, it, vi } from 'vitest';
import { provideCrossFilter, type CrossFilterChip } from '@/composables/useCrossFilter';
import type { RuleGroup } from '@/types/report-delivery';

function group(value: string): RuleGroup {
    return { conjunction: 'and', rules: [{ field: 'status', operator: 'in', value: [value] }], groups: [] };
}

function chip(widget: string, value: string): CrossFilterChip {
    return { widget, source: 'status', label: `Status: ${value}`, group: group(value) };
}

describe('useCrossFilter', () => {
    it('toggles a chip off when the same fragment is re-emitted', () => {
        const onChange = vi.fn();
        const bus = provideCrossFilter({ onChange });

        bus.emit('chart-status', chip('chart-status', 'active'));
        expect(bus.chips.value).toHaveLength(1);

        bus.emit('chart-status', chip('chart-status', 'active'));
        expect(bus.chips.value).toHaveLength(0);
    });

    it('replaces rather than toggles when the fragment differs', () => {
        const bus = provideCrossFilter({ onChange: vi.fn() });

        bus.emit('chart-status', chip('chart-status', 'active'));
        bus.emit('chart-status', chip('chart-status', 'pending'));

        expect(bus.chips.value).toHaveLength(1);
        expect(bus.chips.value[0].label).toBe('Status: pending');
    });

    it('never feeds a widget its own fragment', () => {
        const bus = provideCrossFilter({ onChange: vi.fn() });

        bus.emit('chart-status', chip('chart-status', 'active'));
        bus.emit('chart-role', chip('chart-role', 'manager'));

        expect(Object.keys(bus.forWidget('chart-status').value)).toEqual(['chart-role']);
        expect(Object.keys(bus.forWidget('chart-role').value)).toEqual(['chart-status']);
        expect(Object.keys(bus.forWidget('table-recent').value).sort()).toEqual(['chart-role', 'chart-status']);
    });

    it('notifies once per segment click, not once per listening widget', () => {
        const onChange = vi.fn();
        const bus = provideCrossFilter({ onChange });

        bus.emit('chart-status', chip('chart-status', 'active'));

        expect(onChange).toHaveBeenCalledTimes(1);
        expect(onChange.mock.calls[0][0]).toHaveLength(1);
    });

    it('does not notify when clearing an empty bus', () => {
        const onChange = vi.fn();
        const bus = provideCrossFilter({ onChange });

        bus.clear();
        bus.clear('chart-status');

        expect(onChange).not.toHaveBeenCalled();
    });

    it('reports whether a widget is currently a source', () => {
        const bus = provideCrossFilter({ onChange: vi.fn() });

        expect(bus.isSource('chart-status')).toBe(false);

        bus.emit('chart-status', chip('chart-status', 'active'));

        expect(bus.isSource('chart-status')).toBe(true);
    });
});
