import { describe, expect, it, vi } from 'vitest';
import {
    IMPORT_FAILED_KEY,
    importErrorMessage,
    useImportRunner,
} from '@/composables/useImportRunner';

function httpError(status: number, data: any) {
    return Object.assign(new Error(`Request failed with status code ${status}`), {
        response: { status, data },
    });
}

const HTML_BODY = '<html><body><h1>Whoops</h1><pre>App\\Http\\Controllers\\Admin\\ImportController</pre></body></html>';

describe('importErrorMessage', () => {
    it('surfaces the message from a JSON refusal payload', () => {
        const e = httpError(422, { message: 'The file has more than 1 rows.', max: 1 });

        expect(importErrorMessage(e)).toBe('The file has more than 1 rows.');
    });

    it('falls back to the generic key for an HTML error page, never the raw body', () => {
        const message = importErrorMessage(httpError(500, HTML_BODY));

        expect(message).toBe(IMPORT_FAILED_KEY);
        expect(message).not.toContain('<html');
        expect(message).not.toContain('ImportController');
    });

    it('falls back to the generic key when the JSON body carries no message', () => {
        expect(importErrorMessage(httpError(422, { errors: { file: ['bad'] } }))).toBe(IMPORT_FAILED_KEY);
    });

    it('uses the supplied fallback so the caller can translate it', () => {
        expect(importErrorMessage(httpError(500, HTML_BODY), () => 'translated')).toBe('translated');
    });

    it('keeps the message of a transport error, which carries no body', () => {
        expect(importErrorMessage(new Error('network'))).toBe('network');
    });
});

describe('useImportRunner refusal handling', () => {
    it('surfaces the refusal message from a 422 JSON body', async () => {
        const commit = vi.fn().mockRejectedValue(
            httpError(422, { message: 'The file has more than 1 rows.', max: 1 }),
        );

        const runner = useImportRunner(commit);
        await runner.run();

        expect(runner.error.value).toBe('The file has more than 1 rows.');
        expect(runner.status.value).toBe('idle');
    });

    it('never renders an HTML error page into the error slot', async () => {
        const commit = vi.fn().mockRejectedValue(httpError(500, HTML_BODY));

        const runner = useImportRunner(commit, { fallback: () => 'translated failure' });
        await runner.run();

        expect(runner.error.value).toBe('translated failure');
        expect(runner.error.value).not.toContain('<html');
    });
});
