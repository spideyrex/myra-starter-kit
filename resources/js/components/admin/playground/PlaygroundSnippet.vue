<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Check, Copy } from 'lucide-vue-next';
import type { SnippetLang } from '@/composables/usePlayground';

/**
 * The snippet is rendered as TEXT. Never v-html: a generated string that ever
 * carried a user value would otherwise become an injection surface, and the
 * syntax colour is not worth that trade on a page whose whole point is that
 * the output is copy-paste safe.
 */
defineProps<{
    code: string;
    lang: SnippetLang;
    copied: boolean;
}>();

const emit = defineEmits<{ 'update:lang': [value: SnippetLang]; copy: [] }>();

const { t } = useI18n();

const LANGS: SnippetLang[] = ['vue', 'ts', 'php'];
</script>

<template>
    <div class="overflow-hidden rounded-lg border bg-muted/40">
        <div class="flex flex-wrap items-center gap-2 border-b bg-muted/60 px-3 py-1.5">
            <Tabs :model-value="lang" @update:model-value="emit('update:lang', $event as SnippetLang)">
                <TabsList class="h-7">
                    <TabsTrigger v-for="value in LANGS" :key="value" :value="value" class="h-6 px-2 text-xs">
                        {{ value }}
                    </TabsTrigger>
                </TabsList>
            </Tabs>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="ml-auto h-7 px-2 text-xs"
                @click="emit('copy')"
            >
                <component :is="copied ? Check : Copy" class="size-3.5" aria-hidden="true" />
                {{ copied ? t('gallery.playground.copied') : t('gallery.playground.copy') }}
            </Button>
        </div>

        <pre
            class="m-0 max-h-80 overflow-auto p-3 text-xs leading-relaxed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            tabindex="0"
            role="region"
            :aria-label="t('gallery.playground.code')"
        ><code class="block font-mono">{{ code }}</code></pre>
    </div>
</template>
