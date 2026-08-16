<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Sparkles } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import AiFilterChips from '@/components/admin/ai/AiFilterChips.vue';
import { useAskBar, type AskBarSchema } from '@/composables/useAskBar';
import type { QueryGroup } from '@/types/admin';

const props = withDefaults(defineProps<{
    scope: string;
    schema?: AskBarSchema | null;
    disabled?: boolean;
}>(), { schema: null, disabled: false });

const emit = defineEmits<{ apply: [tree: QueryGroup] }>();

const { t, te } = useI18n();

const bar = useAskBar({ scope: () => props.scope, schema: () => props.schema });

const message = computed(() => {
    const key = bar.error.value;
    if (!key) return null;

    return te(key) ? t(key) : key;
});

function onApply() {
    emit('apply', bar.apply());
    bar.discard();
}
</script>

<template>
    <div class="rounded-lg border bg-card p-3 text-card-foreground">
        <form class="flex flex-wrap items-center gap-2" @submit.prevent="bar.compile()">
            <label class="sr-only" for="askbar-prompt">{{ t('assistant.askPlaceholder') }}</label>
            <Sparkles class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            <Input
                id="askbar-prompt"
                v-model="bar.prompt.value"
                class="min-w-40 flex-1"
                :placeholder="t('assistant.askPlaceholder')"
                :disabled="disabled || bar.state.value === 'compiling'"
                maxlength="500"
            />
            <Button type="submit" :disabled="disabled || bar.state.value === 'compiling'">
                {{ t('assistant.compile') }}
            </Button>
        </form>

        <div v-if="bar.state.value === 'compiling'" class="mt-3 flex gap-2" aria-busy="true">
            <Skeleton class="h-6 w-32" />
            <Skeleton class="h-6 w-24" />
            <Skeleton class="h-6 w-40" />
        </div>

        <div v-else-if="bar.state.value === 'review'" class="mt-3 space-y-3">
            <p class="text-xs text-muted-foreground">{{ t('assistant.review') }}</p>

            <AiFilterChips
                :tree="bar.draft.value"
                :schema="schema"
                @drop="(path) => bar.dropRule(path)"
            />

            <div class="flex gap-2">
                <Button type="button" size="sm" @click="onApply">{{ t('assistant.apply') }}</Button>
                <Button type="button" size="sm" variant="ghost" @click="bar.discard()">
                    {{ t('assistant.discard') }}
                </Button>
            </div>
        </div>

        <p v-if="message" class="mt-3 text-sm text-destructive" role="status">{{ message }}</p>
    </div>
</template>
