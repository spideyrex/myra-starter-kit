<script setup lang="ts">
/** Map controls: zoom/compass, geolocate, fullscreen, and scale. */
import { inject, onMounted, onBeforeUnmount } from 'vue';
import maplibregl, { type IControl } from 'maplibre-gl';
import { MAP_INJECTION_KEY } from './context';

const props = withDefaults(defineProps<{
    navigation?: boolean;
    geolocate?: boolean;
    fullscreen?: boolean;
    scale?: boolean;
    position?: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';
}>(), {
    navigation: true,
    geolocate: true,
    fullscreen: true,
    scale: true,
    position: 'top-right',
});

const ctx = inject(MAP_INJECTION_KEY);
const controls: IControl[] = [];

onMounted(() => {
    const map = ctx?.map.value;
    if (!map) return;

    if (props.navigation) controls.push(new maplibregl.NavigationControl({ visualizePitch: true }));
    if (props.geolocate) {
        controls.push(new maplibregl.GeolocateControl({
            positionOptions: { enableHighAccuracy: true },
            trackUserLocation: true,
        }));
    }
    if (props.fullscreen) controls.push(new maplibregl.FullscreenControl());
    if (props.scale) controls.push(new maplibregl.ScaleControl({ unit: 'metric' }));

    for (const c of controls) {
        map.addControl(c, c instanceof maplibregl.ScaleControl ? 'bottom-left' : props.position);
    }
});

onBeforeUnmount(() => {
    const map = ctx?.map.value;
    if (!map) return;
    for (const c of controls) {
        try { map.removeControl(c); } catch { /* map already torn down */ }
    }
    controls.length = 0;
});
</script>

<template><span style="display:none" /></template>
