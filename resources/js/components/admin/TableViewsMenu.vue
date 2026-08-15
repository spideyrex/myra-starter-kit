<script setup lang="ts">
import { computed, ref, shallowRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Bookmark, Check, Link2, Pencil, Save, Star, Trash2, Users } from 'lucide-vue-next';
import type { SavedView } from '@/types/table-views';

const props = withDefaults(defineProps<{
    views: SavedView[];
    active: SavedView | null;
    isModified?: boolean;
    busy?: boolean;
    canShareWithTeam?: boolean;
    /** Full-param share URL for a view; the menu copies it to the clipboard. */
    shareUrl?: (view: SavedView) => string;
}>(), {
    isModified: false,
    busy: false,
    canShareWithTeam: false,
});

const emit = defineEmits<{
    apply: [view: SavedView];
    saveAs: [name: string, visibility: 'private' | 'team'];
    updateActive: [];
    rename: [view: SavedView, name: string];
    remove: [view: SavedView];
    makeDefault: [view: SavedView];
}>();

const { t } = useI18n();

const mine = computed(() => props.views.filter(v => v.visibility !== 'team'));
const team = computed(() => props.views.filter(v => v.visibility === 'team'));

const dialogOpen = ref(false);
const dialogMode = ref<'save' | 'rename'>('save');
const draftName = ref('');
const draftTeam = ref(false);
const renameTarget = shallowRef<SavedView | null>(null);

const triggerLabel = computed(() => props.active?.name ?? t('views.title'));
const canPersistActive = computed(() => !!props.active && props.active.id !== null && props.active.owned);

// Deferred a tick so the closing menu returns focus before the dialog traps it.
function openSave() {
    dialogMode.value = 'save';
    draftName.value = '';
    draftTeam.value = false;
    renameTarget.value = null;
    setTimeout(() => { dialogOpen.value = true; }, 0);
}

function openRename(view: SavedView) {
    dialogMode.value = 'rename';
    draftName.value = view.name;
    renameTarget.value = view;
    setTimeout(() => { dialogOpen.value = true; }, 0);
}

function submit() {
    const name = draftName.value.trim();
    if (!name) return;
    if (dialogMode.value === 'rename' && renameTarget.value) {
        emit('rename', renameTarget.value, name);
    } else {
        emit('saveAs', name, draftTeam.value ? 'team' : 'private');
    }
    dialogOpen.value = false;
}

async function copyLink(view: SavedView) {
    const href = props.shareUrl?.(view);
    if (!href) return;
    try {
        await navigator.clipboard.writeText(new URL(href, window.location.origin).toString());
        toast.success(t('views.linkCopied'));
    } catch {}
}

function isActive(view: SavedView): boolean {
    return props.active?.name === view.name && props.active?.id === view.id;
}
</script>

<template>
    <div class="inline-flex">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :class="{ 'border-primary text-primary': !!active }"
                    :aria-label="t('views.a11y.menu')"
                >
                    <Bookmark class="mr-2 size-4" />
                    <span class="max-w-[10rem] truncate">{{ triggerLabel }}</span>
                    <span v-if="isModified" class="ml-1.5 inline-flex items-center gap-1 text-xs text-muted-foreground">
                        <span class="size-1.5 rounded-full bg-primary" aria-hidden="true" />
                        {{ t('views.modified') }}
                    </span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" class="w-64">
                <DropdownMenuLabel>{{ t('views.mine') }}</DropdownMenuLabel>
                <DropdownMenuItem v-if="mine.length === 0" disabled>
                    {{ t('views.none') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-for="view in mine"
                    :key="`mine-${view.id ?? view.name}`"
                    @click="emit('apply', view)"
                >
                    <Check class="mr-2 size-4" :class="isActive(view) ? 'opacity-100' : 'opacity-0'" />
                    <span class="flex-1 truncate">{{ view.name }}</span>
                    <Star v-if="view.is_default" class="ml-1 size-3.5 text-muted-foreground" :aria-label="t('views.default')" />
                </DropdownMenuItem>

                <template v-if="team.length > 0">
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel>{{ t('views.team') }}</DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="view in team"
                        :key="`team-${view.id ?? view.name}`"
                        @click="emit('apply', view)"
                    >
                        <Check class="mr-2 size-4" :class="isActive(view) ? 'opacity-100' : 'opacity-0'" />
                        <span class="flex-1 truncate">{{ view.name }}</span>
                        <Users class="ml-1 size-3.5 text-muted-foreground" :aria-label="t('views.shared')" />
                    </DropdownMenuItem>
                </template>

                <DropdownMenuSeparator />

                <DropdownMenuItem :disabled="busy" @click="openSave">
                    <Save class="mr-2 size-4" />
                    {{ t('views.saveAs') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="canPersistActive"
                    :disabled="busy || !isModified"
                    @click="emit('updateActive')"
                >
                    <Check class="mr-2 size-4" />
                    {{ t('views.update') }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="canPersistActive" @click="openRename(active as SavedView)">
                    <Pencil class="mr-2 size-4" />
                    {{ t('views.rename') }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="canPersistActive" @click="emit('makeDefault', active as SavedView)">
                    <Star class="mr-2 size-4" />
                    {{ t('views.makeDefault') }}
                </DropdownMenuItem>
                <DropdownMenuItem v-if="active && shareUrl" @click="copyLink(active as SavedView)">
                    <Link2 class="mr-2 size-4" />
                    {{ t('views.copyLink') }}
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="canPersistActive"
                    class="text-destructive focus:text-destructive"
                    @click="emit('remove', active as SavedView)"
                >
                    <Trash2 class="mr-2 size-4" />
                    {{ t('views.delete') }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ dialogMode === 'rename' ? t('views.rename') : t('views.save') }}</DialogTitle>
                    <DialogDescription>{{ t('views.namePlaceholder') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <Label for="table-view-name">{{ t('views.namePlaceholder') }}</Label>
                        <Input
                            id="table-view-name"
                            v-model="draftName"
                            maxlength="60"
                            :placeholder="t('views.namePlaceholder')"
                            @keydown.enter.prevent="submit"
                        />
                    </div>
                    <div v-if="dialogMode === 'save' && canShareWithTeam" class="flex items-center gap-2">
                        <Checkbox id="table-view-team" :model-value="draftTeam" @update:model-value="(v: boolean | 'indeterminate') => draftTeam = v === true" />
                        <Label for="table-view-team" class="cursor-pointer text-sm font-normal">{{ t('views.shareWithTeam') }}</Label>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" size="sm" @click="dialogOpen = false">{{ t('common.cancel') }}</Button>
                    <Button size="sm" :disabled="busy || !draftName.trim()" @click="submit">{{ t('views.save') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
