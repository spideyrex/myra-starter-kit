<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Map, MapControls, MapMarker, MapPopup, MapRoute, MapArc, MapCluster } from '@/components/ui/map';
import { MapPin, Plane } from 'lucide-vue-next';

// Major Malaysian cities (lng, lat).
const cities = [
    { id: 'kul', name: 'Kuala Lumpur', state: 'Federal Territory', lng: 101.6869, lat: 3.1390 },
    { id: 'png', name: 'George Town', state: 'Penang', lng: 100.3293, lat: 5.4141 },
    { id: 'jhb', name: 'Johor Bahru', state: 'Johor', lng: 103.7414, lat: 1.4927 },
    { id: 'bki', name: 'Kota Kinabalu', state: 'Sabah', lng: 116.0735, lat: 5.9804 },
    { id: 'kch', name: 'Kuching', state: 'Sarawak', lng: 110.3592, lat: 1.5535 },
    { id: 'lgk', name: 'Langkawi', state: 'Kedah', lng: 99.8000, lat: 6.3500 },
    { id: 'mkz', name: 'Malacca City', state: 'Malacca', lng: 102.2501, lat: 2.1896 },
    { id: 'iph', name: 'Ipoh', state: 'Perak', lng: 101.0901, lat: 4.5975 },
];

// KLIA hub → domestic destinations, as mapcn-style flight arcs.
const hub = cities[0]; // Kuala Lumpur
const arcs = cities
    .filter((c) => c.id !== 'kul')
    .map((c) => ({ id: c.id, from: [hub.lng, hub.lat] as [number, number], to: [c.lng, c.lat] as [number, number] }));

// North–South road trip: KL → Ipoh → George Town.
const roadTrip: [number, number][] = [
    [101.6869, 3.1390], // KL
    [101.0901, 4.5975], // Ipoh
    [100.6500, 5.1500], // approaching mainland Penang
    [100.3293, 5.4141], // George Town
];

// ~70 deterministic points across Peninsular + East Malaysia for clustering.
const clusterPoints = Array.from({ length: 70 }, (_, i) => {
    const peninsular = i % 3 !== 0;
    return peninsular
        ? { lng: 100.3 + ((i * 31) % 100) / 33, lat: 1.5 + ((i * 47) % 100) / 20 }
        : { lng: 109.5 + ((i * 53) % 100) / 14, lat: 1.3 + ((i * 29) % 100) / 22 };
});
</script>

<template>
    <AuthenticatedLayout :breadcrumbs="[{ label: 'Demo' }, { label: 'Map' }]">
        <Head title="Map" />

        <PageHeader
            title="Map — Malaysia"
            description="MapLibre GL maps (mapcn-style shadcn-vue components) showcased across Malaysia: flight arcs, markers, routes, and clustering."
        />

        <div class="mt-6 space-y-6">
            <!-- 1. Flight arcs from KL hub -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Plane class="size-4" /> Flight Connections (Arcs)
                    </CardTitle>
                    <CardDescription>
                        Curved arcs from the Kuala Lumpur hub to domestic destinations — hover an arc to highlight it.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[420px] w-full overflow-hidden rounded-lg border">
                        <Map :center="[108.5, 4.0]" :zoom="5">
                            <MapControls :geolocate="false" />
                            <MapArc
                                :data="arcs"
                                :paint="{ 'line-color': '#6366f1', 'line-width': 2, 'line-dasharray': [2, 2] }"
                                :hover-paint="{ 'line-color': '#ef4444', 'line-width': 4 }"
                            />
                            <MapMarker :lng="hub.lng" :lat="hub.lat" color="#ef4444">
                                <MapPopup>
                                    <p class="font-medium">{{ hub.name }} (Hub)</p>
                                    <p class="text-muted-foreground">{{ hub.state }}</p>
                                </MapPopup>
                            </MapMarker>
                            <MapMarker
                                v-for="c in cities.filter((c) => c.id !== 'kul')"
                                :key="c.id"
                                :lng="c.lng"
                                :lat="c.lat"
                            >
                                <MapPopup>
                                    <p class="font-medium">{{ c.name }}</p>
                                    <p class="text-muted-foreground">{{ c.state }}</p>
                                </MapPopup>
                            </MapMarker>
                        </Map>
                    </div>
                </CardContent>
            </Card>

            <!-- 2. City markers + popups -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">City Markers & Popups</CardTitle>
                    <CardDescription>Major Malaysian cities — click a marker for its state.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[420px] w-full overflow-hidden rounded-lg border">
                        <Map :center="[108.5, 4.0]" :zoom="5">
                            <MapControls :geolocate="false" :fullscreen="false" />
                            <MapMarker v-for="c in cities" :key="c.id" :lng="c.lng" :lat="c.lat">
                                <MapPopup>
                                    <p class="flex items-center gap-1 font-medium">
                                        <MapPin class="size-3.5" /> {{ c.name }}
                                    </p>
                                    <p class="text-muted-foreground">{{ c.state }}</p>
                                </MapPopup>
                            </MapMarker>
                        </Map>
                    </div>
                </CardContent>
            </Card>

            <!-- 3. Road-trip route -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Route Line — KL to Penang</CardTitle>
                    <CardDescription>A polyline along the North–South corridor: Kuala Lumpur → Ipoh → George Town.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[420px] w-full overflow-hidden rounded-lg border">
                        <Map :center="[100.9, 4.3]" :zoom="6">
                            <MapControls :geolocate="false" :fullscreen="false" />
                            <MapRoute :coordinates="roadTrip" color="#6366f1" />
                            <MapMarker :lng="roadTrip[0][0]" :lat="roadTrip[0][1]" color="#22c55e">
                                <MapPopup>Start — Kuala Lumpur</MapPopup>
                            </MapMarker>
                            <MapMarker :lng="roadTrip[roadTrip.length - 1][0]" :lat="roadTrip[roadTrip.length - 1][1]" color="#ef4444">
                                <MapPopup>End — George Town</MapPopup>
                            </MapMarker>
                        </Map>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. Clustering across Malaysia -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Marker Clustering</CardTitle>
                    <CardDescription>{{ clusterPoints.length }} outlets across Peninsular &amp; East Malaysia — click a cluster to zoom in.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="h-[420px] w-full overflow-hidden rounded-lg border">
                        <Map :center="[108.5, 4.0]" :zoom="5">
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
