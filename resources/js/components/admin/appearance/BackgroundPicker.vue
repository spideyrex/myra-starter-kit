<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import ColorSwatch from '@/components/admin/ColorSwatch.vue';
import RecipeSwatch from './RecipeSwatch.vue';
import type { AppearanceOptions, BackgroundTypeKey } from './types';

const props = defineProps<{
    type: BackgroundTypeKey;
    color: string;
    recipe: string;
    options: AppearanceOptions;
    supportsMedia: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:type': [value: BackgroundTypeKey];
    'update:color': [value: string];
    'update:recipe': [value: string];
}>();

const { t } = useI18n();

/** A shell with no media panel can never show an uploaded image. */
const types = computed(() =>
    props.options.types.filter((key) => key !== 'image' || props.supportsMedia),
);

const recipes = computed(() => {
    if (props.type === 'gradient') return props.options.gradients;
    if (props.type === 'pattern') return props.options.patterns;

    return [];
});

/** Switching family must never leave a gradient key selected under 'pattern'. */
function pickType(next: BackgroundTypeKey) {
    emit('update:type', next);

    const allowed = next === 'gradient' ? props.options.gradients : next === 'pattern' ? props.options.patterns : [];

    if (!allowed.includes(props.recipe)) {
        emit('update:recipe', allowed[0] ?? '');
    }
}
</script>

<template>
    <div class="space-y-4">
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">{{ t('appearanceAdmin.fields.type') }}</legend>
            <p class="text-xs text-muted-foreground">{{ t('appearanceAdmin.help.type') }}</p>

            <RadioGroup
                :model-value="type"
                class="grid gap-2 sm:grid-cols-3"
                @update:model-value="pickType(String($event) as BackgroundTypeKey)"
            >
                <div
                    v-for="key in types"
                    :key="key"
                    class="flex items-start gap-2 rounded-md border p-3"
                    :class="type === key ? 'border-primary ring-2 ring-primary/40' : ''"
                >
                    <RadioGroupItem :id="`auth-bg-type-${key}`" :value="key" :aria-describedby="`auth-bg-type-${key}-help`" class="mt-1" />
                    <div class="min-w-0">
                        <Label :for="`auth-bg-type-${key}`" class="cursor-pointer text-sm font-medium">
                            {{ t('appearanceAdmin.backgrounds.' + key) }}
                        </Label>
                        <p :id="`auth-bg-type-${key}-help`" class="text-xs text-muted-foreground">
                            {{ t('appearanceAdmin.backgroundHelp.' + key) }}
                        </p>
                    </div>
                </div>
            </RadioGroup>
            <p v-if="errors?.auth_bg_type" class="text-sm text-destructive">{{ errors.auth_bg_type }}</p>
        </fieldset>

        <div v-if="type !== 'brand' && type !== 'none'" class="space-y-2">
            <Label for="auth-bg-color">{{ t('appearanceAdmin.fields.color') }}</Label>
            <div class="flex items-center gap-2">
                <Input
                    id="auth-bg-color"
                    :model-value="color"
                    type="text"
                    placeholder="#000000"
                    class="font-mono"
                    :aria-describedby="'auth-bg-color-help'"
                    @update:model-value="emit('update:color', String($event))"
                />
                <ColorSwatch :value="color || '#000000'" :size="24" :show-value="false" />
            </div>
            <p id="auth-bg-color-help" class="text-xs text-muted-foreground">{{ t('appearanceAdmin.help.color') }}</p>
            <p v-if="errors?.auth_bg_color" class="text-sm text-destructive">{{ errors.auth_bg_color }}</p>
        </div>

        <fieldset v-if="recipes.length" class="space-y-2">
            <legend class="text-sm font-medium">{{ t('appearanceAdmin.fields.recipe') }}</legend>

            <RadioGroup
                :model-value="recipe"
                class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6"
                @update:model-value="emit('update:recipe', String($event))"
            >
                <div
                    v-for="key in recipes"
                    :key="key"
                    class="rounded-md border p-2"
                    :class="recipe === key ? 'border-primary ring-2 ring-primary/40' : ''"
                >
                    <div class="h-10 overflow-hidden rounded-sm">
                        <RecipeSwatch
                            :recipe-key="key"
                            :css="options.recipeCss[key]"
                            :size="options.recipeSize[key]"
                            :pattern="type === 'pattern'"
                        />
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <RadioGroupItem :id="`auth-recipe-${key}`" :value="key" />
                        <Label :for="`auth-recipe-${key}`" class="cursor-pointer text-xs">
                            {{ t('appearanceAdmin.recipes.' + key) }}
                        </Label>
                    </div>
                </div>
            </RadioGroup>
            <p v-if="errors?.auth_bg_recipe" class="text-sm text-destructive">{{ errors.auth_bg_recipe }}</p>
        </fieldset>
    </div>
</template>
