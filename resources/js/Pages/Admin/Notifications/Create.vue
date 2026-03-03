<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { FormField, ResourceForm } from '@/components/admin';
import { useResourceForm } from '@/composables/useResourceForm';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';

interface SimpleUser {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    users: SimpleUser[];
}>();

const { form, submit } = useResourceForm({
    data: {
        type: 'system',
        target: 'all',
        user_ids: [] as number[],
        title: '',
        message: '',
        action_url: '',
    },
    storeRoute: 'admin.notifications.store',
    confirm: {
        title: 'Send Notification',
        description: 'Are you sure you want to send this notification?',
        confirmText: 'Send',
    },
});

const userPickerOpen = ref(false);
const userSearch = ref('');

const filteredUsers = computed(() => {
    const q = userSearch.value.toLowerCase();
    if (!q) return props.users;
    return props.users.filter(
        u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
    );
});

const selectedUsers = computed(() =>
    props.users.filter(u => form.user_ids.includes(u.id))
);

function toggleUser(userId: number) {
    const idx = form.user_ids.indexOf(userId);
    if (idx === -1) {
        form.user_ids.push(userId);
    } else {
        form.user_ids.splice(idx, 1);
    }
}

function removeUser(userId: number) {
    const idx = form.user_ids.indexOf(userId);
    if (idx !== -1) form.user_ids.splice(idx, 1);
}

// Reset user_ids when switching to "all"
watch(() => form.target, (val) => {
    if (val === 'all') form.user_ids = [];
});
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[
        { label: 'System' },
        { label: 'Notifications', href: route('admin.notifications.index') },
        { label: 'Send' },
    ]">
        <Head title="Send Notification" />

        <PageHeader title="Send Notification" description="Compose and send a notification to users." />

        <ResourceForm
            :processing="form.processing"
            submit-text="Send Notification"
            :cancel-href="route('admin.notifications.index')"
            :columns="1"
            @submit="submit"
        >
            <FormField
                label="Notification Type"
                name="type"
                type="select"
                v-model="form.type"
                :options="[
                    { label: 'System', value: 'system' },
                    { label: 'User Action', value: 'user_action' },
                    { label: 'Security Alert', value: 'security_alert' },
                ]"
                :error="form.errors.type"
                required
            />

            <FormField
                label="Target"
                name="target"
                type="radio"
                v-model="form.target"
                :options="[
                    { label: 'All Users (Blast)', value: 'all' },
                    { label: 'Specific Users', value: 'specific' },
                ]"
                :inline="true"
                :error="form.errors.target"
                required
            />

            <!-- User multi-select (only visible when target = specific) -->
            <div v-if="form.target === 'specific'" class="space-y-2">
                <Label>
                    Recipients
                    <span class="text-destructive">*</span>
                </Label>

                <!-- Selected user chips -->
                <div v-if="selectedUsers.length" class="flex flex-wrap gap-1 mb-2">
                    <Badge v-for="u in selectedUsers" :key="u.id" variant="secondary" class="gap-1 pr-1">
                        {{ u.name }}
                        <button
                            type="button"
                            class="ml-1 rounded-full p-0.5 hover:bg-muted"
                            @click="removeUser(u.id)"
                        >
                            <X class="size-3" />
                        </button>
                    </Badge>
                </div>

                <Popover v-model:open="userPickerOpen">
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            role="combobox"
                            :aria-expanded="userPickerOpen"
                            class="w-full justify-between"
                            type="button"
                        >
                            <span class="text-muted-foreground">
                                {{ selectedUsers.length
                                    ? `${selectedUsers.length} user${selectedUsers.length > 1 ? 's' : ''} selected`
                                    : 'Select users...'
                                }}
                            </span>
                            <ChevronsUpDown class="ml-2 size-4 shrink-0 opacity-50" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[--radix-popover-trigger-width] p-0" align="start">
                        <Command>
                            <CommandInput v-model="userSearch" placeholder="Search users..." />
                            <CommandEmpty>No users found.</CommandEmpty>
                            <CommandList>
                                <CommandGroup>
                                    <CommandItem
                                        v-for="u in filteredUsers"
                                        :key="u.id"
                                        :value="u.name + ' ' + u.email"
                                        @select.prevent="toggleUser(u.id)"
                                    >
                                        <Check
                                            class="mr-2 size-4"
                                            :class="form.user_ids.includes(u.id) ? 'opacity-100' : 'opacity-0'"
                                        />
                                        <div>
                                            <p class="text-sm">{{ u.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ u.email }}</p>
                                        </div>
                                    </CommandItem>
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>

                <p v-if="form.errors.user_ids" class="text-sm text-destructive">{{ form.errors.user_ids }}</p>
            </div>

            <FormField
                label="Title"
                name="title"
                type="text"
                v-model="form.title"
                placeholder="Notification title"
                :error="form.errors.title"
                required
            />

            <FormField
                label="Message"
                name="message"
                type="textarea"
                v-model="form.message"
                placeholder="Notification message"
                :rows="4"
                :error="form.errors.message"
                required
            />

            <FormField
                label="Action URL"
                name="action_url"
                type="url"
                v-model="form.action_url"
                placeholder="https://example.com/path"
                hint="Optional link the user can click on."
                :error="form.errors.action_url"
            />
        </ResourceForm>
    </AuthenticatedLayout>
</template>
