<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeBlock from '@/components/admin/CodeBlock.vue';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';
import type { ExampleSourceFile } from '@/types';
import type { CodeLanguage } from '@/composables/useInfolistSchema';

const props = defineProps<{ files: ExampleSourceFile[] }>();

const { t } = useI18n();

const selected = ref(props.files[0]?.path ?? '');

watch(() => props.files, files => { selected.value = files[0]?.path ?? ''; });

const current = computed(() => props.files.find(file => file.path === selected.value) ?? null);
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[minmax(0,16rem)_1fr]">
        <ScrollArea class="max-h-96 rounded-lg border">
            <ul role="list" class="p-1" :aria-label="t('examples.source.file')">
                <li v-for="file in files" :key="file.path">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :aria-current="selected === file.path ? 'true' : undefined"
                        :class="cn('w-full justify-start font-mono text-xs', selected === file.path && 'bg-accent')"
                        @click="selected = file.path"
                    >
                        <span class="truncate">{{ file.path }}</span>
                    </Button>
                </li>
            </ul>
        </ScrollArea>

        <CodeBlock
            v-if="current"
            :value="current.content"
            :code-language="(current.language as CodeLanguage)"
            :code-filename="current.path"
            :label="t('examples.source.tab')"
        />

        <p v-else class="text-sm text-muted-foreground">{{ t('examples.source.empty') }}</p>
    </div>
</template>
