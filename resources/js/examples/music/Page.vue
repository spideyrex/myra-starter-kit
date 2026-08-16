<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ArrowLeft, ArrowRight, PlusCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel';
import AlbumArtwork from './components/AlbumArtwork.vue';
import MusicSidebar from './components/MusicSidebar.vue';
import PodcastEmptyPlaceholder from './components/PodcastEmptyPlaceholder.vue';
import albums from './data/albums.json';

const { t } = useI18n();
</script>

<template>
    <div class="border-t bg-background">
        <div class="grid lg:grid-cols-5">
            <aside class="hidden border-r lg:block">
                <MusicSidebar :playlists="albums.playlists" />
            </aside>

            <main id="content" class="col-span-3 lg:col-span-4 lg:border-l">
                <div class="h-full px-4 py-6 lg:px-8">
                    <Tabs default-value="music" class="h-full space-y-6">
                        <div class="flex items-center gap-2">
                            <TabsList>
                                <TabsTrigger value="music">{{ t('examples.music.tabs.music') }}</TabsTrigger>
                                <TabsTrigger value="podcasts">{{ t('examples.music.tabs.podcasts') }}</TabsTrigger>
                                <TabsTrigger value="live" disabled>{{ t('examples.music.tabs.live') }}</TabsTrigger>
                            </TabsList>
                            <Button size="sm" class="ml-auto">
                                <PlusCircle class="size-4" aria-hidden="true" />
                                {{ t('examples.music.addMusic') }}
                            </Button>
                        </div>

                        <TabsContent value="music" class="border-none p-0 outline-none">
                            <section :aria-labelledby="'music-listen-now'">
                                <div class="flex items-center justify-between">
                                    <div class="space-y-1">
                                        <h2 id="music-listen-now" class="text-2xl font-semibold tracking-tight">
                                            {{ t('examples.music.listenNow.title') }}
                                        </h2>
                                        <p class="text-sm text-muted-foreground">
                                            {{ t('examples.music.listenNow.description') }}
                                        </p>
                                    </div>
                                </div>
                                <Separator class="my-4" />

                                <Carousel class="w-full" :opts="{ align: 'start' }">
                                    <CarouselContent class="-ml-4">
                                        <CarouselItem
                                            v-for="album in albums.listenNow"
                                            :key="album.id"
                                            class="basis-[180px] pl-4"
                                        >
                                            <AlbumArtwork :album="album" :playlists="albums.playlists" class="w-[160px]" />
                                        </CarouselItem>
                                    </CarouselContent>
                                    <CarouselPrevious>
                                        <ArrowLeft class="size-4" aria-hidden="true" />
                                        <span class="sr-only">{{ t('examples.music.carousel.previous') }}</span>
                                    </CarouselPrevious>
                                    <CarouselNext>
                                        <ArrowRight class="size-4" aria-hidden="true" />
                                        <span class="sr-only">{{ t('examples.music.carousel.next') }}</span>
                                    </CarouselNext>
                                </Carousel>
                            </section>

                            <section class="mt-6" aria-labelledby="music-made-for-you">
                                <div class="space-y-1">
                                    <h2 id="music-made-for-you" class="text-2xl font-semibold tracking-tight">
                                        {{ t('examples.music.madeForYou.title') }}
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        {{ t('examples.music.madeForYou.description') }}
                                    </p>
                                </div>
                                <Separator class="my-4" />

                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                    <AlbumArtwork
                                        v-for="album in albums.madeForYou"
                                        :key="album.id"
                                        :album="album"
                                        :playlists="albums.playlists"
                                        :ratio="4 / 3"
                                    />
                                </div>
                            </section>
                        </TabsContent>

                        <TabsContent value="podcasts" class="border-none p-0 outline-none">
                            <div class="space-y-1">
                                <h2 class="text-2xl font-semibold tracking-tight">
                                    {{ t('examples.music.podcasts.title') }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ t('examples.music.podcasts.description') }}
                                </p>
                            </div>
                            <Separator class="my-4" />
                            <PodcastEmptyPlaceholder />
                        </TabsContent>
                    </Tabs>
                </div>
            </main>
        </div>
    </div>
</template>
