/**
 * Everything the block pipeline does AFTER the bytes are in hand: classify a
 * block against THIS install, lay it out on disk and write the manifest, the
 * loader map and the fixture.
 *
 * fetch-blocks.mjs supplies the bytes from the network; reclassify.mjs supplies
 * them from the vendored tree. Both share this file so availability is decided
 * in exactly one place.
 */

import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { exportedSymbols, importedSymbols, importSpecifiers, packageName } from './rewrite.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));

export const ROOT = path.resolve(here, '..', '..');
export const SOURCES = readJson(path.join(here, '_sources.json'));

export function readJson(file) {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
}

export function sha256(text) {
    return createHash('sha256').update(text, 'utf8').digest('hex');
}

export function writeFile(file, content) {
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, content);
}

export function rmDir(dir) {
    fs.rmSync(dir, { recursive: true, force: true });
}

/**
 * Reads package.json and resources/js/components/ui fresh on every call: the
 * whole point of re-running the pipeline is to re-decide against what is
 * installed NOW, not against what was installed when the manifest was written.
 */
export function installSnapshot(root = ROOT, sources = SOURCES) {
    const pkg = readJson(path.join(root, 'package.json'));
    const packages = new Set([
        ...Object.keys(pkg.dependencies ?? {}),
        ...Object.keys(pkg.devDependencies ?? {}),
        'vue',
    ]);
    const ui = new Set(fs.readdirSync(path.join(root, sources.uiDir)));
    const exportCache = new Map();

    const uiExports = name => {
        if (!exportCache.has(name)) {
            const index = path.join(root, sources.uiDir, name, 'index.ts');
            exportCache.set(name, fs.existsSync(index) ? exportedSymbols(fs.readFileSync(index, 'utf8')) : new Set());
        }
        return exportCache.get(name);
    };

    return { root, sources, packages, ui, uiExports };
}

/**
 * The post-condition. Returns the npm packages a block needs, the registry
 * components it needs that this install does not have, and throws on anything
 * that should never survive the rewrite.
 */
export function classify(snapshot, id, written) {
    const npm = new Set();
    const missingUi = new Set();
    const paths = new Set(written.map(f => f.target));

    for (const file of written) {
        for (const specifier of importSpecifiers(file.content)) {
            if (specifier.startsWith('@/components/ui/')) {
                const name = specifier.slice('@/components/ui/'.length).split('/')[0];
                if (!snapshot.ui.has(name)) {
                    missingUi.add(name);
                    continue;
                }
                // An installed component whose API predates the block is just as
                // unusable as an absent one — and it would break `vite build`.
                const exported = snapshot.uiExports(name);
                for (const symbol of importedSymbols(file.content, specifier)) {
                    if (!exported.has(symbol)) missingUi.add(`${name}.${symbol}`);
                }
                continue;
            }

            if (specifier.startsWith('@/lib/')) {
                const onDisk = path.join(snapshot.root, 'resources/js', specifier.slice(2));
                const found = ['', '.ts', '.js', '/index.ts'].some(ext => fs.existsSync(onDisk + ext));
                if (!found) throw new Error(`[${id}] ${file.target} imports ${specifier}, which does not exist.`);
                continue;
            }

            if (specifier.startsWith('./') || specifier.startsWith('../')) {
                const resolved = path.posix.normalize(path.posix.join(path.posix.dirname(file.target), specifier));
                const found = ['', '.ts', '.js', '.vue'].some(ext => paths.has(resolved + ext));
                if (!found) throw new Error(`[${id}] ${file.target} imports ${specifier}, which is not in the block.`);
                continue;
            }

            if (specifier.startsWith('@/') || specifier.startsWith('~/')) {
                throw new Error(`[${id}] ${file.target} kept an unrewritten alias: ${specifier}`);
            }

            const name = packageName(specifier);
            if (!snapshot.packages.has(name)) npm.add(name);
        }
    }

    return { npm: [...npm].sort(), missingUi: [...missingUi].sort() };
}

