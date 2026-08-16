<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { AlertCircle, Archive, ArchiveX, File, Inbox, MessagesSquare, Send, ShoppingCart, Trash2, Users2 } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import AccountSwitcher from './components/AccountSwitcher.vue';
import MailDisplay from './components/MailDisplay.vue';
import MailList from './components/MailList.vue';
import MailNav from './components/MailNav.vue';
import accountsData from './data/accounts.json';
import mailsData from './data/mails.json';
import type { Account, Mail } from './data/types';

const { t } = useI18n();

const accounts = accountsData as Account[];
const mails = mailsData as Mail[];

const account = ref(accounts[0].email);
const folder = ref('inbox');
const selected = ref(mails[0].id);
const query = ref('');
const scope = ref('all');

const primary = [
    { id: 'inbox', icon: Inbox, count: mails.filter(m => !m.read).length },
    { id: 'drafts', icon: File, count: 9 },
    { id: 'sent', icon: Send },
    { id: 'junk', icon: ArchiveX, count: 23 },
    { id: 'trash', icon: Trash2 },
    { id: 'archive', icon: Archive },
];

const secondary = [
    { id: 'social', icon: Users2, count: 972 },
    { id: 'updates', icon: AlertCircle, count: 342 },
    { id: 'forums', icon: MessagesSquare, count: 128 },
    { id: 'shopping', icon: ShoppingCart, count: 8 },
];

const visible = computed(() => {
    const term = query.value.trim().toLowerCase();

    return mails.filter(mail => {
        if (scope.value === 'unread' && mail.read) return false;
        if (term === '') return true;

        return [mail.name, mail.subject, mail.text].some(field => field.toLowerCase().includes(term));
    });
});

const current = computed(() => mails.find(mail => mail.id === selected.value) ?? null);
</script>

<template>
    <ResizablePanelGroup id="mail" direction="horizontal" class="h-svh items-stretch">
        <ResizablePanel id="mail-sidebar" :default-size="20" :min-size="15" :max-size="30">
            <section class="flex h-full flex-col" :aria-label="t('examples.mail.panes.folders')">
                <div class="p-2">
                    <AccountSwitcher v-model="account" :accounts="accounts" />
                </div>
                <Separator />
                <nav class="flex-1 overflow-auto p-2" :aria-label="t('examples.mail.panes.folders')">
                    <MailNav v-model="folder" :items="primary" />
                    <Separator class="my-2" />
                    <MailNav v-model="folder" :items="secondary" />
                </nav>
            </section>
        </ResizablePanel>

        <ResizableHandle id="mail-handle-1" with-handle />

        <ResizablePanel id="mail-list" :default-size="35" :min-size="25">
            <section class="flex h-full flex-col" :aria-label="t('examples.mail.panes.list')">
                <Tabs v-model="scope" class="flex h-full flex-col">
                    <div class="flex items-center gap-2 px-4 py-2">
                        <h2 class="text-xl font-bold">{{ t(`examples.mail.folders.${folder}`) }}</h2>
                        <TabsList class="ml-auto">
                            <TabsTrigger value="all">{{ t('examples.mail.scope.all') }}</TabsTrigger>
                            <TabsTrigger value="unread">{{ t('examples.mail.scope.unread') }}</TabsTrigger>
                        </TabsList>
                    </div>

                    <Separator />

                    <div class="p-4">
                        <Label for="mail-search" class="sr-only">{{ t('examples.mail.search') }}</Label>
                        <Input
                            id="mail-search"
                            v-model="query"
                            type="search"
                            autocomplete="off"
                            :placeholder="t('examples.mail.search')"
                        />
                    </div>

                    <p class="sr-only" role="status" aria-live="polite">
                        {{ t('examples.mail.resultCount', { n: visible.length }) }}
                    </p>

                    <TabsContent value="all" class="mt-0 min-h-0 flex-1">
                        <MailList v-model="selected" :mails="visible" />
                    </TabsContent>
                    <TabsContent value="unread" class="mt-0 min-h-0 flex-1">
                        <MailList v-model="selected" :mails="visible" />
                    </TabsContent>
                </Tabs>
            </section>
        </ResizablePanel>

        <ResizableHandle id="mail-handle-2" with-handle />

        <ResizablePanel id="mail-detail" :default-size="45" :min-size="30">
            <section class="h-full" :aria-label="t('examples.mail.panes.message')">
                <MailDisplay :mail="current" />
            </section>
        </ResizablePanel>
    </ResizablePanelGroup>
</template>
