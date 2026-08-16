<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Wrench } from 'lucide-vue-next';
import BrandedErrorLayout from '@/Layouts/BrandedErrorLayout.vue';

defineProps<{ maintenanceMessage?: string | null }>();

const { t } = useI18n();
</script>

<template>
    <BrandedErrorLayout code="503" :title="t('brand.errors.503.title')" :description="t('brand.errors.503.body')">
        <template #icon>
            <Wrench class="size-12 text-muted-foreground" />
        </template>

        <p v-if="maintenanceMessage" class="mt-4 rounded-md border bg-muted/40 p-3 text-sm">{{ maintenanceMessage }}</p>
    </BrandedErrorLayout>
</template>
