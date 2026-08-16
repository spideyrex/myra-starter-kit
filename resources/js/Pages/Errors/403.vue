<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ShieldAlert } from 'lucide-vue-next';
import BrandedErrorLayout from '@/Layouts/BrandedErrorLayout.vue';

const { t } = useI18n();
</script>

<template>
    <BrandedErrorLayout code="403" :title="t('brand.errors.403.title')" :description="t('brand.errors.403.body')">
        <template #icon>
            <ShieldAlert class="size-12 text-muted-foreground" />
        </template>
    </BrandedErrorLayout>
</template>
