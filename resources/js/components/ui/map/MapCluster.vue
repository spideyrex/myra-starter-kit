<script setup lang="ts">
/**
 * MapCluster — renders many points as zoom-aware clusters. Clicking a cluster
 * zooms in to expand it. Accepts points as { lng, lat, ... }.
 */
import { inject, onMounted, onBeforeUnmount, useId } from 'vue';
import type { Map as MapLibreMap, MapMouseEvent, GeoJSONSource } from 'maplibre-gl';
import { MAP_INJECTION_KEY } from './context';

const props = withDefaults(defineProps<{
    points: { lng: number; lat: number }[];
    color?: string;
}>(), {
    color: '#6366f1',
});

const ctx = inject(MAP_INJECTION_KEY);
const uid = useId().replace(/[^a-zA-Z0-9]/g, '');
const sourceId = `cluster-${uid}`;
const clusters = `clusters-${uid}`;
const count = `cluster-count-${uid}`;
const unclustered = `unclustered-${uid}`;
let boundMap: MapLibreMap | null = null;

function build(map: MapLibreMap) {
    if (map.getSource(sourceId)) return;

    map.addSource(sourceId, {
        type: 'geojson',
        cluster: true,
        clusterMaxZoom: 14,
        clusterRadius: 50,
        data: {
            type: 'FeatureCollection',
            features: props.points.map((p) => ({
                type: 'Feature',
                properties: {},
                geometry: { type: 'Point', coordinates: [p.lng, p.lat] },
            })),
        },
    });

    map.addLayer({
        id: clusters,
        type: 'circle',
        source: sourceId,
        filter: ['has', 'point_count'],
        paint: {
            'circle-color': props.color,
            'circle-opacity': 0.85,
            'circle-radius': ['step', ['get', 'point_count'], 16, 10, 22, 30, 30],
        },
    });
    map.addLayer({
        id: count,
        type: 'symbol',
        source: sourceId,
        filter: ['has', 'point_count'],
        layout: { 'text-field': '{point_count_abbreviated}', 'text-size': 12 },
        paint: { 'text-color': '#ffffff' },
    });
    map.addLayer({
        id: unclustered,
        type: 'circle',
        source: sourceId,
        filter: ['!', ['has', 'point_count']],
        paint: {
            'circle-color': props.color,
            'circle-radius': 6,
            'circle-stroke-width': 2,
            'circle-stroke-color': '#ffffff',
        },
    });

    map.on('click', clusters, onClusterClick);
    map.on('mouseenter', clusters, setPointer);
    map.on('mouseleave', clusters, clearPointer);
}

async function onClusterClick(e: MapMouseEvent) {
    if (!boundMap) return;
    const features = boundMap.queryRenderedFeatures(e.point, { layers: [clusters] });
    const clusterId = features[0]?.properties?.cluster_id;
    if (clusterId == null) return;
    const source = boundMap.getSource(sourceId) as GeoJSONSource;
    const zoom = await source.getClusterExpansionZoom(clusterId);
    boundMap.easeTo({ center: (features[0].geometry as any).coordinates, zoom });
}

const setPointer = () => { if (boundMap) boundMap.getCanvas().style.cursor = 'pointer'; };
const clearPointer = () => { if (boundMap) boundMap.getCanvas().style.cursor = ''; };

function onStyleData() {
    if (boundMap && !boundMap.getSource(sourceId)) build(boundMap);
}

onMounted(async () => {
    const map = await ctx?.ready.value;
    if (!map) return;
    boundMap = map;
    build(map);
    map.on('styledata', onStyleData);
});

onBeforeUnmount(() => {
    if (!boundMap) return;
    boundMap.off('styledata', onStyleData);
    boundMap.off('click', clusters, onClusterClick);
    boundMap.off('mouseenter', clusters, setPointer);
    boundMap.off('mouseleave', clusters, clearPointer);
    try {
        for (const id of [clusters, count, unclustered]) {
            if (boundMap.getLayer(id)) boundMap.removeLayer(id);
        }
        if (boundMap.getSource(sourceId)) boundMap.removeSource(sourceId);
    } catch { /* map torn down */ }
    boundMap = null;
});
</script>

<template><span style="display:none" /></template>
