<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, LayoutDashboard } from 'lucide-vue-next';

// >>> MYRA v2.7 [B] START
// The ONLY signal that the dashboard on screen is authored FOR SOMEONE ELSE.
// role="status" so arriving on the page announces it; text plus icon, never
// colour alone.
const props = defineProps<{
    role: string;
    backHref?: string | null;
}>();

const { t } = useI18n();

const description = computed(() => t('roleDashboardAdmin.banner.description', { role: props.role }));
</script>

<template>
    <Card
        role="status"
        class="gap-3 border-primary/30 bg-primary/5 px-4 py-4 dark:bg-primary/10"
        :aria-label="t('roleDashboardAdmin.banner.title', { role })"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                    <LayoutDashboard class="size-5" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold">
                        {{ t('roleDashboardAdmin.banner.title', { role }) }}
                    </p>
                    <p class="text-sm text-muted-foreground">{{ description }}</p>
                </div>
            </div>

            <Button v-if="backHref" as-child variant="outline" size="sm" class="shrink-0">
                <Link :href="backHref">
                    <ArrowLeft class="mr-2 size-4" aria-hidden="true" />
                    {{ t('roleDashboardAdmin.banner.back') }}
                </Link>
            </Button>
        </div>
    </Card>
</template>
<!-- <<< MYRA v2.7 [B] END -->
