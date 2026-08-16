// Acquires the shadcn-vue examples verbatim, rewrites their aliases onto Myra's
// and emits examples-manifest.json. Run: node scripts/shadcn/fetch-examples.mjs
// Network + node built-ins only; nothing here runs at request time.

import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { REPO_ROOT, rewrite, classify } from './rewrite-examples.mjs';

const SOURCES = JSON.parse(fs.readFileSync(path.join(REPO_ROOT, 'scripts/shadcn/_sources-examples.json'), 'utf8'));
const EXAMPLES_DIR = path.join(REPO_ROOT, 'resources/js/examples');
const QUARANTINE_DIR = path.join(EXAMPLES_DIR, '_unavailable');
const UI_DIR = path.join(REPO_ROOT, 'resources/js/components/ui');
const LIB_DIR = path.join(REPO_ROOT, 'resources/js/lib');
const PACKAGE_JSON = JSON.parse(fs.readFileSync(path.join(REPO_ROOT, 'package.json'), 'utf8'));

const TEXT = /\.(vue|ts|js|mjs|json|css|md)$/;

const sha256 = value => crypto.createHash('sha256').update(value).digest('hex');
const headers = { 'User-Agent': 'myra-fetch-examples' };

async function raw(repoPath) {
    const res = await fetch(`${SOURCES.raw}/${SOURCES.sha}/${repoPath}`, { headers });
    if (!res.ok) throw new Error(`GET ${repoPath} -> ${res.status}`);
    return res.text();
}

async function tree() {
    const res = await fetch(`${SOURCES.api}/git/trees/${SOURCES.sha}?recursive=1`, { headers });
    if (!res.ok) throw new Error(`tree -> ${res.status}`);
    return (await res.json()).tree;
}

function walk(dir, base = dir, acc = []) {
    if (!fs.existsSync(dir)) return acc;
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        entry.isDirectory() ? walk(full, base, acc) : acc.push(path.relative(base, full).split(path.sep).join('/'));
    }
    return acc;
}

function write(target, content) {
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, content);
}

function rmrf(dir) {
    if (fs.existsSync(dir)) fs.rmSync(dir, { recursive: true, force: true });
}

async function collectFetched(blobs, id) {
    const prefix = `${SOURCES.examplesPath}/${id}/`;
    const files = [];

    for (const blob of blobs.filter(b => b.type === 'blob' && b.path.startsWith(prefix))) {
        files.push({ path: blob.path.slice(prefix.length), source: blob.path, content: await raw(blob.path) });
    }
    for (const extra of SOURCES.extraFiles?.[id] ?? []) {
        files.push({ path: extra.target, source: extra.source, content: await raw(extra.source) });
    }

    return files;
}

function manifestFiles(dir) {
    return walk(dir)
        .sort()
        .map(rel => ({ path: rel, sha256: sha256(fs.readFileSync(path.join(dir, rel))) }));
}

async function main() {
    const blobs = await tree();
    const manifest = {
        generatedAt: new Date().toISOString(),
        source: `${SOURCES.raw}/${SOURCES.sha}/${SOURCES.examplesPath}`,
        sha: SOURCES.sha,
        examples: {},
    };

    const ids = [...SOURCES.fetched, ...SOURCES.authored];

    for (const id of ids) {
        const authored = SOURCES.authored.includes(id);
        const liveDir = path.join(EXAMPLES_DIR, id);
        const deadDir = path.join(QUARANTINE_DIR, id);

        // Files that will be classified: fetched-and-rewritten, or the authored
        // tree already on disk.
        let candidates;
        if (authored) {
            candidates = walk(liveDir).map(rel => ({
                path: rel,
                source: null,
                content: fs.readFileSync(path.join(liveDir, rel), 'utf8'),
            }));
        } else {
            const fetched = await collectFetched(blobs, id);
            candidates = fetched.map(f => ({
                ...f,
                rewritten: TEXT.test(f.path) ? rewrite(f.content, f.path) : f.content,
            }));
        }

        const shellPath = `${id}/Page.vue`;
        const shellOnDisk = fs.existsSync(path.join(EXAMPLES_DIR, shellPath));

        // Classify against the rewritten text (authored files are already local).
        const classifyInput = candidates.map(f => ({ path: f.path, content: f.rewritten ?? f.content }));
        if (!authored && shellOnDisk) {
            classifyInput.push({ path: 'Page.vue', content: fs.readFileSync(path.join(EXAMPLES_DIR, shellPath), 'utf8') });
        }
        const { npmDependencies, unresolved } = classify(classifyInput, {
            uiDir: UI_DIR,
            libDir: LIB_DIR,
            packageJson: PACKAGE_JSON,
        });

        const available = unresolved.length === 0;

        if (authored) {
            if (!available) throw new Error(`Authored example [${id}] must resolve cleanly: ${unresolved.join(', ')}`);
        } else if (available) {
            rmrf(deadDir);
            for (const file of candidates) {
                write(path.join(liveDir, file.path), file.rewritten);
            }
        } else {
            // QUARANTINE. Verbatim upstream bytes, every file suffixed .txt so
            // vue-tsc never checks it, Vite never bundles it and the preview
            // glob never matches it. Fidelity is preserved; the build cannot break.
            rmrf(liveDir);
            for (const file of candidates) {
                write(path.join(deadDir, `${file.path}.txt`), file.content);
            }
        }

        const dir = available ? liveDir : deadDir;

        manifest.examples[id] = {
            origin: authored ? 'authored' : 'fetched',
            sourceSha: authored ? null : SOURCES.sha,
            description: null,
            registryDependencies: [...new Set(
                classifyInput.flatMap(f => [...f.content.matchAll(/from ["']@\/components\/ui\/([^"'/]+)["']/g)].map(m => m[1])),
            )].sort(),
            npmDependencies,
            available,
            unavailableReason: available ? null : `requires ${unresolved.join(', ')}`,
            shell: available ? shellPath : null,
            files: manifestFiles(dir),
        };
    }

    write(
        path.join(REPO_ROOT, 'scripts/shadcn/examples-manifest.json'),
        `${JSON.stringify(manifest, null, 4)}\n`,
    );

    const availableIds = ids.filter(id => manifest.examples[id].available);
    const shells = availableIds.map(id => `    '${id}': '${manifest.examples[id].shell}',`).join('\n');

    write(
        path.join(EXAMPLES_DIR, 'index.ts'),
        `// GENERATED by scripts/shadcn/fetch-examples.mjs — do not edit by hand.\n`
        + `// Keys are the AVAILABLE example ids; values are the shell paths the\n`
        + `// preview glob resolves. Quarantined examples are absent on purpose.\n\n`
        + `export const EXAMPLE_IDS = [\n${availableIds.map(id => `    '${id}',`).join('\n')}\n] as const;\n\n`
        + `export type ExampleId = (typeof EXAMPLE_IDS)[number];\n\n`
        + `export const EXAMPLE_SHELLS: Record<string, string> = {\n${shells}\n};\n`,
    );

    for (const id of ids) {
        const row = manifest.examples[id];
        console.log(`${row.available ? 'ok        ' : 'quarantine'} ${id.padEnd(16)} ${row.unavailableReason ?? ''}`);
    }
}

main().catch(error => {
    console.error(error);
    process.exit(1);
});
