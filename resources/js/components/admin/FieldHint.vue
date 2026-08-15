<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { HintAction, SemanticColor } from '@/composables/useFormSchema';

const props = withDefaults(defineProps<{
    fieldId: string;
    hint?: string;
    hintIcon?: Component;
    hintIconTooltip?: string;
    hintColor?: SemanticColor;
    hintAction?: HintAction;
    error?: string;
    form?: Record<string, any>;
}>(), {
    hintColor: 'muted',
});

const COLOR_CLASS: Record<SemanticColor, string> = {
    muted: 'text-muted-foreground',
    primary: 'text-primary',
    info: 'text-info',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-destructive',
};

const colorClass = computed(() => COLOR_CLASS[props.hintColor] ?? COLOR_CLASS.muted);

function runHintAction() {
    props.hintAction?.onClick(props.form ?? {});
}
</script>

<template>
    <div v-if="hint || hintIcon || hintAction || error" class="space-y-1">
        <div v-if="hint || hintIcon || hintAction" class="flex items-start gap-1.5 text-sm" :class="colorClass">
            <TooltipProvider v-if="hintIcon" :delay-duration="200">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <span class="mt-0.5 inline-flex shrink-0" :tabindex="hintIconTooltip ? 0 : -1">
                            <component :is="hintIcon" class="size-4" aria-hidden="true" />
                            <span v-if="hintIconTooltip" class="sr-only">{{ hintIconTooltip }}</span>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent v-if="hintIconTooltip">{{ hintIconTooltip }}</TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <p v-if="hint" :id="`${fieldId}-hint`" class="flex-1">{{ hint }}</p>
            <span v-else class="flex-1" />

            <Button
                v-if="hintAction"
                type="button"
                variant="link"
                size="sm"
                class="h-auto shrink-0 p-0 text-sm"
                @click="runHintAction"
            >
                <component :is="hintAction.icon" v-if="hintAction.icon" class="mr-1 size-3.5" aria-hidden="true" />
                {{ hintAction.label }}
            </Button>
        </div>

        <p v-if="error" :id="`${fieldId}-error`" role="alert" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>
