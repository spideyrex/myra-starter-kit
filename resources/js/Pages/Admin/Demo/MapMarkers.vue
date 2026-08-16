<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Map, MapControls, MapMarker, MapPopup } from '@/components/ui/map';
import { Marker, MarkerContent, MarkerIcon } from '@/components/ui/marker';
import { MapPin, Navigation } from 'lucide-vue-next';

interface MarkerRow {
    id: string;
    name: string;
    region: string;
    lng: number;
    lat: number;
    tone: string;
}

const props = withDefaults(defineProps<{ markers?: MarkerRow[] }>(), { markers: () => [] });

const { t } = useI18n();

const selected = ref<string | null>(null);
</script>

<template>
    <AuthenticatedLayout
        :breadcrumbs="[{ label: t('navGroups.demo') }, { label: t('gallery.demos.mapMarkers.title') }]"
    >
        <Head :title="t('gallery.demos.mapMarkers.title')" />

        <PageHeader
            :title="t('gallery.demos.mapMarkers.title')"
            :description="t('gallery.demos.mapMarkers.description')"
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.mapMarkers.mapTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.mapMarkers.mapDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <Map :center="[109, 3.5]" :zoom="4" class="h-[420px] w-full rounded-md">
                        <MapControls />
                        <MapMarker
                            v-for="marker in props.markers"
                            :key="marker.id"
                            :lng="marker.lng"
                            :lat="marker.lat"
                            @click="selected = marker.id"
                        >
                            <MapPopup>
                                <p class="text-sm font-medium">{{ marker.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ marker.region }}</p>
                            </MapPopup>
                        </MapMarker>
                    </Map>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('gallery.componentDemos.mapMarkers.listTitle') }}</CardTitle>
                    <CardDescription>{{ t('gallery.componentDemos.mapMarkers.listDescription') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- The canvas is not the only channel: every marker is also a row here. -->
                    <ul role="list" class="space-y-2">
                        <li v-for="marker in props.markers" :key="marker.id">
                            <Marker as-child variant="border">
                                <button
                                    type="button"
                                    class="w-full rounded-md px-1 py-1.5 text-left hover:bg-muted"
                                    :aria-current="selected === marker.id ? 'true' : undefined"
                                    @click="selected = marker.id"
                                >
                                    <MarkerIcon>
                                        <MapPin />
                                    </MarkerIcon>
                                    <MarkerContent>
                                        <span class="font-medium text-foreground">{{ marker.name }}</span>
                                        <span class="ml-1 text-xs">{{ marker.region }}</span>
                                    </MarkerContent>
                                </button>
                            </Marker>
                        </li>
                    </ul>

                    <Marker variant="separator" class="my-4">
                        <MarkerContent>{{ t('gallery.componentDemos.mapMarkers.separator') }}</MarkerContent>
                    </Marker>

                    <Marker>
                        <MarkerIcon>
                            <Navigation />
                        </MarkerIcon>
                        <MarkerContent>
                            <span role="status" aria-live="polite">
                                {{
                                    selected
                                        ? t('gallery.componentDemos.mapMarkers.selected', {
                                            name: props.markers.find(m => m.id === selected)?.name ?? '',
                                        })
                                        : t('gallery.componentDemos.mapMarkers.none')
                                }}
                            </span>
                        </MarkerContent>
                    </Marker>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
