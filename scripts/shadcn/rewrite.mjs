/**
 * Import rewriting for vendored shadcn-vue block source.
 *
 * The table is ordered and data driven so it can be asserted on. Nothing here
 * decides whether a block is usable: fetch-blocks.mjs classifies every import
 * specifier AFTER rewriting and fails (or quarantines) on the result.
 */

/** Path from a file inside a block to the block's components/ directory. */
export function componentsPrefix(target = 'Page.vue') {
    const dir = target.includes('/') ? target.slice(0, target.lastIndexOf('/')) : '';
    if (dir === '') return './components/';
    if (dir === 'components') return './';
    return `${'../'.repeat(dir.split('/').length)}components/`;
}

export function rulesFor(target = 'Page.vue') {
    const siblings = componentsPrefix(target);

    return [
        // Upstream registry aliases -> this app's components.json aliases.
        [/from (["'])@\/registry\/[^"']*\/ui\/([^"']+)\1/g, "from '@/components/ui/$2'"],
        [/from (["'])[@~]\/styles\/[^"']*\/ui\/([^"']+)\1/g, "from '@/components/ui/$2'"],
        [/from (["'])@\/registry\/[^"']*\/lib\/utils\1/g, "from '@/lib/utils'"],
        // A block referencing its own sibling through the registry root.
        [/from (["'])[@~]\/registry\/[^"']*\/blocks\/[^"'/]+\/components\/([^"']+)\1/g, `from '${siblings}$2'`],
        // The upstream demo app keeps block siblings at @/components/Foo.vue.
        [/from (["'])@\/components\/(?!ui\/)([A-Z][\w]*\.vue)\1/g, `from '${siblings}$2'`],
        // Myra pins lucide-vue-next; @lucide/vue is the same icon set, same names.
        [/from (["'])@lucide\/vue\1/g, "from 'lucide-vue-next'"],
    ];
}

/** The table as it applies to a block's entry file, for introspection. */
export const RULES = rulesFor('Page.vue');

export function rewrite(content, target = 'Page.vue') {
    let out = content;
    for (const [pattern, replacement] of rulesFor(target)) out = out.replace(pattern, replacement);
    return out;
}

/**
 * Every static import specifier in a file, in source order. Whole-line comments
 * are dropped first: upstream leaves commented-out imports (recharts) in the
 * chart blocks, and a commented import is not a dependency.
 */
export function importSpecifiers(content) {
    const live = content
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/<!--[\s\S]*?-->/g, '')
        .split('\n')
        .filter(line => !line.trimStart().startsWith('//'))
        .join('\n');

    const found = [];
    for (const match of live.matchAll(/from\s+(["'])([^"']+)\1/g)) found.push(match[2]);
    for (const match of live.matchAll(/(?:^|\n)\s*import\s+(["'])([^"']+)\1/g)) found.push(match[2]);
    return found;
}

/**
 * Named symbols a file imports from one specifier, e.g.
 * `import { Card, CardTitle } from '@/components/ui/card'` -> ['Card','CardTitle'].
 */
export function importedSymbols(content, specifier) {
    const quoted = specifier.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(`import\\s+(?:type\\s+)?\\{([^}]*)\\}\\s*from\\s*(["'])${quoted}\\2`, 'g');
    const out = new Set();

    for (const match of content.matchAll(pattern)) {
        for (const raw of match[1].split(',')) {
            const name = raw.trim().split(/\s+as\s+/)[0].replace(/^type\s+/, '').trim();
            if (name) out.add(name);
        }
    }

    return [...out];
}

/** Names an index.ts re-exports, including `export { default as X } from`. */
export function exportedSymbols(source) {
    const out = new Set();

    for (const match of source.matchAll(/export\s*\{([^}]*)\}/g)) {
        for (const raw of match[1].split(',')) {
            const parts = raw.trim().split(/\s+as\s+/);
            const name = (parts[1] ?? parts[0]).replace(/^type\s+/, '').trim();
            if (name) out.add(name);
        }
    }

    for (const match of source.matchAll(/export\s+(?:declare\s+)?(?:const|let|var|function|class|interface|type|enum)\s+([A-Za-z0-9_$]+)/g)) {
        out.add(match[1]);
    }

    return out;
}

/** 'foo/bar' -> 'foo', '@scope/pkg/sub' -> '@scope/pkg'. */
export function packageName(specifier) {
    const parts = specifier.split('/');
    return specifier.startsWith('@') ? parts.slice(0, 2).join('/') : parts[0];
}