export function unavailableReason(npm, missingUi) {
    const parts = [];
    if (npm.length) parts.push(`npm: ${npm.join(', ')}`);
    if (missingUi.length) parts.push(`registry: ${missingUi.join(', ')}`);
    return parts.join(' · ');
}

export function toClientSchema(entry) {
    return {
        key: entry.key,
        titleKey: `blocks.entries.${entry.key}.title`,
        descriptionKey: `blocks.entries.${entry.key}.description`,
        category: entry.category,
        entryFile: entry.entryFile,
        available: entry.available,
        unavailableReason: entry.unavailableReason,
        registryDependencies: entry.registryDependencies,
        npmDependencies: entry.npmDependencies,
        tags: entry.tags,
        since: '2.6.0',
        viewport: entry.viewport,
    };
}

/** Mirrors SyncsBlockFixtures::normaliseFixture + PHP's JSON_PRETTY_PRINT. */
export function normalise(value) {
    if (Array.isArray(value)) return value.map(normalise);
    if (value && typeof value === 'object') {
        const out = {};
        for (const key of Object.keys(value).sort()) out[key] = normalise(value[key]);
        return out;
    }
    return value;
}

export function sortCollected(collected, sources = SOURCES) {
    collected.sort((a, b) => {
        const byCategory = sources.categoryOrder.indexOf(a.category) - sources.categoryOrder.indexOf(b.category);
        return byCategory !== 0 ? byCategory : a.key.localeCompare(b.key);
    });

    return collected;
}

/**
 * Lays the vendored bytes out on disk and writes every generated artefact.
 * Quarantined source keeps its bytes but not its extension: .txt is invisible
 * to vue-tsc, to vite and to import.meta.glob.
 */
export function emit(collected, { root = ROOT, sources = SOURCES, ref = null } = {}) {
    const outDir = path.join(root, sources.outDir);
    const quarantineDir = path.join(root, sources.quarantineDir);

    for (const dir of fs.existsSync(outDir) ? fs.readdirSync(outDir) : []) {
        if (dir !== 'index.ts') rmDir(path.join(outDir, dir));
    }

    const blocks = {};

    for (const block of sortCollected(collected, sources)) {
        const base = block.available ? path.join(outDir, block.key) : path.join(quarantineDir, block.key);
        const manifestFiles = [];

        for (const file of block.written) {
            const relative = block.available ? file.target : `${file.target}.txt`;
            writeFile(path.join(base, relative), file.content);
            manifestFiles.push({
                path: path.posix.join(block.available ? sources.outDir : sources.quarantineDir, block.key, relative),
                sha256: sha256(file.content),
            });
        }

        blocks[block.key] = {
            category: block.category,
            description: block.description,
            registryDependencies: block.registryDependencies,
            npmDependencies: block.npmDependencies,
            missingRegistryComponents: block.missingUi,
            available: block.available,
            unavailableReason: block.unavailableReason,
            entryFile: block.entryFile,
            tags: block.tags,
            viewport: block.viewport,
            files: manifestFiles,
        };
    }

    const manifest = {
        generatedAt: new Date().toISOString(),
        source: sources.api,
        ref: ref ?? sources.ref,
        blocks,
    };

    writeFile(path.join(root, sources.manifest), `${JSON.stringify(manifest, null, 2)}\n`);

    const available = collected.filter(b => b.available);

    const index = [
        '// GENERATED by scripts/shadcn/fetch-blocks.mjs — do not edit by hand.',
        '// Loaders are dynamic so every block stays its own lazy chunk.',
        'export const BLOCK_ENTRY_FILES: Record<string, string> = {',
        ...available.map(b => `    '${b.key}': '${b.entryFile}',`),
        '};',
        '',
        'export const BLOCK_IDS = Object.keys(BLOCK_ENTRY_FILES);',
        '',
    ].join('\n');

    writeFile(path.join(root, sources.indexFile), index);

    const fixture = normalise({
        blocks: collected.map(toClientSchema),
        categories: [...new Set(collected.map(b => b.category))],
    });

    writeFile(path.join(root, sources.fixture), `${JSON.stringify(fixture, null, 4)}\n`);

    return { total: collected.length, available: available.length };
}
