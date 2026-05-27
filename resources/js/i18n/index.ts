import { createI18n } from 'vue-i18n';
import en from './locales/en.json';

export type SupportedLocale = 'en' | 'ms' | 'zh';

export const SUPPORTED_LOCALES: { code: SupportedLocale; label: string; flag: string }[] = [
    { code: 'en', label: 'English', flag: '🇬🇧' },
    { code: 'ms', label: 'Bahasa Melayu', flag: '🇲🇾' },
    { code: 'zh', label: '中文', flag: '🇨🇳' },
];

function getSavedLocale(): SupportedLocale {
    const saved = localStorage.getItem('locale');
    if (saved && SUPPORTED_LOCALES.some(l => l.code === saved)) {
        return saved as SupportedLocale;
    }
    return 'en';
}

const i18n = createI18n({
    legacy: false,
    locale: getSavedLocale() as string,
    fallbackLocale: 'en',
    messages: { en },
});

const loadedLocales = new Set<string>(['en']);

export async function setLocale(locale: SupportedLocale) {
    if (!loadedLocales.has(locale)) {
        const messages = await import(`./locales/${locale}.json`);
        i18n.global.setLocaleMessage(locale, messages.default);
        loadedLocales.add(locale);
    }
    (i18n.global.locale as any).value = locale;
    localStorage.setItem('locale', locale);
    document.documentElement.setAttribute('lang', locale);
}

// Eagerly load saved locale if not English
const savedLocale = getSavedLocale();
if (savedLocale !== 'en') {
    setLocale(savedLocale);
}

export default i18n;
