// Bundle B's LOCAL copy of the rewrite table (A owns scripts/shadcn/rewrite.mjs).
// The duplication is deliberate: the two bundles must be independently mergeable.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

/**
 * ORDERED rule table. Applied top to bottom. The script never assumes which
 * rules fire — classify() asserts the post-condition instead.
 *
 * The last two rules are B-local additions the block table does not carry:
 * Myra ships `lucide-vue-next`, not `@lucide/vue`, and the upstream examples
 * import a shared `@/components/Icons` module that is vendored per-example.
 */
export const RULES = [
    [/from (["'])@\/registry\/[^"']*\/ui\/([^"']+)\1/g, "from '@/components/ui/$2'"],
    [/from (["'])[@~]\/styles\/[^"']*\/ui\/([^"']+)\1/g, "from '@/components/ui/$2'"],
    [/from (["'])@\/registry\/[^"']*\/lib\/utils\1/g, "from '@/lib/utils'"],
    [/from (["'])@\/components\/(?!ui\/)([A-Z][\w]*\.vue)\1/g, "from '<<SIBLING>>$2'"],
    [/from (["'])@lucide\/vue\1/g, "from 'lucide-vue-next'"],
    [/from (["'])@\/components\/Icons\1/g, "from '<<SIBLING>>Icons'"],
];

/**
 * `filePath` is the path RELATIVE TO THE EXAMPLE ROOT. A sibling-component
 * alias has to walk back out of whatever sub-directory the importer sits in,
 * so the depth is part of the rewrite, not an assumption about placement.
 */
export function rewrite(content, filePath = '') {
    let out = content;
    for (const [pattern, replacement] of RULES) {
        out = out.replace(pattern, replacement);
    }

    const depth = filePath.split('/').length - 1;
    const sibling = depth === 0 ? './components/' : `${'../'.repeat(depth)}components/`;

    return injectVueAutoImports(out.replaceAll('<<SIBLING>>', sibling));
}

/**
 * Upstream apps/v4 is a NUXT app, so `ref`/`computed`/… are auto-imported by
 * the build. Myra's Vite build has no such facility and `vue-tsc --noEmit` is a
 * merge gate, so the rewrite reproduces the auto-import explicitly rather than
 * leaving a file that only compiles under someone else's toolchain.
 */
export const VUE_AUTO_IMPORTS = [
    'ref', 'computed', 'reactive', 'readonly', 'watch', 'watchEffect', 'onMounted',
    'onUnmounted', 'onBeforeUnmount', 'nextTick', 'shallowRef', 'toRef', 'toRefs',
    'provide', 'inject', 'h', 'defineComponent', 'useTemplateRef', 'useId',
];

export function injectVueAutoImports(source) {
    const imported = new Set();
    for (const m of source.matchAll(/import\s*(?:type\s*)?\{([^}]*)\}\s*from\s*["']vue["']/g)) {
        m[1].split(',').map(s => s.trim().replace(/^type\s+/, '')).forEach(n => imported.add(n));
    }

    const missing = VUE_AUTO_IMPORTS.filter(
        name => new RegExp(`(^|[^\\w.$])${name}\\s*[(<]`).test(stripComments(source)) && !imported.has(name),
    );

    if (missing.length === 0) return source;

    const line = `import { ${missing.join(', ')} } from 'vue'`;
    const scriptOpen = /<script setup[^>]*>\n/.exec(source);

    if (scriptOpen) {
        const at = scriptOpen.index + scriptOpen[0].length;
        return `${source.slice(0, at)}${line}\n${source.slice(at)}`;
    }

    return `${line}\n${source}`;
}

/** Strip comments so a commented-out import is never mistaken for a real one. */
function stripComments(source) {
    return source
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/(^|[^:"'`\\])\/\/[^\n]*/g, '$1');
}

export function importSpecifiers(source) {
    const bare = stripComments(source);
    const out = new Set();
    for (const m of bare.matchAll(/(?:from|import)\s*\(?\s*(["'])([^"']+)\1/g)) {
        out.add(m[2]);
    }
    return [...out];
}

/**
 * v9-only surface of @tanstack/vue-table. Myra pins ^8.21.3, so a file naming
 * any of these is API-incompatible even though the package name resolves.
 */
export const TANSTACK_V9_SYMBOLS = ['useTable', 'tableFeatures', 'createPaginatedRowModel', 'createSortedRowModel', 'createFilteredRowModel'];

export function usesTanstackV9(source) {
    const bare = stripComments(source);
    for (const m of bare.matchAll(/import\s*(?:type\s*)?\{([^}]*)\}\s*from\s*["']@tanstack\/vue-table["']/g)) {
        const named = m[1].split(',').map(s => s.trim().replace(/^type\s+/, '').split(/\s+as\s+/)[0]);
        if (named.some(n => TANSTACK_V9_SYMBOLS.includes(n))) return true;
    }
    return false;
}

function packageNameOf(specifier) {
    const parts = specifier.split('/');
    return specifier.startsWith('@') ? parts.slice(0, 2).join('/') : parts[0];
}

/**
 * THE POST-CONDITION. Classifies every import of every rewritten file.
 *
 * Unlike bundle A's block pipeline this never hard-fails on an unresolvable
 * `@/` alias: the three registry components the upstream examples reach for
 * (chart, empty, item) are not installed here and §0.2 forbids adding them, so
 * an unresolvable alias is a QUARANTINE reason with fidelity preserved rather
 * than a build break. A rewritten file that resolves cleanly is still asserted.
 */
export function classify(files, opts) {
    const { uiDir, libDir, packageJson } = opts;
    const installed = new Set([
        ...Object.keys(packageJson.dependencies ?? {}),
        ...Object.keys(packageJson.devDependencies ?? {}),
    ]);

    const npmDependencies = new Set();
    const unresolved = [];
    const relativeTargets = new Set(files.map(f => f.path));

    for (const file of files) {
        if (!/\.(vue|ts|js|mjs)$/.test(file.path)) continue;

        if (usesTanstackV9(file.content)) {
            unresolved.push('@tanstack/vue-table@^9 (v9-only API against the pinned ^8.21.3)');
        }

        for (const spec of importSpecifiers(file.content)) {
            if (spec.startsWith('@/components/ui/')) {
                const name = spec.slice('@/components/ui/'.length).split('/')[0];
                if (!fs.existsSync(path.join(uiDir, name))) unresolved.push(`@/components/ui/${name}`);
                continue;
            }
            if (spec.startsWith('@/lib/')) {
                const rel = spec.slice('@/lib/'.length);
                const hit = ['', '.ts', '.js', '/index.ts'].some(ext => fs.existsSync(path.join(libDir, rel + ext)));
                if (!hit) unresolved.push(spec);
                continue;
            }
            if (spec.startsWith('./') || spec.startsWith('../')) {
                const base = path.posix.normalize(path.posix.join(path.posix.dirname(file.path), spec));
                const hit = ['', '.ts', '.js', '.vue', '/index.ts'].some(ext => relativeTargets.has(base + ext));
                if (!hit) unresolved.push(spec);
                continue;
            }
            if (spec.startsWith('@/')) {
                unresolved.push(spec);
                continue;
            }
            const pkg = packageNameOf(spec);
            npmDependencies.add(pkg);
            if (!installed.has(pkg)) unresolved.push(pkg);
        }
    }

    return {
        npmDependencies: [...npmDependencies].sort(),
        unresolved: [...new Set(unresolved)].sort(),
    };
}
