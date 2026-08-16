<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Podcast } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const { t } = useI18n();
</script>

<template>
    <div class="flex h-[450px] shrink-0 items-center justify-center rounded-md border border-dashed">
        <div class="mx-auto flex max-w-[420px] flex-col items-center justify-center gap-2 text-center">
            <div class="flex size-20 items-center justify-center rounded-full bg-muted">
                <Podcast class="size-10 text-muted-foreground" aria-hidden="true" />
            </div>
            <h3 class="mt-4 text-lg font-semibold">{{ t('examples.music.empty.title') }}</h3>
            <p class="mb-4 text-sm text-muted-foreground">{{ t('examples.music.empty.body') }}</p>
            <Button>{{ t('examples.music.empty.action') }}</Button>
        </div>
    </div>
</template>
