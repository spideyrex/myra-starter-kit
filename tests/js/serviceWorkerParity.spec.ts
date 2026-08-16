import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * The rule lives twice: once in TypeScript (the source of truth the app and the
 * matrix test use) and once in plain JS inside the worker, which cannot import
 * from the bundle. A divergence between the two copies is the failure mode this
 * cluster cannot afford, so it is a test rather than a convention.
 */

const root = path.resolve(__dirname, '../..');

/** Extracts a function body by brace matching from the declaration onwards. */
function body(source: string, declaration: string): string {
    const start = source.indexOf(declaration);
    expect(start, `missing declaration: ${declaration}`).toBeGreaterThan(-1);

    const open = source.indexOf('{', start);
    let depth = 0;

    for (let i = open; i < source.length; i++) {
        if (source[i] === '{') depth++;
        if (source[i] === '}') depth--;
        if (depth === 0) return source.slice(open + 1, i);
    }

    throw new Error(`unbalanced braces after ${declaration}`);
}

function normalise(text: string): string {
    return text
        .split('\n')
        .map((line) => line.replace(/\/\/.*$/, '').trim())
        .filter((line) => line !== '')
        .join('\n')
        .replace(/\s+/g, ' ');
}

describe('isCacheable parity', () => {
    const worker = readFileSync(path.join(root, 'public/myra-sw.js'), 'utf8');
    const ts = readFileSync(path.join(root, 'resources/js/pwa/register.ts'), 'utf8');

    it('the worker copy and the TypeScript copy have identical bodies', () => {
        const fromWorker = normalise(body(worker, 'function isCacheable(request)'));
        const fromTs = normalise(body(ts, 'export function isCacheableRequest(request: CacheableRequest): boolean'));

        expect(fromTs).toBe(fromWorker);
    });

    it('both copies share the same shell path list and asset prefix', () => {
        for (const line of [
            "const SHELL_PATHS = ['/offline.html', '/manifest.webmanifest', '/favicon.ico'];",
            "const ASSET_PREFIX = '/build/';",
        ]) {
            expect(worker).toContain(line);
            expect(ts).toContain(line);
        }
    });

    it('the worker never calls respondWith for a request outside the allow-list', () => {
        expect(normalise(body(worker, "self.addEventListener('fetch'"))).toContain(
            'if (!isCacheable(event.request)) return;',
        );
    });

    it('the worker carries a second, independent gate before cache.put', () => {
        const storable = normalise(body(worker, 'function isStorable(res)'));

        expect(storable).toContain("res.type !== 'basic'");
        expect(storable).toContain("vary.includes('cookie')");
        expect(storable).toContain("control.includes('private')");
        expect(storable).toContain("control.includes('no-store')");
        expect(worker).toContain('if (isStorable(res))');
    });
});
