<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Archive, Forward, Reply, Trash2 } from 'lucide-vue-next';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { Mail } from '../data/types';

const props = defineProps<{ mail: Mail | null }>();

const { t, locale } = useI18n();

const reply = ref('');

watch(() => props.mail?.id, () => { reply.value = ''; });

const actions = [
    { id: 'archive', icon: Archive },
    { id: 'trash', icon: Trash2 },
    { id: 'forward', icon: Forward },
] as const;

function initials(name: string): string {
    return name.split(' ').map(part => part[0]).join('').slice(0, 2);
}

function fullDate(value: string): string {
    return new Date(value).toLocaleString(locale.value);
}
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="flex items-center gap-2 p-2">
            <TooltipProvider>
                <Tooltip v-for="action in actions" :key="action.id">
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="!mail"
                            :aria-label="t(`examples.mail.actions.${action.id}`)"
                        >
                            <component :is="action.icon" class="size-4" aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{ t(`examples.mail.actions.${action.id}`) }}</TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>

        <Separator />

        <div v-if="mail" class="flex flex-1 flex-col overflow-auto">
            <div class="flex items-start gap-4 p-4">
                <Avatar>
                    <AvatarFallback>{{ initials(mail.name) }}</AvatarFallback>
                </Avatar>
                <div class="grid gap-1 text-sm">
                    <p class="font-semibold">{{ mail.name }}</p>
                    <p class="text-xs">{{ mail.subject }}</p>
                    <p class="text-xs text-muted-foreground">{{ mail.email }}</p>
                </div>
                <p class="ml-auto text-xs text-muted-foreground">{{ fullDate(mail.date) }}</p>
            </div>

            <Separator />

            <p class="flex-1 whitespace-pre-wrap p-4 text-sm">{{ mail.text }}</p>

            <Separator />

            <form class="grid gap-2 p-4" @submit.prevent>
                <Label for="mail-reply" class="sr-only">{{ t('examples.mail.replyLabel') }}</Label>
                <Textarea
                    id="mail-reply"
                    v-model="reply"
                    class="min-h-24"
                    :placeholder="t('examples.mail.replyPlaceholder', { name: mail.name })"
                />
                <Button type="submit" size="sm" class="ml-auto" :disabled="reply.trim() === ''">
                    <Reply class="size-4" aria-hidden="true" />
                    {{ t('examples.mail.send') }}
                </Button>
            </form>
        </div>

        <p v-else class="p-8 text-center text-sm text-muted-foreground">
            {{ t('examples.mail.noMessage') }}
        </p>
    </div>
</template>
