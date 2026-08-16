import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';

// sortablejs wants a real layout engine; the KEYBOARD path is what this file
// asserts, and that path is useReorderable, which stays entirely real.
vi.mock('vue-draggable-plus', () => ({
    VueDraggable: { name: 'VueDraggable', template: '<div><slot /></div>' },
}));

import { uiStubs } from './helpers/i18n';
import SectionList from '@/components/admin/pagebuilder/SectionList.vue';
import { REORDER_INSTRUCTIONS_ID } from '@/composables/useReorderable';
import { useSectionList, type SectionSchema } from '@/composables/useSectionList';

const SCHEMAS: SectionSchema[] = [
    {
        key: 'hero',
        labelKey: 'pageBuilder.sections.hero.label',
        descriptionKey: 'pageBuilder.sections.hero.description',
        icon: 'Rocket',
        group: 'content',
        titleField: 'title',
        maxPerPage: 1,
        variants: { align: ['center', 'split', 'left'], compact: 'bool' },
        fields: [
            { name: 'title', type: 'text', labelKey: 'pageBuilder.sections.hero.fields.title', required: false, default: '', max: 120 },
        ],
    },
    {
        key: 'features',
        labelKey: 'pageBuilder.sections.features.label',
        descriptionKey: 'pageBuilder.sections.features.description',
        icon: 'Grid3x3',
        group: 'content',
        titleField: 'title',
        maxPerPage: 0,
        variants: {},
        fields: [
            { name: 'title', type: 'text', labelKey: 'pageBuilder.sections.features.fields.title', required: false, default: '', max: 120 },
        ],
    },
];

const messages = { en, ms, zh } as Record<string, Record<string, unknown>>;

function build(locale = 'en', errors: Record<string, string> = {}) {
    const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: messages as never });

    const list = useSectionList({
        schemas: SCHEMAS,
        initial: [
            { id: 'ROW_ONE', type: 'hero', enabled: true, variant: {}, data: { title: 'First' } },
            { id: 'ROW_TWO', type: 'features', enabled: true, variant: {}, data: { title: 'Second' } },
            { id: 'ROW_THREE', type: 'from_a_package', enabled: false, variant: {}, data: { odd: 1 } },
        ],
        t: (key, named) => (named ? i18n.global.t(key, named) : i18n.global.t(key)),
        has: key => i18n.global.te(key),
    });

    const wrapper = mount(SectionList, {
        props: { list, errors },
        global: {
            plugins: [i18n],
            stubs: { ...uiStubs, teleport: true },
        },
    });

    return { wrapper, list };
}

function handles(wrapper: ReturnType<typeof build>['wrapper']) {
    return wrapper.findAll('[data-reorder-key]');
}

describe('SectionList keyboard reordering', () => {
    it('gives every grip a focusable, named, described handle', () => {
        const { wrapper } = build();
        const grips = handles(wrapper);

        expect(grips).toHaveLength(3);

        for (const grip of grips) {
            expect(grip.attributes('tabindex')).toBe('0');
            expect(grip.attributes('aria-label')).toBeTruthy();
            expect(grip.attributes('aria-label')).not.toBe('');
            expect(grip.attributes('aria-describedby')).toBe(REORDER_INSTRUCTIONS_ID);
            expect(grip.attributes('aria-roledescription')).toBe(en.pageBuilder.editor.a11y.roledescription);
        }
    });

    it('renders the instructions node the grips point at', () => {
        const { wrapper } = build();
        const node = wrapper.find(`#${REORDER_INSTRUCTIONS_ID}`);

        expect(node.exists()).toBe(true);
        expect(node.text()).toBe(en.pageBuilder.editor.a11y.instructions);
    });

    it('grabs with Space and moves with the arrow keys', async () => {
        const { wrapper, list } = build();
        const grip = handles(wrapper)[0];

        await grip.trigger('keydown', { key: ' ' });
        await grip.trigger('keydown', { key: 'ArrowDown' });

        expect(list.rows.value.map(r => r.id)).toEqual(['ROW_TWO', 'ROW_ONE', 'ROW_THREE']);
    });

    it('moves to the ends with Home and End', async () => {
        const { wrapper, list } = build();

        await handles(wrapper)[0].trigger('keydown', { key: ' ' });
        await handles(wrapper)[0].trigger('keydown', { key: 'End' });

        expect(list.rows.value.map(r => r.id)).toEqual(['ROW_TWO', 'ROW_THREE', 'ROW_ONE']);
    });

    it('cancels with Escape and restores the pre-grab position', async () => {
        const { wrapper, list } = build();

        await handles(wrapper)[0].trigger('keydown', { key: ' ' });
        await handles(wrapper)[0].trigger('keydown', { key: 'ArrowDown' });
        expect(list.rows.value.map(r => r.id)).toEqual(['ROW_TWO', 'ROW_ONE', 'ROW_THREE']);

        // The row is now at index 1; Escape restores index 0.
        await handles(wrapper)[1].trigger('keydown', { key: 'Escape' });

        expect(list.rows.value.map(r => r.id)).toEqual(['ROW_ONE', 'ROW_TWO', 'ROW_THREE']);
    });

    it('ignores the arrows until a row is grabbed', async () => {
        const { wrapper, list } = build();

        await handles(wrapper)[0].trigger('keydown', { key: 'ArrowDown' });

        expect(list.rows.value.map(r => r.id)).toEqual(['ROW_ONE', 'ROW_TWO', 'ROW_THREE']);
    });

    it('announces the move through a polite live region', async () => {
        const { wrapper } = build();
        const region = wrapper.find('[aria-live="polite"]');

        expect(region.exists()).toBe(true);
        expect(region.text()).toBe('');

        await handles(wrapper)[0].trigger('keydown', { key: ' ' });
        await handles(wrapper)[0].trigger('keydown', { key: 'ArrowDown' });

        expect(wrapper.find('[aria-live="polite"]').text()).not.toBe('');
    });
});

