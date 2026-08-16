import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/i18n/locales/en.json';
import { uiStubs } from './helpers/i18n';
import SectionFieldControl from '@/components/admin/pagebuilder/SectionFieldControl.vue';
import { ICON_FIELD_NAMES } from '@/components/admin/pagebuilder/sectionIcons';
import type { SectionFieldSchema } from '@/composables/useSectionList';

/**
 * SectionField::toClientSchema() attaches `options` for type === 'select' ONLY,
 * so an `icon` field arrives WITHOUT options — exactly as declared here. The
 * server then coerces any name outside SectionField::ICON_ALLOWLIST to '', so a
 * free-text control would take an edit and lose it on save.
 */
const ICON_FIELD: SectionFieldSchema = {
    name: 'icon',
    type: 'icon',
    labelKey: 'pageBuilder.sections.features.fields.icon',
    required: false,
    default: '',
    max: null,
};

// A Select that reports its bound value and can emit one back, which the
// popper-free stub in helpers/i18n cannot do.
const selectStubs = {
    ...uiStubs,
    Select: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: `<div class="stub-select" :data-value="modelValue">
            <button class="pick-known" type="button" @click="$emit('update:modelValue', 'Shield')"></button>
            <button class="pick-none" type="button" @click="$emit('update:modelValue', '__none')"></button>
            <slot />
        </div>`,
    },
};

function build(modelValue: unknown, field: SectionFieldSchema = ICON_FIELD) {
    const i18n = createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });

    return mount(SectionFieldControl, {
        props: { field, modelValue, path: 'items.0.icon', idPrefix: 'ROW' },
        global: { plugins: [i18n], stubs: { ...selectStubs, teleport: true } },
    });
}

function itemValues(wrapper: ReturnType<typeof build>): string[] {
    return wrapper.findAll('.stub-select-item').map(node => node.attributes('value') ?? '');
}

describe('SectionFieldControl icon field', () => {
    it('renders a picker, never a free-text input, when the server sends no options', () => {
        const wrapper = build('');

        expect(wrapper.find('input[type="text"]').exists()).toBe(false);
        expect(wrapper.find('.stub-select').exists()).toBe(true);
    });

    it('offers exactly the allowlist the server accepts, plus an explicit none', () => {
        const wrapper = build('');

        expect(itemValues(wrapper)).toEqual(['__none', ...ICON_FIELD_NAMES]);
        expect(ICON_FIELD_NAMES).toContain('Zap');
        expect(ICON_FIELD_NAMES).toHaveLength(18);
    });

    it('binds the sentinel for an empty value and the name itself otherwise', () => {
        expect(build('').find('.stub-select').attributes('data-value')).toBe('__none');
        expect(build('Zap').find('.stub-select').attributes('data-value')).toBe('Zap');
    });

    it('emits the plain name, and the empty string for none — never the sentinel', async () => {
        const wrapper = build('');

        await wrapper.find('.pick-known').trigger('click');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['Shield']);

        await wrapper.find('.pick-none').trigger('click');
        expect(wrapper.emitted('update:modelValue')?.[1]).toEqual(['']);
    });

    it('keeps a stored name this build cannot render selected, and says what saving does to it', () => {
        const wrapper = build('NotAnIcon');

        // What the control shows is what the payload holds — no silent divergence.
        expect(wrapper.find('.stub-select').attributes('data-value')).toBe('NotAnIcon');
        expect(itemValues(wrapper)).toContain('NotAnIcon');
        expect(wrapper.text()).toContain('NotAnIcon is not one of the available icons');
    });

    it('prefers server-declared options when a future schema does send them', () => {
        const wrapper = build('', { ...ICON_FIELD, options: ['Zap', 'Shield'] });

        expect(itemValues(wrapper)).toEqual(['__none', 'Zap', 'Shield']);
    });
});
