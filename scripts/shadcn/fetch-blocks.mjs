/**
 * Vendors every shadcn-vue registry block into resources/js/blocks/.
 *
 *   node scripts/shadcn/fetch-blocks.mjs
 *
 * The CLI cannot install blocks (https://shadcn-vue.com/r/styles/new-york/{id}.json
 * is 404 for every block), so the public API is the only source. Nothing is
 * retyped: every byte is fetched, rewritten by the ordered table in rewrite.mjs
 * and then CLASSIFIED — the post-condition, not the rules, is what decides
 * whether a block ships live or is quarantined as inert .txt.
 */

import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { exportedSymbols, importedSymbols, importSpecifiers, packageName, rewrite } from './rewrite.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');
const sources = readJson(path.join(here, '_sources.json'));
const pkg = readJson(path.join(root, 'package.json'));
const installedPackages = new Set([
    ...Object.keys(pkg.dependencies ?? {}),
    ...Object.keys(pkg.devDependencies ?? {}),
    'vue',
]);
const installedUi = new Set(fs.readdirSync(path.join(root, sources.uiDir)));
const uiExportCache = new Map();

function readJson(file) {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function sha256(text) {
    return createHash('sha256').update(text, 'utf8').digest('hex');
}

async function getJson(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`GET ${url} -> ${res.status}`);
    return res.json();
}

async function getText(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`GET ${url} -> ${res.status}`);
    return res.text();
}

function categoryOf(id, categories) {
    for (const slug of sources.categoryOrder) {
        if ((categories ?? []).includes(slug)) return slug;
    }
    const prefix = id.split('-')[0];
    if (sources.categoryOrder.includes(prefix)) return prefix;
    return /^Chart/.test(id) ? 'chart' : 'other';
}

function describe(item, files) {
    if (item.description) return item.description;
    const joined = files.map(f => f.content ?? '').join('\n');
    const match = /(?:export\s+)?const description\s*(?:\n\s*)?=\s*["']([^"']+)["']/.exec(joined);
    return match ? match[1] : null;
}

/** Upstream marks the routable file registry:page; charts ship a single file. */
function entryOf(files) {
    return files.find(f => f.type === 'registry:page') ?? files[0];
}

function targetPath(file, entry) {
    return file === entry ? 'Page.vue' : file.path.replace(/^\.\//, '');
}

function rmDir(dir) {
    fs.rmSync(dir, { recursive: true, force: true });
}

function writeFile(file, content) {
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, content);
}

/**
 * The post-condition. Returns the npm packages a block needs, the registry
 * components it needs that this install does not have, and throws on anything
 * that should never survive the rewrite.
 */
function uiExports(name) {
    if (!uiExportCache.has(name)) {
        const index = path.join(root, sources.uiDir, name, 'index.ts');
        uiExportCache.set(name, fs.existsSync(index) ? exportedSymbols(fs.readFileSync(index, 'utf8')) : new Set());
    }
    return uiExportCache.get(name);
}

