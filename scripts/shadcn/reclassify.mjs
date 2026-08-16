/**
 * Re-decides block availability against the CURRENT install, offline.
 *
 *   node scripts/shadcn/reclassify.mjs
 *
 * The vendored bytes are already in the repo; what goes stale is the verdict.
 * Installing a registry component or an npm package makes quarantined blocks
 * shippable, and the manifest, the loader map, the fixture and the on-disk
 * layout all have to follow. This replays exactly the classification
 * fetch-blocks.mjs runs — same code path, no network, no upstream drift.
 */

import fs from 'node:fs';
import path from 'node:path';
import { classify, emit, installSnapshot, readJson, ROOT, SOURCES, unavailableReason } from './pipeline.mjs';

const sources = SOURCES;
const snapshot = installSnapshot(ROOT, sources);
const manifest = readJson(path.join(ROOT, sources.manifest));

/** The manifest path minus the block prefix and the quarantine .txt suffix. */
function targetOf(block, filePath) {
    const prefix = `${block.available ? sources.outDir : sources.quarantineDir}/${block.key}/`;
    const relative = filePath.startsWith(prefix) ? filePath.slice(prefix.length) : path.posix.basename(filePath);

    return block.available ? relative : relative.replace(/\.txt$/, '');
}

const collected = [];

for (const [key, spec] of Object.entries(manifest.blocks)) {
    const block = { key, available: spec.available };

    const written = (spec.files ?? []).map(file => {
        const absolute = path.join(ROOT, file.path);
        if (!fs.existsSync(absolute)) throw new Error(`[${key}] ${file.path} is in the manifest but not on disk.`);

        return { target: targetOf(block, file.path), content: fs.readFileSync(absolute, 'utf8') };
    });

    const { npm, missingUi } = classify(snapshot, key, written);
    const available = npm.length === 0 && missingUi.length === 0;

    collected.push({
        key,
        category: spec.category,
        description: spec.description ?? null,
        registryDependencies: spec.registryDependencies ?? [],
        npmDependencies: npm,
        missingUi,
        available,
        unavailableReason: available ? null : unavailableReason(npm, missingUi),
        tags: spec.tags ?? [],
        viewport: spec.viewport ?? 'full',
        entryFile: spec.entryFile ?? `${key}/Page.vue`,
        written,
        wasAvailable: spec.available,
    });
}

const { total, available } = emit(collected, { root: ROOT, sources, ref: manifest.ref });

console.log(`blocks: ${total} classified, ${available} available, ${total - available} quarantined`);
for (const block of collected.filter(b => b.available !== b.wasAvailable)) {
    console.log(`  ${block.wasAvailable ? 'quarantined' : 'released'} ${block.key}: ${block.unavailableReason ?? 'all dependencies installed'}`);
}
