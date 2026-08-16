<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { useFlashToasts } from '@/composables/useFlashToasts';
// >>> MYRA v2.8 [A] START
import Split from './Guest/SplitLayout.vue';
import { useAppearance } from '@/composables/useAppearance';

// EAGER on purpose. No defineAsyncComponent anywhere on the login route: a
// login shell behind a network fetch is a lockout waiting for a bad deploy.
// It is also what lets a new shell be dropped into Guest/ with zero edit here.
const MODULES = import.meta.glob<{ default: Component }>('./Guest/*Layout.vue', { eager: true });

const BY_NAME: Record<string, Component> = {};
for (const [path, mod] of Object.entries(MODULES)) {
    BY_NAME[path.split('/').pop()!.replace('.vue', '')] = mod.default;
}

const { appearance } = useAppearance();

/** The second, independent guard: a registered layout with no .vue lands here. */
const shell = computed<Component>(() => BY_NAME[appearance.value.auth.component] ?? Split);
// <<< MYRA v2.8 [A] END

useFlashToasts();
</script>

<template>
    <component :is="shell" :auth="appearance.auth">
        <slot />
    </component>

    <Toaster />
</template>
