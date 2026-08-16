<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { RotateCcw } from 'lucide-vue-next';
import PlaygroundControls from './PlaygroundControls.vue';
import PlaygroundStage from './PlaygroundStage.vue';
import PlaygroundSnippet from './PlaygroundSnippet.vue';
import {
    usePlayground,
    type PlaygroundLocale,
    type PlaygroundScheme,
    type PlaygroundSpec,
    type PlaygroundViewport,
} from '@/composables/usePlayground';
import { setLocale, SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n';

const props = defineProps<{ spec: PlaygroundSpec }>();

const { t, locale: activeLocale } = useI18n();

const { values, bound, snippet, lang, viewport, scheme, locale, reset, copy, copied } = usePlayground(props.spec);

const VIEWPORTS: PlaygroundViewport[] = ['sm', 'md', 'lg'];
const SCHEMES: PlaygroundScheme[] = ['light', 'dark', 'split'];

locale.value = (activeLocale.value as PlaygroundLocale) ?? 'en';

// Flipping the switcher flips the real app locale, so the stage re-renders the
// primitive exactly as a ms or zh user sees it — not a mocked translation.
watch(locale, value => {
    if (value !== activeLocale.value) void setLocale(value as SupportedLocale);
});

watch(activeLocale, value => {
    locale.value = value as PlaygroundLocale;
});

const stageComponent = computed(() => props.spec.component as any);

const walkthrough = computed(() => [
    t('gallery.playground.keyboardSteps.controls'),
    t('gallery.playground.keyboardSteps.viewport'),
    t('gallery.playground.keyboardSteps.scheme'),
    t('gallery.playground.keyboardSteps.locale'),
    t('gallery.playground.keyboardSteps.snippet'),
    t('gallery.playground.keyboardSteps.copy'),
]);

function update(name: string, value: unknown): void {
    values.value = { ...values.value, [name]: value } as any;
}
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center justify-between gap-2 text-base">
                    {{ t('gallery.playground.props') }}
                    <Button type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="reset">
                        <RotateCcw class="size-3.5" aria-hidden="true" />
                        {{ t('gallery.playground.reset') }}
                    </Button>
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-5">
                <PlaygroundControls
                    :controls="spec.controls"
                    :values="values"
                    :id-prefix="`pg-${spec.key}`"
                    @update="update"
                />

                <div class="space-y-1.5">
                    <!-- role="group" is not labelable, so the heading is referenced, not `for`-linked. -->
                    <span :id="`pg-${spec.key}-viewport`" class="block text-sm font-medium">
                        {{ t('gallery.playground.viewport') }}
                    </span>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        :aria-labelledby="`pg-${spec.key}-viewport`"
                        :model-value="viewport"
                        class="justify-start"
                        @update:model-value="viewport = ($event as PlaygroundViewport) || viewport"
                    >
                        <ToggleGroupItem
                            v-for="value in VIEWPORTS"
                            :key="value"
                            :value="value"
                            :aria-label="t(`gallery.playground.viewports.${value}`)"
                            class="px-3 text-xs"
                        >
                            {{ t(`gallery.playground.viewports.${value}`) }}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <div class="space-y-1.5">
                    <span :id="`pg-${spec.key}-scheme`" class="block text-sm font-medium">
                        {{ t('gallery.playground.scheme') }}
                    </span>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        :aria-labelledby="`pg-${spec.key}-scheme`"
                        :model-value="scheme"
                        class="justify-start"
                        @update:model-value="scheme = ($event as PlaygroundScheme) || scheme"
                    >
                        <ToggleGroupItem
                            v-for="value in SCHEMES"
                            :key="value"
                            :value="value"
                            :aria-label="t(`gallery.playground.schemes.${value}`)"
                            class="px-3 text-xs"
                        >
                            {{ t(`gallery.playground.schemes.${value}`) }}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <div class="space-y-1.5">
                    <Label :for="`pg-${spec.key}-locale`">{{ t('gallery.playground.locale') }}</Label>
                    <Select
                        :model-value="locale"
                        @update:model-value="locale = String($event) as PlaygroundLocale"
                    >
                        <SelectTrigger :id="`pg-${spec.key}-locale`" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in SUPPORTED_LOCALES" :key="option.code" :value="option.code">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </CardContent>
        </Card>

        <div class="min-w-0 space-y-4">
            <PlaygroundStage
                :component="stageComponent"
                :bound="bound"
                :slots="spec.slots"
                :viewport="viewport"
                :scheme="scheme"
                :label-key="spec.titleKey"
            />

            <PlaygroundSnippet
                :code="snippet"
                :lang="lang"
                :copied="copied"
                @update:lang="lang = $event"
                @copy="copy"
            />

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">{{ t('gallery.playground.keyboard') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                        <li v-for="(step, i) in walkthrough" :key="i">{{ step }}</li>
                    </ol>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
