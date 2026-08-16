/**
 * Re-hashes the vendored block tree against the committed manifest.
 *
 *   node scripts/shadcn/verify.mjs
 *
 * Local and offline. The same assertion runs in PHPUnit
 * (tests/Feature/Blocks/BlockRegistryTest) so CI never depends on the network.
 */

import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');
const sources = JSON.parse(fs.readFileSync(path.join(here, '_sources.json'), 'utf8'));
const manifest = JSON.parse(fs.readFileSync(path.join(root, sources.manifest), 'utf8'));

const problems = [];

for (const [id, block] of Object.entries(manifest.blocks)) {
    for (const file of block.files) {
        const absolute = path.join(root, file.path);
        if (!fs.existsSync(absolute)) {
            problems.push(`${id}: missing ${file.path}`);
            continue;
        }
        const actual = createHash('sha256').update(fs.readFileSync(absolute, 'utf8'), 'utf8').digest('hex');
        if (actual !== file.sha256) problems.push(`${id}: ${file.path} drifted from the manifest hash`);
    }
}

const onDisk = fs.existsSync(path.join(root, sources.outDir))
    ? fs.readdirSync(path.join(root, sources.outDir)).filter(name => name !== 'index.ts' && name !== '_unavailable')
    : [];
const expected = Object.entries(manifest.blocks).filter(([, b]) => b.available).map(([id]) => id);

for (const id of onDisk) if (!expected.includes(id)) problems.push(`${id}: on disk but not available in the manifest`);
for (const id of expected) if (!onDisk.includes(id)) problems.push(`${id}: available in the manifest but not on disk`);

if (problems.length) {
    console.error(problems.join('\n'));
    process.exit(1);
}

console.log(`blocks verified: ${Object.keys(manifest.blocks).length} entries, ${expected.length} available`);
