/**
 * Vendors every shadcn-vue registry block into resources/js/blocks/.
 *
 *   node scripts/shadcn/fetch-blocks.mjs
 *
 * The CLI cannot install blocks (https://shadcn-vue.com/r/styles/new-york/{id}.json
 * is 404 for every block), so the public API is the only source. Nothing is
 * retyped: every byte is fetched, rewritten by the ordered table in rewrite.mjs
 * and then CLASSIFIED by pipeline.mjs — the post-condition, not the rules, is
 * what decides whether a block ships live or is quarantined as inert .txt.
 *
 * Re-deciding availability WITHOUT re-fetching is scripts/shadcn/reclassify.mjs.
 */

import { rewrite } from './rewrite.mjs';
import { classify, emit, installSnapshot, ROOT, SOURCES, unavailableReason } from './pipeline.mjs';

const sources = SOURCES;
const snapshot = installSnapshot(ROOT, sources);

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

async function main() {
    const all = await getJson(`${sources.api}/all-items`);
    const ids = Object.values(all)
        .filter(item => item.type === 'registry:block')
        .map(item => item.name);

    const collected = [];

    for (const id of ids) {
        const { item } = await getJson(`${sources.api}/block/${encodeURIComponent(id)}`);
        const files = await filesFor(id, item, all[id]);
        const entryFile = entryOf(files);

        const written = files.map(file => {
            const target = targetPath(file, entryFile);
            return { target, content: rewrite(file.content, target) };
        });

        const { npm, missingUi } = classify(snapshot, id, written);
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

    const { total, available } = emit(collected, { root: ROOT, sources });

    console.log(`blocks: ${total} fetched, ${available} available, ${total - available} quarantined`);
    for (const block of collected.filter(b => !b.available)) {
        console.log(`  quarantined ${block.key}: ${block.unavailableReason}`);
    }
}

main().catch(error => {
    console.error(error.message);
    process.exit(1);
});
