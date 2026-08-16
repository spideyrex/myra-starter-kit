<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { MoreVertical } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { AspectRatio } from '@/components/ui/aspect-ratio';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';

const props = defineProps<{
    album: { id: string; title: string; artist: string };
    playlists: string[];
    ratio?: number;
    class?: string;
}>();

const { t } = useI18n();

/** Deterministic, self-contained cover art: no remote image, no new asset. */
const cover = computed(() => {
    const hue = [...props.album.id].reduce((acc, char) => (acc * 31 + char.charCodeAt(0)) % 360, 7);
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300" role="presentation">`
        + `<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">`
        + `<stop offset="0%" stop-color="hsl(${hue} 70% 55%)"/>`
        + `<stop offset="100%" stop-color="hsl(${(hue + 48) % 360} 70% 32%)"/>`
        + `</linearGradient></defs>`
        + `<rect width="300" height="300" fill="url(#g)"/>`
        + `<circle cx="150" cy="150" r="52" fill="rgba(0,0,0,0.35)"/>`
        + `<circle cx="150" cy="150" r="10" fill="rgba(255,255,255,0.85)"/>`
        + `</svg>`;

    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
});

const artwork = computed(() => t('examples.music.artworkAlt', { title: props.album.title, artist: props.album.artist }));
</script>

<template>
    <div :class="cn('space-y-3', props.class)">
        <ContextMenu>
            <ContextMenuTrigger as-child>
                <button
                    type="button"
                    class="block w-full overflow-hidden rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-label="t('examples.music.openMenu', { title: album.title })"
                >
                    <AspectRatio :ratio="ratio ?? 1">
                        <img :src="cover" :alt="artwork" class="size-full object-cover transition-all hover:scale-105" />
                    </AspectRatio>
                </button>
            </ContextMenuTrigger>
            <ContextMenuContent class="w-40">
                <ContextMenuItem>{{ t('examples.music.menu.addToLibrary') }}</ContextMenuItem>
                <ContextMenuSub>
                    <ContextMenuSubTrigger>{{ t('examples.music.menu.addToPlaylist') }}</ContextMenuSubTrigger>
                    <ContextMenuSubContent class="w-48">
                        <ContextMenuItem v-for="playlist in playlists" :key="playlist">
                            {{ t(`examples.music.playlists.${playlist}`) }}
                        </ContextMenuItem>
                    </ContextMenuSubContent>
                </ContextMenuSub>
                <ContextMenuSeparator />
                <ContextMenuItem>{{ t('examples.music.menu.play') }}</ContextMenuItem>
                <ContextMenuItem>{{ t('examples.music.menu.like') }}</ContextMenuItem>
                <ContextMenuItem>{{ t('examples.music.menu.share') }}</ContextMenuItem>
            </ContextMenuContent>
        </ContextMenu>

        <div class="flex items-start gap-1 text-sm">
            <div class="min-w-0">
                <p class="truncate font-medium leading-none">{{ album.title }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ album.artist }}</p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                class="ml-auto size-7 shrink-0"
                :aria-label="t('examples.music.openMenu', { title: album.title })"
            >
                <MoreVertical class="size-4" aria-hidden="true" />
            </Button>
        </div>
    </div>
</template>
