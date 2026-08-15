import { readFileSync } from 'node:fs';
import path from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/i18n/locales/en.json';
import CodeBlock from '@/components/admin/CodeBlock.vue';

const highlightToHtml = vi.fn(async (code: string) => ({
    html: `<span class="tok">${code.replace(/</g, '&lt;')}</span>`,
    truncated: false,
    totalLines: code.split('\n').length,
}));

vi.mock('@/composables/useCodeMirror', () => ({
    highlightToHtml: (...args: any[]) => (highlightToHtml as any)(...args),
    mountEditor: vi.fn(),
}));

const sanitizeHtml = vi.fn((html: string) => html);
vi.mock('@/composables/useSanitize', () => ({
    sanitizeHtml: (html: string) => sanitizeHtml(html),
    sanitizeSvg: (html: string) => html,
}));

vi.mock('vue-sonner', () => ({
    toast: { success: vi.fn(), error: vi.fn() },
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en } });

function mountBlock(props: Record<string, any>) {
    return mount(CodeBlock, { props, global: { plugins: [i18n] } });
}

describe('CodeBlock', () => {
    beforeEach(() => {
        highlightToHtml.mockClear();
        sanitizeHtml.mockClear();
    });

    it('stringifies an object value and forces the json language', async () => {
        const wrapper = mountBlock({ value: { a: 1, b: [2, 3] }, codeLanguage: 'plaintext' });
        await flushPromises();

        const [code, lang] = highlightToHtml.mock.calls[0] as any[];
        expect(code).toBe(JSON.stringify({ a: 1, b: [2, 3] }, null, 2));
        expect(lang).toBe('json');
        expect(wrapper.find('code').classes()).toContain('language-json');
    });

    it('passes the highlighter output through sanitizeHtml before v-html', async () => {
        mountBlock({ value: 'const a = 1;', codeLanguage: 'javascript' });
        await flushPromises();
        expect(sanitizeHtml).toHaveBeenCalledTimes(1);
        expect(sanitizeHtml.mock.calls[0][0]).toContain('const a = 1;');
    });

    it('renders an aria-hidden gutter and a focusable, labelled region', async () => {
        const wrapper = mountBlock({
            value: 'line one\nline two',
            codeLanguage: 'plaintext',
            codeFilename: 'notes.txt',
        });
        await flushPromises();

        const pre = wrapper.find('pre');
        expect(pre.attributes('tabindex')).toBe('0');
        expect(pre.attributes('role')).toBe('region');
        expect(pre.attributes('aria-label')).toBe('notes.txt');

        const gutter = wrapper.findAll('pre [aria-hidden="true"]');
        expect(gutter.length).toBe(2);
        expect(gutter[0].text()).toBe('1');
        expect(gutter[1].text()).toBe('2');
    });

    it('honours startLine and hides the gutter when lineNumbers is off', async () => {
        const numbered = mountBlock({ value: 'a\nb', codeStartLine: 10 });
        await flushPromises();
        expect(numbered.findAll('pre [aria-hidden="true"]')[0].text()).toBe('10');

        const plain = mountBlock({ value: 'a\nb', codeLineNumbers: false });
        await flushPromises();
        expect(plain.findAll('pre [aria-hidden="true"]').length).toBe(0);
    });

    it('falls back to escaped plain text when the highlighter chunk fails', async () => {
        highlightToHtml.mockRejectedValueOnce(new Error('chunk load failed'));
        const wrapper = mountBlock({ value: '<script>alert(1)</script>' });
        await flushPromises();

        expect(wrapper.find('code').html()).not.toContain('<script>');
        expect(wrapper.text()).toContain('alert(1)');
    });

    it('exposes an Expand toggle with aria-expanded when truncated', async () => {
        highlightToHtml.mockResolvedValueOnce({ html: 'a\nb', truncated: true, totalLines: 900 });
        const wrapper = mountBlock({ value: 'a\nb\nc', codeMaxLines: 2 });
        await flushPromises();

        const toggle = wrapper.find('button[aria-expanded]');
        expect(toggle.exists()).toBe(true);
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(toggle.attributes('aria-controls')).toBe(wrapper.find('pre').attributes('id'));

        await toggle.trigger('click');
        await flushPromises();
        // Re-highlighted without a maxLines cap.
        expect(highlightToHtml.mock.calls[1][2]).toBeUndefined();
    });

    it('uses the read-only highlight entry point and never mounts an editor', () => {
        const src = readFileSync(
            path.resolve(__dirname, '../../resources/js/components/admin/CodeBlock.vue'),
            'utf8',
        );
        expect(src).toContain('highlightToHtml');
        expect(src).not.toContain('mountEditor');
    });
});
