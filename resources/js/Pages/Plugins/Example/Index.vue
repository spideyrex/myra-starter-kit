<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{ pingUrl: string }>();

const { t } = useI18n();
const result = ref<string | null>(null);
const loading = ref(false);

// fetch, not an Inertia visit — the endpoint returns plain JSON on purpose.
async function ping() {
    loading.value = true;
    try {
        const res = await fetch(props.pingUrl, { headers: { Accept: 'application/json' } });
        result.value = JSON.stringify(await res.json());
    } catch {
        result.value = t('plugins.example.failed');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head :title="t('plugins.example.title')" />

    <AuthenticatedLayout>
        <PageHeader :title="t('plugins.example.title')" :description="t('plugins.example.description')" />

        <div class="px-4 pb-8">
            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        {{ t('plugins.example.cardTitle') }}
                        <Badge variant="secondary">{{ t('plugins.example.badge') }}</Badge>
                    </CardTitle>
                    <CardDescription>{{ t('plugins.example.cardDescription') }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm text-muted-foreground">{{ t('plugins.example.apiNote') }}</p>

                    <div class="flex items-center gap-3">
                        <Button :disabled="loading" @click="ping">
                            {{ loading ? t('plugins.example.pinging') : t('plugins.example.ping') }}
                        </Button>
                        <code v-if="result" class="rounded bg-muted px-2 py-1 text-xs">{{ result }}</code>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
