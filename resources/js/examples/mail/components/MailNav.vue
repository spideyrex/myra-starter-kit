<script setup lang="ts">
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';

defineProps<{ items: Array<{ id: string; icon: Component; count?: number }> }>();

const model = defineModel<string>({ required: true });

const { t } = useI18n();
</script>

<template>
    <ul role="list" class="grid gap-1">
        <li v-for="item in items" :key="item.id">
            <button
                type="button"
                :aria-current="model === item.id ? 'true' : undefined"
                :class="cn(
                    'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    model === item.id ? 'bg-accent text-accent-foreground font-medium' : 'hover:bg-accent/50',
                )"
                @click="model = item.id"
            >
                <component :is="item.icon" class="size-4 shrink-0" aria-hidden="true" />
                <span class="truncate">{{ t(`examples.mail.folders.${item.id}`) }}</span>
                <Badge v-if="item.count" variant="secondary" class="ml-auto tabular-nums">{{ item.count }}</Badge>
            </button>
        </li>
    </ul>
</template>
