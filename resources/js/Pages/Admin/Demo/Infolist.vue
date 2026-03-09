<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import InfolistEntries from '@/components/admin/InfolistEntries.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Section, Grid, Tabs, Tab } from '@/composables/useInfolistSchema';
import {
    TextEntry, BadgeEntry, DateEntry, BooleanEntry, ImageEntry,
    RepeatableEntry, KeyValueEntry,
} from '@/composables/useInfolistSchema';
import { ArrowLeft, User, ShieldCheck, Activity } from 'lucide-vue-next';

const props = defineProps<{
    user: Record<string, any>;
}>();

const schema: any[] = [
    Section.make('User Profile').icon(User).description('Basic user information').columns(3).schema([
        ImageEntry.make('avatar').label('Avatar').circular().size(80),
        TextEntry.make('name').label('Full Name').copyable(),
        TextEntry.make('email').label('Email Address').copyable(),
        BadgeEntry.make('status').label('Status').colors({
            active: 'default',
            suspended: 'destructive',
            pending: 'secondary',
        }),
        TextEntry.make('role').label('Role').badge().badgeColors({
            admin: 'default',
            editor: 'secondary',
            user: 'outline',
        }),
        DateEntry.make('created_at').label('Member Since').format('date'),
        DateEntry.make('last_login_at').label('Last Login').format('relative'),
        BooleanEntry.make('email_verified').label('Email Verified'),
        BooleanEntry.make('two_factor_enabled').label('2FA Enabled'),
    ] as any),

    Tabs.make([
        Tab.make('Details').icon(User).schema([
            TextEntry.make('phone').label('Phone Number').default('Not provided'),
            TextEntry.make('company').label('Company'),
            TextEntry.make('bio').label('Biography').colSpan(2),
            TextEntry.make('balance').label('Account Balance').money(),
            TextEntry.make('orders_count').label('Total Orders').suffix(' orders'),
        ] as any),
        Tab.make('Security').icon(ShieldCheck).schema([
            TextEntry.make('ip_address').label('Last IP Address').copyable(),
            TextEntry.make('user_agent').label('Last User Agent'),
            DateEntry.make('password_changed_at').label('Password Changed').format('relative'),
            KeyValueEntry.make('metadata').label('User Metadata'),
        ] as any),
        Tab.make('Activity').icon(Activity).schema([
            RepeatableEntry.make('recent_activity').label('Recent Activity').colSpan(2).schema([
                TextEntry.make('action').label('Action'),
                DateEntry.make('date').label('Date').format('relative'),
                TextEntry.make('ip').label('IP Address'),
            ]),
        ] as any),
    ]),
];
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'Demo', href: route('admin.demo.index') },
        { label: 'Infolist' },
    ]">
        <Head title="Infolist Demo" />

        <PageHeader title="Infolist / Show Page" description="Schema-based read-only detail views mirroring the form builder pattern.">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="route('admin.demo.index')">
                        <ArrowLeft class="mr-2 size-4" />
                        Back to Demos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mt-6 grid gap-4">
            <InfolistEntries :schema="schema" :record="user" />
        </div>
    </AuthenticatedLayout>
</template>
