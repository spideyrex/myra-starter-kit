<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import CodeBlock from '@/components/admin/CodeBlock.vue';
import type { BlockSourceFile } from '@/types';
import type { CodeLanguage } from '@/composables/useFormSchema';

const props = defineProps<{ files: BlockSourceFile[] }>();

const { t } = useI18n();

const active = ref(props.files[0]?.path ?? '');

watch(() => props.files, files => { active.value = files[0]?.path ?? ''; });

const names = computed(() =>
    Object.fromEntries(props.files.map(file => [file.path, file.path.split('/blocks/')[1] ?? file.path])),
);

function language(file: BlockSourceFile): CodeLanguage {
    return (file.language as CodeLanguage) ?? 'plaintext';
}
</script>

<template>
    <div v-if="files.length">
        <Tabs :model-value="active" @update:model-value="active = String($event)">
            <TabsList class="flex-wrap">
                <TabsTrigger v-for="file in files" :key="file.path" :value="file.path">
                    {{ names[file.path] }}
                </TabsTrigger>
            </TabsList>

            <TabsContent v-for="file in files" :key="file.path" :value="file.path">
                <CodeBlock
                    :value="file.content"
                    :code-language="language(file)"
                    :code-filename="file.path"
                    :label="t('blocks.source.file', { path: file.path })"
                />
            </TabsContent>
        </Tabs>
    </div>
</template>
