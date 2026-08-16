<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

import type { Account } from '../data/types';

defineProps<{ accounts: Account[]; collapsed?: boolean }>();

const model = defineModel<string>({ required: true });

const { t } = useI18n();
</script>

<template>
    <div class="grid gap-1.5">
        <Label for="mail-account" :class="collapsed ? 'sr-only' : 'text-xs text-muted-foreground'">
            {{ t('examples.mail.account') }}
        </Label>
        <NativeSelect id="mail-account" v-model="model" class="w-full">
            <option v-for="account in accounts" :key="account.email" :value="account.email">
                {{ account.label }} — {{ account.email }}
            </option>
        </NativeSelect>
    </div>
</template>
