<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Questionnaire,
    QuestionnaireActions,
    QuestionnaireChoice,
    QuestionnaireChoices,
    QuestionnaireDescription,
    QuestionnaireError,
    QuestionnaireInput,
    QuestionnaireItem,
    QuestionnaireNext,
    QuestionnairePrevious,
    QuestionnaireProgress,
    QuestionnaireSubmit,
    QuestionnaireTitle,
} from '@/components/ui/questionnaire';

interface Choice { value: string; labelKey: string }
interface Question { id: string; type: string; choices: Choice[] }

const props = withDefaults(defineProps<{ questions?: Question[] }>(), { questions: () => [] });

const { t } = useI18n();

const submitted = ref(false);

/** The item order the primitive uses to assign shortcuts and drive next/previous. */
const items = computed(() => props.questions.map(question => ({
    name: question.id,
    required: true,
    choices: question.choices.map(choice => ({ value: choice.value })),
})));

function onSubmit(event: Event) {
    event.preventDefault();
    submitted.value = true;
}
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('gallery.demos.questionnaire.title') }]"
    >
        <Head :title="t('gallery.demos.questionnaire.title')" />

        <PageHeader
            :title="t('gallery.demos.questionnaire.title')"
            :description="t('gallery.demos.questionnaire.description')"
        />

        <div class="mt-6 max-w-2xl">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.questionnaire.title') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.questionnaire.description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Questionnaire :items="items" shortcuts="letters" class="space-y-6" @submit="onSubmit">
                        <QuestionnaireProgress />

                        <QuestionnaireItem
                            v-for="question in props.questions"
                            :key="question.id"
                            :name="question.id"
                            required
                        >
                            <QuestionnaireTitle>
                                {{ t(`gallery.componentDemos.questionnaire.items.${question.id}.title`) }}
                            </QuestionnaireTitle>
                            <QuestionnaireDescription>
                                {{ t(`gallery.componentDemos.questionnaire.items.${question.id}.description`) }}
                            </QuestionnaireDescription>

                            <QuestionnaireChoices v-if="question.type === 'choice'">
                                <QuestionnaireChoice
                                    v-for="choice in question.choices"
                                    :key="choice.value"
                                    :value="choice.value"
                                >
                                    {{ t(choice.labelKey) }}
                                </QuestionnaireChoice>
                            </QuestionnaireChoices>

                            <!-- The legend names the fieldset; the control needs its own name. -->
                            <QuestionnaireInput
                                v-else
                                type="text"
                                :aria-label="t(`gallery.componentDemos.questionnaire.items.${question.id}.title`)"
                                :placeholder="t('gallery.componentDemos.questionnaire.freeTextPlaceholder')"
                            />

                            <QuestionnaireError>
                                {{ t('gallery.componentDemos.questionnaire.required') }}
                            </QuestionnaireError>
                        </QuestionnaireItem>

                        <QuestionnaireActions>
                            <QuestionnairePrevious>
                                {{ t('gallery.componentDemos.questionnaire.previous') }}
                            </QuestionnairePrevious>
                            <QuestionnaireNext>
                                {{ t('gallery.componentDemos.questionnaire.next') }}
                            </QuestionnaireNext>
                            <QuestionnaireSubmit>
                                {{ t('gallery.componentDemos.questionnaire.submit') }}
                            </QuestionnaireSubmit>
                        </QuestionnaireActions>
                    </Questionnaire>

                    <p v-if="submitted" role="status" aria-live="polite" class="mt-4 text-sm text-primary">
                        {{ t('gallery.componentDemos.questionnaire.submitted') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
