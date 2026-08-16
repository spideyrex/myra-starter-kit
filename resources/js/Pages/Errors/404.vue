<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { FileQuestion } from 'lucide-vue-next';
import BrandedErrorLayout from '@/Layouts/BrandedErrorLayout.vue';

const { t } = useI18n();
</script>

<template>
    <BrandedErrorLayout code="404" :title="t('brand.errors.404.title')" :description="t('brand.errors.404.body')">
        <template #icon>
            <FileQuestion class="size-12 text-muted-foreground" />
        </template>
    </BrandedErrorLayout>
</template>
