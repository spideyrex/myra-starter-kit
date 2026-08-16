/**
 * Finds components a vendored block renders but never binds.
 *
 * Two blocks shipped `<Plus />` with no import: Vue resolves nothing, warns at
 * runtime and drops the element, and neither vue-tsc (blocks are excluded from
 * tsconfig) nor `vite build` (the template compiles to a resolveComponent call)
 * says a word. The template AST does.
 *
 * A tag is resolved if <script setup> binds the name — an import, or any
 * top-level declaration — since that is exactly what the SFC compiler looks at.
 */

import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@vue/compiler-sfc';
import { walkFiles } from './a11y-scan.mjs';

const ELEMENT = 1;

/** Resolved by the runtime itself, never by a binding. */
const BUILT_INS = new Set([
    'Transition', 'TransitionGroup', 'KeepAlive', 'Teleport', 'Suspense',
    'Fragment', 'Text', 'Comment', 'Static',
]);

function walk(node, visit) {
    if (node.type === ELEMENT) visit(node);
    for (const child of node.children ?? []) {
        if (child && typeof child === 'object' && 'type' in child) walk(child, visit);
    }
}

/** Every name <script setup> puts in template scope. */
export function scriptBindings(script) {
    const out = new Set();
    if (!script) return out;

    for (const match of script.matchAll(/import\s+(?:type\s+)?([\s\S]*?)\s+from\s*["'][^"']+["']/g)) {
        const clause = match[1];

        for (const named of clause.matchAll(/\{([^}]*)\}/g)) {
            for (const raw of named[1].split(',')) {
                const name = raw.trim().split(/\s+as\s+/).pop()?.replace(/^type\s+/, '').trim();
                if (name) out.add(name);
            }
        }

        const bare = clause.replace(/\{[^}]*\}/g, '').replace(/\*\s+as\s+/, '').split(',')[0].trim();
        if (bare) out.add(bare);
    }

    for (const match of script.matchAll(/(?:^|\n)\s*(?:export\s+)?(?:const|let|var|function|class)\s+([A-Za-z0-9_$]+)/g)) {
        out.add(match[1]);
    }

    return out;
}

/**
 * @param {string} source
 * @param {string} [selfName] the file's own basename — an SFC resolves its own
 *   name for recursion, so <Tree> inside Tree.vue needs no binding.
 * @returns {string[]} component tags used but never bound, sorted
 */
export function scanFile(source, selfName = '') {
    const { descriptor } = parse(source);
    const ast = descriptor.template?.ast;
    if (!ast) return [];

    const bound = scriptBindings(`${descriptor.scriptSetup?.content ?? ''}\n${descriptor.script?.content ?? ''}`);
    if (selfName) bound.add(selfName);
    const unresolved = new Set();

    walk(ast, node => {
        const tag = node.tag ?? '';
        // Lower-case tags are native elements; `component` is the dynamic form.
        if (!/^[A-Z]/.test(tag) || BUILT_INS.has(tag)) return;
        if (!bound.has(tag)) unresolved.add(tag);
    });

    return [...unresolved].sort();
}

/** @returns {Record<string,string[]>} 'block/file.vue' -> unbound tags */
export function scanBlocks(blocksDir) {
    const out = {};

    for (const id of fs.readdirSync(blocksDir)) {
        const dir = path.join(blocksDir, id);
        if (id === '_unavailable' || !fs.statSync(dir).isDirectory()) continue;

        for (const file of walkFiles(dir)) {
            if (!file.endsWith('.vue')) continue;

            const missing = scanFile(fs.readFileSync(file, 'utf8'), path.basename(file, '.vue'));
            if (missing.length) out[path.relative(blocksDir, file).split(path.sep).join('/')] = missing;
        }
    }

    return out;
}
