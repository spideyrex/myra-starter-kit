import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { scanBlocks, scanFile } from '../../scripts/shadcn/resolve-scan.mjs';

/**
 * sidebar-12 and sidebar-15 shipped `<Plus />` with no import. Vue resolved
 * nothing, warned and dropped the icon, and every gate stayed green: blocks are
 * excluded from tsconfig, and the template compiles to a runtime
 * resolveComponent() that vite is happy to emit. This is the gate that isn't.
 */
describe('vendored block component resolution', () => {
    it('renders no component that nothing binds', () => {
        const unresolved = scanBlocks(path.resolve(__dirname, '../../resources/js/blocks')) as Record<string, string[]>;

        expect(unresolved).toEqual({});
    });

    it('catches the exact shape of the defect it exists for', () => {
        const withImport = `
<script setup lang="ts">
import { Plus } from "lucide-vue-next"
</script>
<template><button><Plus /></button></template>`;

        expect(scanFile(withImport)).toEqual([]);
        expect(scanFile(withImport.replace(/^import .*$/m, ''))).toEqual(['Plus']);
    });

    it('does not mistake a recursive SFC for an unbound one', () => {
        const recursive = `
<script setup lang="ts">
const props = defineProps<{ depth: number }>()
</script>
<template><Tree v-if="props.depth" :depth="props.depth - 1" /></template>`;

        expect(scanFile(recursive, 'Tree')).toEqual([]);
        expect(scanFile(recursive, 'Other')).toEqual(['Tree']);
    });
});
