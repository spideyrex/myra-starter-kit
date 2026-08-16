<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { Save } from 'lucide-vue-next';

const props = defineProps<{
    singularKey: string;
    record: { name: string | null; tagline: string | null };
    exists: boolean;
    canEdit: boolean;
}>();

const { t } = useI18n();

const form = useForm({
    name: props.record.name ?? '',
    tagline: props.record.tagline ?? '',
});

function submit() {
    form.put(route('admin.learning.site-identity.update'), { preserveScroll: true });
}
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[
            { label: t('clusters.learning.label') },
            { label: t('clusters.learning.siteIdentity.label') },
        ]"
    >
        <Head :title="t('clusters.learning.siteIdentity.label')" />

        <PageHeader
            :title="t('clusters.learning.siteIdentity.label')"
            :description="t('clusters.learning.siteIdentity.description')"
        />

        <div class="mt-6 max-w-2xl">
            <Card>
                <CardContent class="space-y-4 pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('clusters.learning.siteIdentity.note') }}
                    </p>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-1.5">
                            <Label for="identity-name">{{ t('clusters.learning.siteIdentity.name') }}</Label>
                            <Input
                                id="identity-name"
                                v-model="form.name"
                                :disabled="!canEdit"
                                :aria-invalid="!!form.errors.name"
                            />
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="identity-tagline">{{ t('clusters.learning.siteIdentity.tagline') }}</Label>
                            <Input
                                id="identity-tagline"
                                v-model="form.tagline"
                                :disabled="!canEdit"
                                :aria-invalid="!!form.errors.tagline"
                            />
                            <p v-if="form.errors.tagline" class="text-sm text-destructive">{{ form.errors.tagline }}</p>
                        </div>

                        <Button v-if="canEdit" type="submit" :disabled="form.processing">
                            <Save class="mr-2 size-4" />
                            {{ t('clusters.learning.siteIdentity.save') }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
