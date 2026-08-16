<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Clock } from 'lucide-vue-next';
import BrandedErrorLayout from '@/Layouts/BrandedErrorLayout.vue';

const { t } = useI18n();
</script>

<template>
    <BrandedErrorLayout code="419" :title="t('brand.errors.419.title')" :description="t('brand.errors.419.body')">
        <template #icon>
            <Clock class="size-12 text-muted-foreground" />
        </template>
    </BrandedErrorLayout>
</template>
