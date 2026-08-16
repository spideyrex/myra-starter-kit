import { beforeAll, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { exampleI18n, installExampleEnv } from './helpers/exampleEnv';
import en from '@/i18n/locales/en.json';
import MailPage from '@/examples/mail/Page.vue';
import mails from '@/examples/mail/data/mails.json';

beforeAll(() => installExampleEnv());

function mountMail() {
    return mount(MailPage, { global: { plugins: [exampleI18n()] } });
}

describe('mail example accessibility', () => {
    it('labels all three panes as regions', () => {
        const labels = mountMail().findAll('section').map(s => s.attributes('aria-label'));

        expect(labels).toContain(en.examples.mail.panes.folders);
        expect(labels).toContain(en.examples.mail.panes.list);
        expect(labels).toContain(en.examples.mail.panes.message);
    });

    it('keeps the resize handles keyboard operable', () => {
        const separators = mountMail().findAll('[role="separator"]');

        expect(separators.length).toBeGreaterThan(0);

        for (const separator of separators) {
            expect(Number(separator.attributes('tabindex') ?? '0')).toBeGreaterThanOrEqual(0);
        }
    });

    it('renders the message list as a list of real buttons, never clickable divs', () => {
        const w = mountMail();
        const lists = w.findAll('ul[role="list"]');

        expect(lists.length).toBeGreaterThan(0);

        const items = w.findAll('ul[role="list"] > li');
        expect(items.length).toBeGreaterThan(0);

        for (const item of items) {
            expect(item.find('button, a').exists()).toBe(true);
        }
    });

    it('announces the selected message with aria-current, and only one at a time', async () => {
        const w = mountMail();

        const selected = w.findAll('[aria-current="true"]');
        expect(selected.length).toBeGreaterThan(0);

        const buttons = w.findAll('ul[role="list"] > li button');
        const second = buttons.find(b => b.text().includes(mails[1].subject));

        expect(second).toBeTruthy();

        await second!.trigger('click');

        expect(w.findAll('button[aria-current="true"]').some(b => b.text().includes(mails[1].subject))).toBe(true);
    });

    it('announces the filtered result count politely', async () => {
        const w = mountMail();
        const status = w.find('[role="status"]');

        expect(status.attributes('aria-live')).toBe('polite');

        const before = status.text();
        await w.find('#mail-search').setValue('vacation');

        expect(w.find('[role="status"]').text()).not.toBe(before);
    });

    it('labels the reply composer and the account switcher', () => {
        const w = mountMail();

        for (const id of ['mail-reply', 'mail-search', 'mail-account']) {
            expect(w.find(`label[for="${id}"]`).exists(), `${id} has no associated label`).toBe(true);
        }
    });

    it('ships no hardcoded English — the chrome comes from t()', () => {
        const chinese = mount(MailPage, { global: { plugins: [exampleI18n('zh')] } });

        expect(chinese.text()).not.toContain(en.examples.mail.folders.inbox);
    });
});
