import { mount, type DOMWrapper, type VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { expect } from 'vitest';
import DataTable from '@/components/DataTable.vue';
import { TextColumn, BadgeColumn } from '@/composables/useTableSchema';
import { testI18n } from './i18n';

/**
 * Mounts the REAL DataTable against a REAL server payload (normally a committed
 * fixture). Only the portalled menus are stubbed; the table body, the pagination
 * footer and the virtual window are the production components.
 */
export interface TableHarness {
    rows(): DOMWrapper<Element>[];
    assertRowCount(n: number): TableHarness;
    assertRenderedRowsAtMost(n: number): TableHarness;
    assertPaginationMode(mode: 'cursor' | 'length-aware'): TableHarness;
    assertCellText(rowId: number | string, key: string, text: string): TableHarness;
    scrollTo(px: number): Promise<TableHarness>;
    container(): HTMLElement;
    wrapper(): VueWrapper<any>;
}

export const scaleColumns = [
    TextColumn.make('id').label('#').sortable(),
    TextColumn.make('name').label('Name').sortable(),
    TextColumn.make('email').label('Email'),
    BadgeColumn.make('status').label('Status'),
    TextColumn.make('amount').label('Amount').alignEnd(),
];

export function mountMyraTable(opts: {
    columns?: any[];
    data: any;
    actions?: any[];
    permissions?: string[];
    tableKey?: string;
    routeName?: string;
    groupBy?: string;
    virtualized?: boolean;
    rowHeight?: number;
    viewportHeight?: number;
    overscan?: number;
}): TableHarness {
    const columns = opts.columns ?? scaleColumns;

    const wrapper = mount(DataTable as any, {
        props: {
            columns,
            data: opts.data,
            routeName: opts.routeName ?? 'admin.demo.scale',
            tableKey: opts.tableKey,
            actions: opts.actions,
            groupBy: opts.groupBy,
            virtualized: opts.virtualized ?? false,
            rowHeight: opts.rowHeight ?? 44,
            viewportHeight: opts.viewportHeight ?? 600,
            overscan: opts.overscan ?? 8,
            searchable: false,
            columnManager: false,
        },
        global: {
            plugins: [testI18n()],
            stubs: {
                TableViewsMenu: true,
                ColumnManager: true,
                ActionModal: true,
                QueryBuilderGroup: true,
            },
        },
        attachTo: document.body,
    });

    const harness: TableHarness = {
        rows: () => wrapper.findAll('tbody tr[data-slot="table-row"]'),
        container: () => wrapper.find('[data-slot="table-scroll"]').element as HTMLElement,
        wrapper: () => wrapper,

        assertRowCount(n) {
            expect(harness.rows().length).toBe(n);

            return harness;
        },

        assertRenderedRowsAtMost(n) {
            expect(harness.rows().length).toBeLessThanOrEqual(n);

            return harness;
        },

        assertPaginationMode(mode) {
            const labels = wrapper.findAll('button').map(b => b.text());

            if (mode === 'cursor') {
                expect(labels).toContain('Previous');
                expect(labels).toContain('Next');
                expect(labels.some(l => /^\d+$/.test(l))).toBe(false);
            } else {
                expect(labels.some(l => /^\d+$/.test(l))).toBe(true);
            }

            return harness;
        },

        assertCellText(rowId, key, text) {
            const index = columns.findIndex((c: any) => (c.toSchema?.().key ?? c.key) === key);
            expect(index).toBeGreaterThanOrEqual(0);

            const row = harness.rows().find(r => r.text().includes(String(rowId)));
            expect(row, `no row rendered for id ${rowId}`).toBeTruthy();
            expect(row!.findAll('td')[index].text()).toContain(text);

            return harness;
        },

        async scrollTo(px) {
            const el = harness.container();
            Object.defineProperty(el, 'scrollTop', { value: px, writable: true, configurable: true });
            el.dispatchEvent(new Event('scroll'));
            await nextTick();

            return harness;
        },
    };

    return harness;
}
