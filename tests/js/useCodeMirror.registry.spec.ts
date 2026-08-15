import { describe, expect, it } from 'vitest';
import { GRAMMARS } from '@/composables/useCodeMirror';
import type { CodeLanguage } from '@/composables/useFormSchema';

/** Mirrors the CodeLanguage union. Adding a literal without a grammar fails here. */
const DECLARED: CodeLanguage[] = [
    'plaintext', 'javascript', 'typescript', 'json', 'html', 'css',
    'php', 'sql', 'markdown', 'yaml', 'xml', 'python', 'bash', 'vue',
];

describe('useCodeMirror grammar registry', () => {
    it('has a loader for every language except plaintext', () => {
        for (const lang of DECLARED) {
            if (lang === 'plaintext') continue;
            expect(typeof (GRAMMARS as any)[lang], `missing grammar for ${lang}`).toBe('function');
        }
    });

    it('has no grammar the union does not declare', () => {
        for (const key of Object.keys(GRAMMARS)) {
            expect(DECLARED).toContain(key as CodeLanguage);
        }
    });

    it('does not register a loader for plaintext', () => {
        expect((GRAMMARS as any).plaintext).toBeUndefined();
    });
});
