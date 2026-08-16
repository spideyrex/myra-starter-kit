<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ListMusic, Mic2, Music2, PlayCircle, Radio, User } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';

defineProps<{ playlists: string[] }>();

const { t } = useI18n();

const discover = [
    { id: 'listenNow', icon: PlayCircle },
    { id: 'browse', icon: Music2 },
    { id: 'radio', icon: Radio },
];

const library = [
    { id: 'playlists', icon: ListMusic },
    { id: 'songs', icon: Music2 },
    { id: 'madeForYou', icon: User },
    { id: 'artists', icon: Mic2 },
];
</script>

<template>
    <nav class="space-y-4 py-4" :aria-label="t('examples.music.sidebarLabel')">
        <div class="px-3 py-2">
            <h2 class="mb-2 px-4 text-lg font-semibold tracking-tight">{{ t('examples.music.groups.discover') }}</h2>
            <ul role="list" class="space-y-1">
                <li v-for="item in discover" :key="item.id">
                    <Button variant="ghost" class="w-full justify-start">
                        <component :is="item.icon" class="size-4" aria-hidden="true" />
                        {{ t(`examples.music.nav.${item.id}`) }}
                    </Button>
                </li>
            </ul>
        </div>

        <div class="px-3 py-2">
            <h2 class="mb-2 px-4 text-lg font-semibold tracking-tight">{{ t('examples.music.groups.library') }}</h2>
            <ul role="list" class="space-y-1">
                <li v-for="item in library" :key="item.id">
                    <Button variant="ghost" class="w-full justify-start">
                        <component :is="item.icon" class="size-4" aria-hidden="true" />
                        {{ t(`examples.music.nav.${item.id}`) }}
                    </Button>
                </li>
            </ul>
        </div>

        <div class="py-2">
            <h2 class="relative px-7 text-lg font-semibold tracking-tight">{{ t('examples.music.groups.playlists') }}</h2>
            <ScrollArea class="h-[300px] px-1">
                <ul role="list" class="space-y-1 p-2">
                    <li v-for="playlist in playlists" :key="playlist">
                        <Button variant="ghost" class="w-full justify-start font-normal">
                            <ListMusic class="size-4" aria-hidden="true" />
                            {{ t(`examples.music.playlists.${playlist}`) }}
                        </Button>
                    </li>
                </ul>
            </ScrollArea>
        </div>
    </nav>
</template>
