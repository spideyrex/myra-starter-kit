<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import LoadingButton from '@/components/LoadingButton.vue';
import { Badge } from '@/components/ui/badge';
import Modal from '@/components/Modal.vue';
import { SimpleTable, FormField, DateCell } from '@/components/admin';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { useConfirm } from '@/composables/useConfirm';
import { Plus, Trash2, Key, Copy, Check } from 'lucide-vue-next';
import type { SimpleTableColumn } from '@/types/admin';

defineProps<{
    tokens: Array<{
        id: number;
        name: string;
        abilities: string[];
        last_used_at: string | null;
        created_at: string;
    }>;
}>();

const page = usePage();
const showCreate = ref(false);
const form = useForm({ name: '' });
const { confirm } = useConfirm();
const { confirmDelete } = useConfirmAction();
const copied = ref(false);

const newToken = computed(() => (page.props.flash as any)?.newToken as string | null);
const showTokenModal = computed(() => !!newToken.value);

const columns: SimpleTableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'abilities', label: 'Abilities' },
    { key: 'last_used_at', label: 'Last Used' },
    { key: 'created_at', label: 'Created' },
];

function closeTokenModal() {
    router.reload({ only: ['flash'] });
}

async function copyToken() {
    if (newToken.value) {
        await navigator.clipboard.writeText(newToken.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    }
}

async function createToken() {
    const confirmed = await confirm({ title: 'Create API Token', description: 'Are you sure you want to create this API token?', confirmText: 'Create' });
    if (!confirmed) return;
    form.post(route('admin.api-tokens.store'), {
        onSuccess: () => { showCreate.value = false; form.reset(); },
    });
}
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'System' }, { label: 'API Tokens' }]">
        <Head title="API Tokens" />
        <PageHeader title="API Tokens" description="Manage API tokens for third-party access.">
            <template #actions>
                <Button @click="showCreate = true"><Plus class="mr-2 size-4" />Create Token</Button>
            </template>
        </PageHeader>

        <SimpleTable :columns="columns" :items="tokens" empty-title="No API tokens" empty-description="Create your first API token." :empty-icon="Key">
            <template #cell-name="{ value }">
                <span class="font-medium">{{ value }}</span>
            </template>
            <template #cell-abilities="{ value }">
                <Badge v-for="ability in value" :key="ability" variant="secondary" class="mr-1">{{ ability }}</Badge>
            </template>
            <template #cell-last_used_at="{ value }">
                <span class="text-muted-foreground">{{ value || 'Never' }}</span>
            </template>
            <template #cell-created_at="{ value }">
                <DateCell :value="value" />
            </template>
            <template #actions="{ row }">
                <Button variant="ghost" size="sm" @click="confirmDelete('admin.api-tokens.destroy', row.id, { title: 'Revoke Token', description: 'This token will be permanently revoked.', confirmText: 'Revoke' })">
                    <Trash2 class="size-4 text-destructive" />
                </Button>
            </template>
        </SimpleTable>

        <Modal v-model:open="showCreate" title="Create API Token" description="Give this token a name to identify it.">
            <form @submit.prevent="createToken" class="space-y-4">
                <FormField label="Token Name" name="token_name" v-model="form.name" required placeholder="e.g., Mobile App" :error="form.errors.name" />
            </form>
            <template #footer>
                <LoadingButton :loading="form.processing" type="button" @click="createToken">Create Token</LoadingButton>
            </template>
        </Modal>

        <Modal :open="showTokenModal" @update:open="closeTokenModal" title="API Token Created" description="Copy your new API token now. You won't be able to see it again.">
            <div class="space-y-3">
                <div class="flex items-center gap-2 rounded-md border bg-muted p-3">
                    <code class="flex-1 break-all text-sm font-mono">{{ newToken }}</code>
                    <Button variant="ghost" size="sm" @click="copyToken">
                        <Check v-if="copied" class="size-4 text-success" />
                        <Copy v-else class="size-4" />
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">Store this token in a secure location. It will not be shown again.</p>
            </div>
            <template #footer>
                <Button @click="closeTokenModal">Done</Button>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>
