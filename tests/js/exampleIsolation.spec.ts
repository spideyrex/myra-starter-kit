import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const BLESSED = 'resources/js/Pages/Admin/Examples/Preview.vue';

function walk(dir: string, acc: string[] = []): string[] {
    if (!fs.existsSync(dir)) return acc;

    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        entry.isDirectory() ? walk(full, acc) : acc.push(full);
    }

    return acc;
}

function sources(relative: string, pattern = /\.(vue|ts)$/): string[] {
    return walk(path.join(ROOT, relative)).filter(file => pattern.test(file));
}

const rel = (file: string) => path.relative(ROOT, file).split(path.sep).join('/');

describe('example isolation', () => {
    it('lets exactly one file reach into @/examples', () => {
        const offenders = [
            ...sources('resources/js/Pages'),
            ...sources('resources/js/Layouts'),
            ...sources('resources/js/components'),
            ...sources('resources/js/composables'),
        ]
            .filter(file => /['"`]@\/examples/.test(fs.readFileSync(file, 'utf8')))
            .map(rel);

        expect(offenders).toEqual([BLESSED]);
    });

    it('keeps the app out of the vendored tree', () => {
        const offenders = sources('resources/js/examples')
            .filter(file => /from ['"]@\/(Pages|Layouts)\//.test(fs.readFileSync(file, 'utf8')))
            .map(rel);

        expect(offenders).toEqual([]);
    });

    it('never lets a vendored example shadow a registry primitive', () => {
        const shadowed = sources('resources/js/examples')
            .filter(file => rel(file).includes('/components/ui/'))
            .map(rel);

        expect(shadowed).toEqual([]);
    });

    it('keeps quarantined source unbundleable', () => {
        const quarantined = walk(path.join(ROOT, 'resources/js/examples/_unavailable'));

        expect(quarantined.length).toBeGreaterThan(0);
        expect(quarantined.filter(file => !file.endsWith('.txt')).map(rel)).toEqual([]);
    });

    it('resolves every example the preview glob can see', () => {
        const shells = import.meta.glob('@/examples/**/*.vue');
        const keys = Object.keys(shells);

        expect(keys.length).toBeGreaterThan(0);
        expect(keys.filter(key => key.includes('_unavailable'))).toEqual([]);
    });
});
