import { describe, expect, it } from 'vitest';
import { DEFAULT_MARKDOWN_TOOLBAR, MarkdownEditor } from '@/composables/useFormSchema';

describe('MarkdownEditor field', () => {
    it('emits the default toolbar when nothing is configured', () => {
        const props = MarkdownEditor.make('readme').toProps();
        expect(props.mdToolbar).toEqual([...DEFAULT_MARKDOWN_TOOLBAR]);
        expect(props.mdMode).toBe('split');
        expect(props.mdModeSwitcher).toBe(true);
        expect(props.mdFullscreen).toBe(false);
        expect(props.mdCounter).toBe(false);
    });

    it('withoutToolbar subtracts from the default set', () => {
        const props = MarkdownEditor.make('readme').withoutToolbar(['image', 'table']).toProps();
        expect(props.mdToolbar).not.toContain('image');
        expect(props.mdToolbar).not.toContain('table');
        expect(props.mdToolbar).toContain('bold');
    });

    it('subtraction is order-independent against an explicit toolbar', () => {
        const props = MarkdownEditor.make('readme')
            .withoutToolbar(['image'])
            .toolbar(['bold', 'image'])
            .toProps();
        expect(props.mdToolbar).toEqual(['bold']);
    });

    it('maxLength implies the counter', () => {
        const props = MarkdownEditor.make('readme').maxLength(500).toProps();
        expect(props.mdMaxLength).toBe(500);
        expect(props.mdCounter).toBe(true);
    });

    it('rows() still round-trips', () => {
        expect(MarkdownEditor.make('readme').rows(12).toProps().rows).toBe(12);
    });

    it('carries mode, fullscreen, heights and the upload route', () => {
        const props = MarkdownEditor.make('readme')
            .mode('edit')
            .modeSwitcher(false)
            .fullscreen()
            .minHeight('10rem')
            .maxHeight('40rem')
            .uploadRoute('admin.media.markdown-upload')
            .toProps();

        expect(props).toMatchObject({
            mdMode: 'edit',
            mdModeSwitcher: false,
            mdFullscreen: true,
            mdMinHeight: '10rem',
            mdMaxHeight: '40rem',
            mdUploadRoute: 'admin.media.markdown-upload',
        });
    });
});
