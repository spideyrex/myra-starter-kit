<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Bubble, BubbleContent } from '@/components/ui/bubble';
import { Message, MessageAvatar, MessageContent, MessageFooter, MessageHeader } from '@/components/ui/message';
import {
    MessageScroller,
    MessageScrollerButton,
    MessageScrollerContent,
    MessageScrollerItem,
    MessageScrollerProvider,
    MessageScrollerViewport,
} from '@/components/ui/message-scroller';
import {
    Attachment,
    AttachmentContent,
    AttachmentDescription,
    AttachmentGroup,
    AttachmentMedia,
    AttachmentTitle,
} from '@/components/ui/attachment';
import { FileSpreadsheet, FileText } from 'lucide-vue-next';

interface ThreadMessage {
    id: number;
    author: string;
    initials: string;
    side: 'incoming' | 'outgoing';
    body: string;
    at: string;
}

interface AttachmentRow { id: string; name: string; size: string; kind: string }

const props = withDefaults(
    defineProps<{ thread?: ThreadMessage[]; attachments?: AttachmentRow[] }>(),
    { thread: () => [], attachments: () => [] },
);

const { t } = useI18n();

const messages = ref<ThreadMessage[]>([...props.thread]);
const draft = ref('');

function send() {
    const body = draft.value.trim();
    if (body === '') return;

    messages.value = [
        ...messages.value,
        {
            id: (messages.value.at(-1)?.id ?? 0) + 1,
            author: t('gallery.componentDemos.conversation.you'),
            initials: 'ME',
            side: 'outgoing',
            body,
            at: new Date().toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }),
        },
    ];
    draft.value = '';
}

const icons: Record<string, unknown> = { text: FileText, sheet: FileSpreadsheet };
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('gallery.demos.conversation.title') }]"
    >
        <Head :title="t('gallery.demos.conversation.title')" />

        <PageHeader
            :title="t('gallery.demos.conversation.title')"
            :description="t('gallery.demos.conversation.description')"
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.conversation.threadTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.conversation.threadDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <MessageScrollerProvider auto-scroll>
                        <MessageScroller class="h-[380px] rounded-md border">
                            <MessageScrollerViewport class="p-4">
                                <!-- MessageScrollerContent already carries role="log". -->
                                <MessageScrollerContent
                                    aria-live="polite"
                                    :aria-label="t('gallery.componentDemos.conversation.threadTitle')"
                                >
                                    <ul role="list" class="flex flex-col gap-4">
                                        <li v-for="message in messages" :key="message.id">
                                            <MessageScrollerItem :message-id="String(message.id)">
                                                <Message :align="message.side === 'outgoing' ? 'end' : 'start'">
                                                    <MessageAvatar>
                                                        <span class="px-2 text-xs font-semibold" aria-hidden="true">
                                                            {{ message.initials }}
                                                        </span>
                                                    </MessageAvatar>
                                                    <MessageContent>
                                                        <MessageHeader>{{ message.author }}</MessageHeader>
                                                        <Bubble :variant="message.side === 'outgoing' ? 'default' : 'muted'">
                                                            <BubbleContent>{{ message.body }}</BubbleContent>
                                                        </Bubble>
                                                        <MessageFooter>{{ message.at }}</MessageFooter>
                                                    </MessageContent>
                                                </Message>
                                            </MessageScrollerItem>
                                        </li>
                                    </ul>
                                </MessageScrollerContent>
                            </MessageScrollerViewport>

                            <MessageScrollerButton
                                class="absolute bottom-3 left-1/2 -translate-x-1/2"
                                :aria-label="t('gallery.componentDemos.conversation.scrollToLatest')"
                            />
                        </MessageScroller>
                    </MessageScrollerProvider>

                    <form class="mt-4 flex items-end gap-2" @submit.prevent="send">
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <Label for="conversation-draft">
                                {{ t('gallery.componentDemos.conversation.draftLabel') }}
                            </Label>
                            <Input
                                id="conversation-draft"
                                v-model="draft"
                                autocomplete="off"
                                :placeholder="t('gallery.componentDemos.conversation.draftPlaceholder')"
                            />
                        </div>
                        <Button type="submit" :disabled="draft.trim() === ''">
                            {{ t('gallery.componentDemos.conversation.send') }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.conversation.attachmentsTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.conversation.attachmentsDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <AttachmentGroup>
                        <Attachment v-for="file in props.attachments" :key="file.id" orientation="horizontal">
                            <AttachmentMedia>
                                <component :is="icons[file.kind] ?? FileText" aria-hidden="true" />
                            </AttachmentMedia>
                            <AttachmentContent>
                                <AttachmentTitle>{{ file.name }}</AttachmentTitle>
                                <AttachmentDescription>{{ file.size }}</AttachmentDescription>
                            </AttachmentContent>
                        </Attachment>
                    </AttachmentGroup>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
