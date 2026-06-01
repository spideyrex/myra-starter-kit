<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Map, MapControls, MapMarker, MapPopup, MapRoute, MapCluster } from '@/components/ui/map';
import { MapPin } from 'lucide-vue-next';

// Sample store locations around Kuala Lumpur (lng, lat).
const stores = [
    { id: 1, name: 'Myra HQ', address: 'KLCC, Kuala Lumpur', lng: 101.7117, lat: 3.1578 },
    { id: 2, name: 'Bangsar Branch', address: 'Bangsar, KL', lng: 101.6711, lat: 3.1283 },
    { id: 3, name: 'Mont Kiara Branch', address: 'Mont Kiara, KL', lng: 101.6500, lat: 3.1726 },
    { id: 4, name: 'Cheras Branch', address: 'Cheras, KL', lng: 101.7440, lat: 3.0904 },
];

// A delivery route (ordered waypoints).
const route: [number, number][] = [
    [101.7117, 3.1578],
    [101.6869, 3.1390],
    [101.6711, 3.1283],
    [101.6500, 3.1726],
];

// ~60 deterministic points scattered around KL for the clustering demo.
const clusterPoints = Array.from({ length: 60 }, (_, i) => ({
    lng: 101.60 + ((i * 37) % 100) / 500,
    lat: 3.05 + ((i * 53) % 100) / 500,
}));
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo' }, { label: 'Map' }]">
        <Head title="Map" />

        <PageHeader
            title="Map"
            description="Interactive maps with MapLibre GL — theme-aware shadcn-vue components (markers, popups, routes, clustering)."
        />

        <div class="mt-6 space-y-6">
            <!-- 1. Base map + controls -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Base Map & Controls</CardTitle>
                    <CardDescription>
                        Vector basemap with zoom/compass, geolocate, fullscreen, and scale controls.
                        The basemap follows your light/dark theme automatically.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[360px] w-full overflow-hidden rounded-lg border">
                        <Map :center="[101.6869, 3.139]" :zoom="11">
                            <MapControls />
                        </Map>
                    </div>
                </CardContent>
            </Card>

            <!-- 2. Markers + popups -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Markers & Popups</CardTitle>
                    <CardDescription>Click a marker to open its popup.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[360px] w-full overflow-hidden rounded-lg border">
                        <Map :center="[101.6869, 3.139]" :zoom="11">
                            <MapControls :geolocate="false" :fullscreen="false" />
                            <MapMarker v-for="s in stores" :key="s.id" :lng="s.lng" :lat="s.lat">
                                <MapPopup>
                                    <p class="flex items-center gap-1 font-medium">
                                        <MapPin class="size-3.5" /> {{ s.name }}
                                    </p>
                                    <p class="text-muted-foreground">{{ s.address }}</p>
                                </MapPopup>
                            </MapMarker>
                        </Map>
                    </div>
                </CardContent>
            </Card>

            <!-- 3. Route line -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Route Line</CardTitle>
                    <CardDescription>A polyline drawn through ordered waypoints.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[360px] w-full overflow-hidden rounded-lg border">
                        <Map :center="[101.68, 3.145]" :zoom="11">
                            <MapControls :geolocate="false" :fullscreen="false" />
                            <MapRoute :coordinates="route" />
                            <MapMarker :lng="route[0][0]" :lat="route[0][1]" color="#22c55e">
                                <MapPopup>Start</MapPopup>
                            </MapMarker>
                            <MapMarker :lng="route[route.length - 1][0]" :lat="route[route.length - 1][1]" color="#ef4444">
                                <MapPopup>Destination</MapPopup>
                            </MapMarker>
                        </Map>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. Clustering -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Marker Clustering</CardTitle>
                    <CardDescription>{{ clusterPoints.length }} points grouped into clusters — click a cluster to zoom in.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[360px] w-full overflow-hidden rounded-lg border">
                        <Map :center="[101.69, 3.14]" :zoom="10">
                            <MapControls :geolocate="false" :fullscreen="false" />
                            <MapCluster :points="clusterPoints" />
                        </Map>
                    </div>
                </CardContent>
            </Card>

            <p class="text-xs text-muted-foreground">
                Coordinates are <code class="rounded bg-muted px-1">[longitude, latitude]</code> (MapLibre/GeoJSON order).
                The default CARTO basemap is free for non-commercial use only — for production, pass a custom
                <code class="rounded bg-muted px-1">map-style</code> (MapTiler, Protomaps, Stadia, or self-hosted tiles).
            </p>
        </div>
    </AuthenticatedLayout>
</template>
