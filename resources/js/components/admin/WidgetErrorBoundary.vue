<script setup lang="ts">
import { onErrorCaptured, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { AlertTriangle, RotateCw } from 'lucide-vue-next';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

defineProps<{ widgetKey: string }>();

const emit = defineEmits<{ (e: 'retry'): void }>();

const { t } = useI18n();
const failed = ref(false);

// A thrown widget renders a card-level error and NEVER propagates.
onErrorCaptured((error) => {
    failed.value = true;
    console.error('[myra] widget render failed', error);

    return false;
});

// v-else remounts the slot content, so clearing the flag is the retry.
function retry(): void {
    failed.value = false;
    emit('retry');
}
</script>

<template>
    <Card v-if="failed" class="h-full border-destructive/40">
        <CardContent class="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
            <AlertTriangle class="size-5 text-destructive" aria-hidden="true" />
            <p class="text-sm text-muted-foreground">{{ t('dashboardLayout.errors.widget') }}</p>
            <Button variant="outline" size="sm" @click="retry">
                <RotateCw class="mr-1.5 size-3.5" aria-hidden="true" />
                {{ t('dashboardLayout.retry') }}
            </Button>
        </CardContent>
    </Card>
    <!-- No wrapper element: the boundary must not change the rendered tree. -->
    <slot v-else />
</template>
