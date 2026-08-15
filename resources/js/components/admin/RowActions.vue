<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { usePermissions } from '@/composables/usePermissions';
import { MoreHorizontal } from 'lucide-vue-next';
import type { RowAction, RowActionsConfig } from '@/types/admin';

const props = defineProps<{
    actions: RowAction[];
    config?: RowActionsConfig;
}>();

const { can } = usePermissions();

const DEFAULT_CONFIG: RowActionsConfig = {
    label: 'Actions',
    icon: MoreHorizontal,
    size: 'sm',
    asButton: false,
    buttonGroup: false,
    placement: 'bottom-end',
    width: 'md',
    maxHeight: '20rem',
};

const cfg = computed<RowActionsConfig>(() => ({ ...DEFAULT_CONFIG, ...(props.config ?? {}) }));

/** Semantic colour → theme token class. Colour is never the sole carrier of meaning. */
function colorClass(action: RowAction): string | undefined {
    if (action.destructive) return 'text-destructive focus:text-destructive';
    switch (action.color) {
        case 'muted': return 'text-muted-foreground';
        case 'primary': return 'text-primary';
        case 'info': return 'text-primary';
        case 'success': return 'text-success';
        case 'warning': return 'text-warning';
        case 'danger': return 'text-destructive focus:text-destructive';
        default: return action.color || undefined;
    }
}

function isVisible(action: RowAction): boolean {
    // Dividers and section headings are structural — never permission-filtered.
    if (action.kind === 'divider' || action.kind === 'section') return true;
    if (action.show === false) return false;
    if (action.permission && !can(action.permission)) return false;
    if (action.kind === 'group') return filterItems(action.items ?? []).length > 0;
    return true;
}

/**
 * Permission filter, then drop headings with nothing under them, then collapse
 * leading / trailing / duplicate dividers. Order matters: dropping a heading can
 * strand the divider that preceded it.
 */
function filterItems(items: RowAction[]): RowAction[] {
    const kept = items.filter(isVisible);

    const headed = kept.filter((item, i) => {
        if (item.kind !== 'section') return true;
        const next = kept[i + 1];
        return !!next && next.kind !== 'section' && next.kind !== 'divider';
    });

    const out: RowAction[] = [];
    for (const item of headed) {
        if (item.kind === 'divider') {
            if (out.length === 0) continue;
            if (out[out.length - 1].kind === 'divider') continue;
        }
        out.push(item);
    }
    while (out.length > 0 && out[out.length - 1].kind === 'divider') out.pop();

    return out;
}

const visibleActions = computed(() => filterItems(props.actions));

/** An item-level separator is redundant right after a divider or a heading. */
function showSeparator(index: number): boolean {
    if (index === 0) return false;
    const prev = visibleActions.value[index - 1];
    return prev.kind !== 'divider' && prev.kind !== 'section';
}

const leafCount = computed(() => visibleActions.value.filter(a => a.kind !== 'divider' && a.kind !== 'section').length);

/** Inline (flat button group) instead of a dropdown. */
const inline = computed(() => {
    if (leafCount.value === 0) return false;
    // A submenu has nowhere to go in a flat button row.
    if (visibleActions.value.some(a => a.kind === 'group')) return false;
    if (cfg.value.buttonGroup) return true;
    return cfg.value.collapseAfter != null && leafCount.value < cfg.value.collapseAfter;
});

const inlineActions = computed(() => visibleActions.value.filter(a => a.kind !== 'divider' && a.kind !== 'section'));

const contentSide = computed<'top' | 'bottom'>(() => (cfg.value.placement.startsWith('top') ? 'top' : 'bottom'));
const contentAlign = computed<'start' | 'end'>(() => (cfg.value.placement.endsWith('start') ? 'start' : 'end'));
const widthClass = computed(() => ({ sm: 'w-44', md: 'w-56', lg: 'w-72' }[cfg.value.width]));
</script>

