import { readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * The isolation contract: a vendored block must never be reachable from the
 * admin shell. It is a reference page, not a component library — and a sidebar
 * block mounts its own SidebarProvider, so a stray import would hijack the real
 * one. This is the guard that keeps that structural, not conventional.
 */

const root = path.resolve(__dirname, '../..');

const ALLOWED_IMPORTERS = [
    'resources/js/Pages/Admin/Blocks/Preview.vue',
    'resources/js/components/admin/blocks/BlockFrame.vue',
];

function files(dir: string): string[] {
    const out: string[] = [];
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) out.push(...files(full));
        else out.push(full);
    }
    return out;
}

function relative(file: string): string {
    return path.relative(root, file).split(path.sep).join('/');
}

const scanned = ['resources/js/Pages', 'resources/js/Layouts', 'resources/js/components/ui']
    .map(dir => path.join(root, dir))
    .filter(dir => statSync(dir).isDirectory())
    .flatMap(files);

describe('block isolation', () => {
    it('is imported by nothing under Pages, Layouts or components/ui except the preview page', () => {
        const offenders = scanned
            .filter(file => /@\/blocks/.test(readFileSync(file, 'utf8')))
            .map(relative)
            .filter(file => !ALLOWED_IMPORTERS.includes(file));

        expect(offenders).toEqual([]);
    });

    it('is loaded from exactly one place in the app, and it is the frame', () => {
        const importers = files(path.join(root, 'resources/js'))
            .filter(file => !relative(file).startsWith('resources/js/blocks/'))
            .filter(file => {
                const source = readFileSync(file, 'utf8');
                return /from ['"]@\/blocks/.test(source) || /glob\(['"]@\/blocks/.test(source);
            })
            .map(relative);

        for (const importer of importers) expect(ALLOWED_IMPORTERS).toContain(importer);
        expect(importers).toContain('resources/js/components/admin/blocks/BlockFrame.vue');
    });

    it('never reaches back into the app pages from a block', () => {
        const offenders = files(path.join(root, 'resources/js/blocks'))
            .filter(file => file.endsWith('.vue') || file.endsWith('.ts'))
            .filter(file => /@\/(Pages|Layouts)\//.test(readFileSync(file, 'utf8')))
            .map(relative);

        expect(offenders).toEqual([]);
    });

    it('keeps quarantined source inert: nothing under _unavailable is a module', () => {
        const live = files(path.join(root, 'resources/js/blocks/_unavailable'))
            .map(relative)
            .filter(file => !file.endsWith('.txt'));

        expect(live).toEqual([]);
    });
});
