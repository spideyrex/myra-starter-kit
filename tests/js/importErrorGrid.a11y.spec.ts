import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/i18n/locales/en.json';
import ImportErrorGrid from '@/components/admin/ImportErrorGrid.vue';

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en } });

const columns = [
    { name: 'name', label: 'Name', required: true },
    { name: 'email', label: 'Email', required: true },
];

const rows = [
    { line: 2, values: { name: 'Ada', email: 'ada@example.com' }, errors: {} },
    {
        line: 3,
        values: { name: 'Alan', email: 'not-an-email' },
        errors: { email: ['Email must be a valid email address.'] },
    },
];

function grid(props: Record<string, any> = {}) {
    return mount(ImportErrorGrid, {
        props: { columns, rows, ...props },
        global: { plugins: [i18n] },
    });
}

describe('ImportErrorGrid a11y', () => {
    it('is a real table with a caption', () => {
        const w = grid();

        expect(w.find('table').exists()).toBe(true);
        expect(w.find('caption').exists()).toBe(true);
        expect(w.find('caption').text()).toBe(en.transfer.import.a11y.errorGrid);
    });

    it('marks every column header with scope="col"', () => {
        const heads = grid().findAll('thead th');

        expect(heads.length).toBe(columns.length + 1);
        heads.forEach(th => expect(th.attributes('scope')).toBe('col'));
    });

    it('marks an offending cell aria-invalid and wires aria-describedby to its message', () => {
        const w = grid();
        const invalid = w.findAll('td').filter(td => td.attributes('aria-invalid') === 'true');

        expect(invalid.length).toBe(1);

        const describedBy = invalid[0].attributes('aria-describedby');
        expect(describedBy).toBe('imp-3-email');
        expect(w.find(`#${describedBy}`).exists()).toBe(true);
        expect(w.find(`#${describedBy}`).text()).toContain('valid email');
    });

    it('does not mark a valid cell', () => {
        const w = grid();
        const cells = w.findAll('td');

        expect(cells[0].attributes('aria-invalid')).toBeUndefined();
    });

    it('does not rely on colour alone — the error carries an icon and text', () => {
        const w = grid();
        const message = w.find('#imp-3-email');

        expect(message.find('svg').exists()).toBe(true);
        expect(message.text().length).toBeGreaterThan(0);
    });

    it('invalidOnly hides the passing rows', () => {
        const w = grid({ invalidOnly: true });

        expect(w.findAll('tbody tr').length).toBe(1);
    });

    it('renders an empty state when nothing failed', () => {
        const w = grid({ rows: [rows[0]], invalidOnly: true });

        expect(w.text()).toContain(en.transfer.import.noErrors);
    });
});
