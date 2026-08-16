<script setup lang="ts">
import type { Component, DefineComponent } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ExampleFrame from '@/components/admin/examples/ExampleFrame.vue';
import type { ExampleEntry } from '@/types';

// BARE page: no AuthenticatedLayout, no PageHeader, no Toaster. The example
// shells mount their own chrome, so nesting them in the admin shell would put
// two layouts (and two sidebar providers) on one document.
//
// This file is the ONLY one permitted to import '@/examples' — the glob is
// dynamic, so every example stays its own lazily-loaded chunk and nothing
// under resources/js/examples ever reaches the main bundle.
const shells = import.meta.glob<DefineComponent>('@/examples/**/*.vue');

defineProps<{ example: ExampleEntry; dark?: boolean; reduced?: boolean }>();

const { t } = useI18n();

function resolve(shell: string): (() => Promise<{ default: Component }>) | null {
    const key = Object.keys(shells).find(path => path.endsWith(`/examples/${shell}`));

    return key ? (shells[key] as unknown as () => Promise<{ default: Component }>) : null;
}
</script>

<template>
    <Head :title="t(example.titleKey)" />

    <ExampleFrame
        :loader="resolve(example.shell)"
        :dark="dark"
        :reduced="reduced"
        fallback-key="examples.unavailable.title"
    />
</template>
