<script setup lang="ts">
/** MapRoute — draws a line through an ordered list of [lng, lat] coordinates. */
import { inject, onMounted, onBeforeUnmount, useId } from 'vue';
import type { Map as MapLibreMap } from 'maplibre-gl';
import { MAP_INJECTION_KEY } from './context';

const props = withDefaults(defineProps<{
    coordinates: [number, number][];
    color?: string;
    width?: number;
}>(), {
    color: '#6366f1',
    width: 4,
});

const ctx = inject(MAP_INJECTION_KEY);
const uid = useId().replace(/[^a-zA-Z0-9]/g, '');
const sourceId = `route-${uid}`;
const layerId = `route-layer-${uid}`;
let boundMap: MapLibreMap | null = null;

function addRoute(map: MapLibreMap) {
    if (map.getLayer(layerId)) map.removeLayer(layerId);
    if (map.getSource(sourceId)) map.removeSource(sourceId);

    map.addSource(sourceId, {
        type: 'geojson',
        data: {
            type: 'Feature',
            properties: {},
            geometry: { type: 'LineString', coordinates: props.coordinates },
        },
    });
    map.addLayer({
        id: layerId,
        type: 'line',
        source: sourceId,
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': props.color, 'line-width': props.width },
    });
}

// Re-add the layer after a basemap style swap (setStyle drops custom sources).
function onStyleData() {
    if (boundMap && !boundMap.getSource(sourceId)) addRoute(boundMap);
}

onMounted(async () => {
    const map = await ctx?.ready.value;
    if (!map) return;
    boundMap = map;
    addRoute(map);
    map.on('styledata', onStyleData);
});

onBeforeUnmount(() => {
    if (!boundMap) return;
    boundMap.off('styledata', onStyleData);
    try {
        if (boundMap.getLayer(layerId)) boundMap.removeLayer(layerId);
        if (boundMap.getSource(sourceId)) boundMap.removeSource(sourceId);
    } catch { /* map torn down */ }
    boundMap = null;
});
</script>

<template><span style="display:none" /></template>
