import { describe, expect, it, vi, beforeEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import CodeEditorField from '@/components/admin/CodeEditorField.vue';

const mounted: any[] = [];
const handle = {
    setDoc: vi.fn(),
    setLanguage: vi.fn(),
    setReadOnly: vi.fn(),
    setContentAttributes: vi.fn(),
    focus: vi.fn(),
    destroy: vi.fn(),
};

vi.mock('@/composables/useCodeMirror', () => ({
    GRAMMARS: {},
    mountEditor: vi.fn(async (opts: any) => {
        mounted.push(opts);
        return handle;
    }),
    highlightToHtml: vi.fn(),
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({ t: (key: string) => key }),
}));

beforeEach(() => {
    mounted.length = 0;
    handle.setContentAttributes.mockClear();
});

describe('CodeEditorField accessibility', () => {
    it('names the contenteditable through the field label and marks it as a textbox', async () => {
        mount(CodeEditorField, {
            props: { fieldId: 'field-x', label: 'Payload', codeLanguage: 'json', describedBy: 'field-x-hint' },
            attachTo: document.body,
        });
        await flushPromises();

        expect(mounted).toHaveLength(1);
        const attrs = mounted[0].contentAttributes;
        expect(attrs.role).toBe('textbox');
        expect(attrs['aria-multiline']).toBe('true');
        expect(attrs['aria-labelledby']).toBe('field-x-label');
        expect(attrs['aria-describedby']).toBe('field-x-hint');
        expect(attrs['aria-invalid']).toBe('false');
    });

    it('re-pushes content attributes when the error appears', async () => {
        const wrapper = mount(CodeEditorField, {
            props: { fieldId: 'field-x', label: 'Payload' },
            attachTo: document.body,
        });
        await flushPromises();

        await wrapper.setProps({ error: 'Invalid JSON', describedBy: 'field-x-hint field-x-error' });
        await flushPromises();

        expect(handle.setContentAttributes).toHaveBeenCalled();
        const last = handle.setContentAttributes.mock.calls.at(-1)![0];
        expect(last['aria-invalid']).toBe('true');
        expect(last['aria-describedby']).toBe('field-x-hint field-x-error');
    });

    it('marks the editor read-only when disabled', async () => {
        mount(CodeEditorField, {
            props: { fieldId: 'field-x', label: 'Payload', disabled: true },
            attachTo: document.body,
        });
        await flushPromises();
        expect(mounted[0].contentAttributes['aria-readonly']).toBe('true');
        expect(mounted[0].readOnly).toBe(true);
    });

    it('never enables Tab capture unless asked', async () => {
        mount(CodeEditorField, { props: { fieldId: 'field-x', label: 'Payload' }, attachTo: document.body });
        await flushPromises();
        expect(mounted[0].indentWithTab).toBeFalsy();
    });

    it('destroys the editor on unmount', async () => {
        const wrapper = mount(CodeEditorField, { props: { fieldId: 'field-x', label: 'Payload' }, attachTo: document.body });
        await flushPromises();
        wrapper.unmount();
        expect(handle.destroy).toHaveBeenCalled();
    });
});
