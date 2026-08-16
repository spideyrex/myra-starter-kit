<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';

defineProps<{ panes: string[] }>();

const model = defineModel<string>({ required: true });

const { t } = useI18n();
</script>

<template>
    <nav :aria-label="t('examples.forms.navLabel')">
        <ul role="list" class="flex gap-2 overflow-x-auto lg:flex-col lg:gap-1">
            <li v-for="pane in panes" :key="pane">
                <button
                    type="button"
                    :aria-current="model === pane ? 'true' : undefined"
                    :class="cn(
                        'w-full whitespace-nowrap rounded-md px-4 py-2 text-left text-sm transition-colors',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        model === pane ? 'bg-muted font-medium' : 'hover:bg-accent/50',
                    )"
                    @click="model = pane"
                >
                    {{ t(`examples.forms.panes.${pane}.title`) }}
                </button>
            </li>
        </ul>
    </nav>
</template>