function classify(id, written) {
    const npm = new Set();
    const missingUi = new Set();
    const paths = new Set(written.map(f => f.target));

    for (const file of written) {
        for (const specifier of importSpecifiers(file.content)) {
            if (specifier.startsWith('@/components/ui/')) {
                const name = specifier.slice('@/components/ui/'.length).split('/')[0];
                if (!installedUi.has(name)) {
                    missingUi.add(name);
                    continue;
                }
                // An installed component whose API predates the block is just as
                // unusable as an absent one — and it would break `vite build`.
                const exported = uiExports(name);
                for (const symbol of importedSymbols(file.content, specifier)) {
                    if (!exported.has(symbol)) missingUi.add(`${name}.${symbol}`);
                }
                continue;
            }

            if (specifier.startsWith('@/lib/')) {
                const onDisk = path.join(root, 'resources/js', specifier.slice(2));
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
            if (!installedPackages.has(name)) npm.add(name);
        }
    }

    return { npm: [...npm].sort(), missingUi: [...missingUi].sort() };
}

function unavailableReason(npm, missingUi) {
    const parts = [];
    if (npm.length) parts.push(`npm: ${npm.join(', ')}`);
    if (missingUi.length) parts.push(`registry: ${missingUi.join(', ')}`);
    return parts.join(' · ');
}

async function filesFor(id, item, registryItem) {
    const served = item.files.map(f => ({ ...f, content: f.content ?? '' }));
    if (served.some(f => f.content.length > 0)) return served;

    // The block API serves the chart blocks with empty bodies; recover the exact
    // bytes from the pinned ref instead of retyping them. Only the registry
    // index keeps the repo-relative path.
    const out = [];
    for (const [i, file] of served.entries()) {
        const repoPath = registryItem?.files?.[i]?.path ?? file.path;
        const url = `${sources.raw}/${sources.ref}/${sources.rawPrefix}/${repoPath}`;
        out.push({ ...file, content: await getText(url) });
    }
    return out;
}

function toClientSchema(entry) {
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
function normalise(value) {
    if (Array.isArray(value)) return value.map(normalise);
    if (value && typeof value === 'object') {
        const out = {};
        for (const key of Object.keys(value).sort()) out[key] = normalise(value[key]);
        return out;
    }
    return value;
}

async function main() {
    const all = await getJson(`${sources.api}/all-items`);
    const ids = Object.values(all)
        .filter(item => item.type === 'registry:block')
        .map(item => item.name);

    const blocks = {};
    const outDir = path.join(root, sources.outDir);
    const quarantineDir = path.join(root, sources.quarantineDir);

    for (const dir of fs.existsSync(outDir) ? fs.readdirSync(outDir) : []) {
        if (dir !== 'index.ts') rmDir(path.join(outDir, dir));
    }

    const collected = [];

    for (const id of ids) {
        const { item } = await getJson(`${sources.api}/block/${encodeURIComponent(id)}`);
        const files = await filesFor(id, item, all[id]);
        const entryFile = entryOf(files);

        const written = files.map(file => {
            const target = targetPath(file, entryFile);
            return { target, content: rewrite(file.content, target) };
        });

        const { npm, missingUi } = classify(id, written);
        const available = npm.length === 0 && missingUi.length === 0;

        collected.push({
            key: id,
            category: categoryOf(id, item.categories),
            description: describe(item, files),
            registryDependencies: (item.registryDependencies ?? []).slice().sort(),
            npmDependencies: npm,
            missingUi,
            available,
            unavailableReason: available ? null : unavailableReason(npm, missingUi),
            tags: [...new Set([...(item.categories ?? []), categoryOf(id, item.categories)])].sort(),
            viewport: categoryOf(id, item.categories) === 'chart' ? '768' : 'full',
            entryFile: `${id}/Page.vue`,
            written,
        });
    }

    collected.sort((a, b) => {
        const byCategory = sources.categoryOrder.indexOf(a.category) - sources.categoryOrder.indexOf(b.category);
        return byCategory !== 0 ? byCategory : a.key.localeCompare(b.key);
    });

    for (const block of collected) {
        const base = block.available ? path.join(outDir, block.key) : path.join(quarantineDir, block.key);
        const manifestFiles = [];

        for (const file of block.written) {
            // Quarantined source keeps its bytes but not its extension: .txt is
            // invisible to vue-tsc, to vite and to import.meta.glob.
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
        ref: sources.ref,
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

    const quarantined = collected.length - available.length;
    console.log(`blocks: ${collected.length} fetched, ${available.length} available, ${quarantined} quarantined`);
    for (const block of collected.filter(b => !b.available)) {
        console.log(`  quarantined ${block.key}: ${block.unavailableReason}`);
    }
}

main().catch(error => {
    console.error(error.message);
    process.exit(1);
});
