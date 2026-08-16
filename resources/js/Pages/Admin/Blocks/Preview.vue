<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BlockFrame from '@/components/admin/blocks/BlockFrame.vue';
import type { BlockSchema } from '@/types';

/**
 * BARE page: no AuthenticatedLayout, no PageHeader, no Toaster. It renders only
 * inside the same-origin iframe on Show.vue, because a sidebar block mounts its
 * own SidebarProvider and would otherwise nest inside the live admin shell.
 */
const props = defineProps<{ block: BlockSchema; dark?: boolean; reduced?: boolean }>();

const { t, te } = useI18n();

const title = computed(() => (te(props.block.titleKey) ? t(props.block.titleKey) : props.block.key));
</script>

<template>
    <Head :title="title" />

    <BlockFrame
        :entry-file="block.entryFile"
        :dark="dark === true"
        :reduced="reduced === true"
        fallback-key="blocks.unavailable.body"
        :fallback-params="{ deps: block.unavailableReason ?? '' }"
    />
</template>