describe('SectionList pointer drag', () => {
    it('routes a drag through the SAME move the keyboard uses', async () => {
        const { wrapper, list } = build();

        await wrapper.findComponent({ name: 'VueDraggable' }).vm.$emit('update', { oldIndex: 0, newIndex: 2 });

        expect(list.rows.value.map(r => r.id)).toEqual(['ROW_TWO', 'ROW_THREE', 'ROW_ONE']);
        expect(list.canUndo.value).toBe(true);
    });

    it('ignores a drag event with no usable indices', async () => {
        const { wrapper, list } = build();

        await wrapper.findComponent({ name: 'VueDraggable' }).vm.$emit('update', {});

        expect(list.rows.value.map(r => r.id)).toEqual(['ROW_ONE', 'ROW_TWO', 'ROW_THREE']);
    });
});

describe('SectionList validation errors', () => {
    it('expands and selects the first card the server rejected', async () => {
        const { wrapper, list } = build('en', { 'blocks.1.data.title': 'The title is required.' });

        list.toggleCollapsed('ROW_TWO');
        await wrapper.setProps({ errors: { 'blocks.1.data.title': 'The title is required.' } });
        await wrapper.vm.$nextTick();

        expect(list.isCollapsed('ROW_TWO')).toBe(false);
        expect(list.selectedId.value).toBe('ROW_TWO');
        expect(wrapper.text()).toContain('The title is required.');
    });

    it('ignores a bag key that addresses no card', async () => {
        const { wrapper, list } = build('en', { blocks: 'At most 100 sections.' });

        await wrapper.vm.$nextTick();

        expect(list.rows.value).toHaveLength(3);
    });
});

describe('SectionList controls', () => {
    it('names every button and switch, in every locale', () => {
        for (const locale of ['en', 'ms', 'zh']) {
            const { wrapper } = build(locale);

            for (const control of wrapper.findAll('button, [role="switch"]')) {
                const name = control.attributes('aria-label') ?? control.text();

                expect(name.trim(), `${locale}: an unnamed control`).not.toBe('');
                expect(name, `${locale}: an unresolved i18n key leaked`).not.toMatch(/pageBuilder\./);
            }

            wrapper.unmount();
        }
    });

    it('quarantines an unknown type instead of dropping it', () => {
        const { wrapper, list } = build();

        expect(list.rows.value.map(r => r.type)).toContain('from_a_package');
        expect(wrapper.text()).toContain(en.pageBuilder.editor.card.unknown.title);
        // The bare type is still visible as the card title.
        expect(wrapper.text()).toContain('from_a_package');
    });

    it('marks a hidden row so the state is not colour-only', () => {
        const { wrapper } = build();

        expect(wrapper.text()).toContain(en.pageBuilder.editor.card.hidden);
    });

    it('exposes each collapsible body through aria-expanded / aria-controls', async () => {
        const { wrapper, list } = build();
        const toggle = wrapper.findAll('[aria-expanded]')[0];

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(document.body).toBeTruthy();

        list.toggleCollapsed('ROW_ONE');
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('[aria-expanded]')[0].attributes('aria-expanded')).toBe('false');
        expect(wrapper.findAll('[aria-expanded]')[0].attributes('aria-controls')).toBe('pb-section-ROW_ONE-body');
    });
});
