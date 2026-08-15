import { computed, getCurrentInstance, inject, provide, ref, type ComputedRef, type InjectionKey } from 'vue';
import type { RuleGroup } from '@/types/report-delivery';

export interface CrossFilterChip {
    /** The widget that emitted the fragment. One chip per widget. */
    widget: string;
    /** The cross-filter channel siblings listen on. */
    source: string;
    label: string;
    group: RuleGroup;
}

export interface CrossFilterBus {
    chips: ComputedRef<CrossFilterChip[]>;
    /** Toggles: re-emitting an identical chip from the same widget clears it. */
    emit(widgetKey: string, chip: CrossFilterChip | null): void;
    clear(widgetKey?: string): void;
    /** Rules from every OTHER widget — a widget never filters itself. */
    forWidget(widgetKey: string): ComputedRef<Record<string, RuleGroup>>;
    isSource(widgetKey: string): boolean;
}

export const CROSS_FILTER: InjectionKey<CrossFilterBus> = Symbol('myra.crossFilter');

function signature(group: RuleGroup | null | undefined): string {
    return JSON.stringify(group ?? null);
}

/**
 * The bus only tracks fragments. It never filters anything in the browser:
 * `forWidget()` feeds `ReportState.cross`, which the server re-validates through
 * the report's own FieldSet. The client is transport, not authority.
 */
export function provideCrossFilter(opts: { onChange: (chips: CrossFilterChip[]) => void }): CrossFilterBus {
    const entries = ref<Record<string, CrossFilterChip>>({});

    const chips = computed<CrossFilterChip[]>(() => Object.values(entries.value));

    function notify(): void {
        opts.onChange(chips.value);
    }

    function emit(widgetKey: string, chip: CrossFilterChip | null): void {
        const existing = entries.value[widgetKey];

        if (chip === null || (existing && signature(existing.group) === signature(chip.group))) {
            if (!existing) return;
            const next = { ...entries.value };
            delete next[widgetKey];
            entries.value = next;
            notify();
            return;
        }

        entries.value = { ...entries.value, [widgetKey]: { ...chip, widget: widgetKey } };
        notify();
    }

    function clear(widgetKey?: string): void {
        if (widgetKey === undefined) {
            if (Object.keys(entries.value).length === 0) return;
            entries.value = {};
            notify();
            return;
        }

        emit(widgetKey, null);
    }

    function forWidget(widgetKey: string): ComputedRef<Record<string, RuleGroup>> {
        return computed(() => {
            const out: Record<string, RuleGroup> = {};
            for (const [key, chip] of Object.entries(entries.value)) {
                if (key !== widgetKey) out[key] = chip.group;
            }
            return out;
        });
    }

    const bus: CrossFilterBus = {
        chips,
        emit,
        clear,
        forWidget,
        isSource: (widgetKey: string) => entries.value[widgetKey] !== undefined,
    };

    if (getCurrentInstance()) provide(CROSS_FILTER, bus);

    return bus;
}

export function useCrossFilter(): CrossFilterBus {
    const bus = inject(CROSS_FILTER, null);

    if (!bus) {
        throw new Error('useCrossFilter() requires provideCrossFilter() on an ancestor.');
    }

    return bus;
}
