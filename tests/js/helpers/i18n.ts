import { createI18n } from 'vue-i18n';
import en from '@/i18n/locales/en.json';

export function testI18n() {
    return createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en', messages: { en } });
}

/** reka-ui Select/TagsInput need a real popper; stub them to plain slot wrappers. */
export const uiStubs = {
    Select: { template: '<div class="stub-select"><slot /></div>' },
    SelectTrigger: { template: '<div class="stub-select-trigger"><slot /></div>' },
    SelectContent: { template: '<div class="stub-select-content"><slot /></div>' },
    SelectItem: { template: '<div class="stub-select-item"><slot /></div>' },
    SelectValue: { template: '<span class="stub-select-value" />' },
    TagsInput: { template: '<div class="stub-tags"><slot /></div>' },
    TagsInputItem: { template: '<div><slot /></div>' },
    TagsInputItemText: { template: '<span />' },
    TagsInputItemDelete: { template: '<button type="button" />' },
    TagsInputInput: { template: '<span />' },
};
