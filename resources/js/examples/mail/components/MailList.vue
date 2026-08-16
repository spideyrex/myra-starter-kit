<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { Mail } from '../data/types';

defineProps<{ mails: Mail[] }>();

const model = defineModel<string>({ required: true });

const { t, locale } = useI18n();

function variant(label: string): 'default' | 'outline' {
    return ['work', 'important'].includes(label) ? 'default' : 'outline';
}

function shortDate(value: string): string {
    return new Date(value).toLocaleDateString(locale.value, { month: 'short', day: 'numeric' });
}
</script>

<template>
    <ScrollArea class="h-full">
        <ul role="list" class="flex flex-col gap-2 p-4 pt-0">
            <li v-for="mail in mails" :key="mail.id">
                <button
                    type="button"
                    :aria-current="model === mail.id ? 'true' : undefined"
                    :class="cn(
                        'flex w-full flex-col items-start gap-2 rounded-lg border p-3 text-left text-sm transition-all',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        model === mail.id ? 'bg-muted border-primary/40' : 'hover:bg-accent/40',
                    )"
                    @click="model = mail.id"
                >
                    <span class="flex w-full items-center gap-2">
                        <span class="font-semibold">{{ mail.name }}</span>
                        <span
                            v-if="!mail.read"
                            class="size-2 rounded-full bg-primary"
                            :aria-label="t('examples.mail.unread')"
                            role="img"
                        />
                        <span class="ml-auto text-xs text-muted-foreground">
                            {{ shortDate(mail.date) }}
                        </span>
                    </span>
                    <span class="text-xs font-medium">{{ mail.subject }}</span>
                    <span class="line-clamp-2 text-xs text-muted-foreground">{{ mail.text }}</span>
                    <span v-if="mail.labels.length" class="flex flex-wrap gap-1">
                        <Badge v-for="label in mail.labels" :key="label" :variant="variant(label)">
                            {{ label }}
                        </Badge>
                    </span>
                </button>
            </li>
        </ul>
    </ScrollArea>
</template>
