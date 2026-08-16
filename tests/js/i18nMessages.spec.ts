import { describe, expect, it } from 'vitest';
import { baseCompile } from '@intlify/message-compiler';
import en from '@/i18n/locales/en.json';
import ms from '@/i18n/locales/ms.json';
import zh from '@/i18n/locales/zh.json';

const locales: Record<string, unknown> = { en, ms, zh };

function leaves(node: unknown, prefix = ''): [string, string][] {
    if (typeof node === 'string') return [[prefix, node]];
    if (node === null || typeof node !== 'object') return [];

    return Object.entries(node as Record<string, unknown>).flatMap(([key, child]) =>
        leaves(child, prefix === '' ? key : `${prefix}.${key}`),
    );
}

/**
 * Every message is compiled by vue-i18n at render time. An unescaped `{` opens
 * a named placeholder, so a literal brace in the copy THROWS the moment the
 * component renders. This compiles the whole catalogue instead of waiting for a
 * page to be visited.
 */
describe('i18n message catalogue', () => {
    for (const [locale, messages] of Object.entries(locales)) {
        it(`compiles every ${locale} message`, () => {
            const broken: string[] = [];

            for (const [key, message] of leaves(messages)) {
                const errors: string[] = [];

                try {
                    baseCompile(message, { onError: (e: { message: string }) => errors.push(e.message) });
                } catch (e) {
                    errors.push(String((e as Error).message));
                }

                if (errors.length > 0) {
                    broken.push(`${key}: ${JSON.stringify(message)} -> ${errors.join(' | ')}`);
                }
            }

            expect(broken).toEqual([]);
        });
    }

    it('renders the demo unchanged label as a literal brace pair', () => {
        const path = 'live.demo.unchanged';

        for (const [locale, messages] of Object.entries(locales)) {
            const found = leaves(messages).find(([key]) => key === path);

            expect(found, `${locale} is missing ${path}`).toBeTruthy();
            expect(found![1]).toContain("{'{'}");
        }
    });
});
