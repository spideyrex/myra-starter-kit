// Re-hashes the committed example tree against examples-manifest.json and
// exits 1 on drift. LOCAL/OPTIONAL command — the merge gate is the PHPUnit
// hash guard, which needs no network. Run: node scripts/shadcn/verify-examples.mjs

import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { REPO_ROOT } from './rewrite-examples.mjs';

const manifest = JSON.parse(fs.readFileSync(path.join(REPO_ROOT, 'scripts/shadcn/examples-manifest.json'), 'utf8'));
const EXAMPLES_DIR = path.join(REPO_ROOT, 'resources/js/examples');

const problems = [];

for (const [id, row] of Object.entries(manifest.examples)) {
    const dir = row.available ? path.join(EXAMPLES_DIR, id) : path.join(EXAMPLES_DIR, '_unavailable', id);

    for (const file of row.files) {
        const target = path.join(dir, file.path);
        if (!fs.existsSync(target)) {
            problems.push(`missing  ${id}/${file.path}`);
            continue;
        }
        const actual = crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex');
        if (actual !== file.sha256) problems.push(`drifted  ${id}/${file.path}`);
    }
}

if (problems.length > 0) {
    console.error(problems.join('\n'));
    console.error('\nRe-run: node scripts/shadcn/fetch-examples.mjs');
    process.exit(1);
}

console.log(`ok — ${Object.keys(manifest.examples).length} examples verified against the manifest`);
