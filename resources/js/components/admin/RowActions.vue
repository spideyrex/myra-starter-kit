<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/composables/usePermissions';
import { MoreHorizontal } from 'lucide-vue-next';
import type { RowAction } from '@/types/admin';

const props = defineProps<{
    actions: RowAction[];
}>();

const { can } = usePermissions();

const visibleActions = computed(() =>
    props.actions.filter(action => {
        if (action.show === false) return false;
        if (action.permission && !can(action.permission)) return false;
        return true;
    }),
);
</script>

<template>
    <DropdownMenu v-if="visibleActions.length > 0">
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="sm">
                <MoreHorizontal class="size-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <template v-for="(action, index) in visibleActions" :key="index">
                <DropdownMenuSeparator v-if="action.separator" />
                <DropdownMenuItem
                    v-if="action.href"
                    as-child
                    :class="{ 'text-destructive': action.destructive }"
                >
                    <Link :href="action.href">
                        <component :is="action.icon" v-if="action.icon" class="mr-2 size-4" />
                        {{ action.label }}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-else
                    :class="{ 'text-destructive': action.destructive }"
                    @click="action.onClick?.()"
                >
                    <component :is="action.icon" v-if="action.icon" class="mr-2 size-4" />
                    {{ action.label }}
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
