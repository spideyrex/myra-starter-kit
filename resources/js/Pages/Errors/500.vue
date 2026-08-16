<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ServerCrash } from 'lucide-vue-next';
import BrandedErrorLayout from '@/Layouts/BrandedErrorLayout.vue';

const { t } = useI18n();
</script>

<template>
    <BrandedErrorLayout code="500" :title="t('brand.errors.500.title')" :description="t('brand.errors.500.body')">
        <template #icon>
            <ServerCrash class="size-12 text-muted-foreground" />
        </template>
    </BrandedErrorLayout>
</template>
