<script setup lang="ts">
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Check, ChevronsUpDown } from 'lucide-vue-next';

const page = usePage<PageProps>();
const currentTeam = computed(() => page.props.currentTeam);
const teams = computed(() => page.props.teams || []);

function switchTeam(teamId: number) {
    if (teamId === currentTeam.value?.id) return;
    router.post(route('teams.switch', { team: teamId }), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="h-8 gap-1">
                <span class="truncate max-w-[120px]">{{ currentTeam?.name || 'Select team' }}</span>
                <ChevronsUpDown class="size-3.5 shrink-0 text-muted-foreground" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-48">
            <DropdownMenuItem
                v-for="team in teams"
                :key="team.id"
                class="flex items-center justify-between"
                @click="switchTeam(team.id)"
            >
                <span class="truncate">{{ team.name }}</span>
                <Check v-if="team.id === currentTeam?.id" class="size-4 shrink-0 text-primary" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