<template>
    <template v-if="visibleActions.length > 0">
        <!-- Inline: a flat, joined button group. No dropdown, no extra click. -->
        <ButtonGroup v-if="inline">
            <template v-for="(action, index) in inlineActions" :key="index">
                <Button
                    v-if="action.href && action.external"
                    variant="outline"
                    :size="cfg.size"
                    as-child
                    :class="colorClass(action)"
                >
                    <a :href="action.href" target="_blank" rel="noopener noreferrer" :title="action.tooltip">
                        <component :is="action.icon" v-if="action.icon" class="size-4" />
                        {{ action.label }}
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                </Button>
                <Button
                    v-else-if="action.href"
                    variant="outline"
                    :size="cfg.size"
                    as-child
                    :class="colorClass(action)"
                >
                    <Link :href="action.href" :title="action.tooltip">
                        <component :is="action.icon" v-if="action.icon" class="size-4" />
                        {{ action.label }}
                    </Link>
                </Button>
                <Button
                    v-else
                    variant="outline"
                    :size="cfg.size"
                    :class="colorClass(action)"
                    :title="action.tooltip"
                    @click="action.onClick?.()"
                >
                    <component :is="action.icon" v-if="action.icon" class="size-4" />
                    {{ action.label }}
                </Button>
            </template>
        </ButtonGroup>

        <!-- Dropdown -->
        <DropdownMenu v-else>
            <TooltipProvider v-if="cfg.tooltip">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <DropdownMenuTrigger as-child>
                            <Button
                                :variant="cfg.asButton ? 'outline' : 'ghost'"
                                :size="cfg.asButton ? cfg.size : 'sm'"
                                :aria-label="cfg.label"
                            >
                                <component :is="cfg.icon" v-if="cfg.icon" class="size-4" />
                                <span v-if="cfg.asButton">{{ cfg.label }}</span>
                                <Badge v-if="cfg.badge != null && cfg.badge !== ''" variant="secondary" class="ml-1 px-1.5 text-[10px]">
                                    {{ cfg.badge }}
                                </Badge>
                            </Button>
                        </DropdownMenuTrigger>
                    </TooltipTrigger>
                    <TooltipContent>{{ cfg.tooltip }}</TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <DropdownMenuTrigger v-else as-child>
                <Button
                    :variant="cfg.asButton ? 'outline' : 'ghost'"
                    :size="cfg.asButton ? cfg.size : 'sm'"
                    :aria-label="cfg.label"
                >
                    <component :is="cfg.icon" v-if="cfg.icon" class="size-4" />
                    <span v-if="cfg.asButton">{{ cfg.label }}</span>
                    <Badge v-if="cfg.badge != null && cfg.badge !== ''" variant="secondary" class="ml-1 px-1.5 text-[10px]">
                        {{ cfg.badge }}
                    </Badge>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                :align="contentAlign"
                :side="contentSide"
                :class="[widthClass, 'overflow-y-auto']"
                :style="{ maxHeight: cfg.maxHeight }"
            >
                <template v-for="(action, index) in visibleActions" :key="index">
                    <DropdownMenuSeparator v-if="action.kind === 'divider'" />

                    <DropdownMenuLabel v-else-if="action.kind === 'section'" class="text-xs text-muted-foreground">
                        {{ action.label }}
                    </DropdownMenuLabel>

                    <DropdownMenuSub v-else-if="action.kind === 'group'">
                        <DropdownMenuSubTrigger>
                            <component :is="action.icon" v-if="action.icon" class="mr-2 size-4" />
                            {{ action.label }}
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            <template v-for="(child, ci) in filterItems(action.items ?? [])" :key="ci">
                                <DropdownMenuSeparator v-if="child.kind === 'divider'" />
                                <DropdownMenuLabel v-else-if="child.kind === 'section'" class="text-xs text-muted-foreground">
                                    {{ child.label }}
                                </DropdownMenuLabel>
                                <DropdownMenuItem
                                    v-else-if="child.href && child.external"
                                    as-child
                                    :class="colorClass(child)"
                                >
                                    <a :href="child.href" target="_blank" rel="noopener noreferrer">
                                        <component :is="child.icon" v-if="child.icon" class="mr-2 size-4" />
                                        {{ child.label }}
                                        <span class="sr-only">(opens in a new tab)</span>
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuItem v-else-if="child.href" as-child :class="colorClass(child)">
                                    <Link :href="child.href">
                                        <component :is="child.icon" v-if="child.icon" class="mr-2 size-4" />
                                        {{ child.label }}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem v-else :class="colorClass(child)" @click="child.onClick?.()">
                                    <component :is="child.icon" v-if="child.icon" class="mr-2 size-4" />
                                    {{ child.label }}
                                    <Badge v-if="child.badge != null && child.badge !== ''" variant="secondary" class="ml-auto px-1.5 text-[10px]">
                                        {{ child.badge }}
                                    </Badge>
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>

                    <template v-else>
                        <DropdownMenuSeparator v-if="action.separator && showSeparator(index)" />
                        <DropdownMenuItem
                            v-if="action.href && action.external"
                            as-child
                            :class="colorClass(action)"
                        >
                            <a :href="action.href" target="_blank" rel="noopener noreferrer" :title="action.tooltip">
                                <component :is="action.icon" v-if="action.icon" class="mr-2 size-4" />
                                {{ action.label }}
                                <span class="sr-only">(opens in a new tab)</span>
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-else-if="action.href"
                            as-child
                            :class="colorClass(action)"
                        >
                            <Link :href="action.href" :title="action.tooltip">
                                <component :is="action.icon" v-if="action.icon" class="mr-2 size-4" />
                                {{ action.label }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-else
                            :class="colorClass(action)"
                            :title="action.tooltip"
                            @click="action.onClick?.()"
                        >
                            <component :is="action.icon" v-if="action.icon" class="mr-2 size-4" />
                            {{ action.label }}
                            <Badge v-if="action.badge != null && action.badge !== ''" variant="secondary" class="ml-auto px-1.5 text-[10px]">
                                {{ action.badge }}
                            </Badge>
                        </DropdownMenuItem>
                    </template>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
    </template>
</template>
