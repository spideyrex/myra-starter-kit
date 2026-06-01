<script setup lang="ts">
/**
 * Map — shadcn-vue style wrapper over MapLibre GL JS (mapcn philosophy, Vue).
 *
 * Provides the map instance to child components (MapMarker, MapControls,
 * MapRoute, MapCluster) via provide/inject. Theme-aware: picks a CARTO light/
 * dark basemap from the document `dark` class and swaps it live when dark mode
 * toggles (same mechanism as useThemeColors).
 *
 * Coordinate order is [lng, lat] everywhere (MapLibre/GeoJSON).
 *
 * NOTE: CARTO basemaps are free for non-commercial use only. For production /
 * commercial use, pass a custom `map-style` (MapTiler, Protomaps, Stadia, or
 * self-hosted tiles).
 */
import { onMounted, onBeforeUnmount, provide, ref, shallowRef } from 'vue';
import maplibregl, { type Map as MapLibreMap } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { MAP_INJECTION_KEY } from './context';

const props = withDefaults(defineProps<{
    center?: [number, number];
    zoom?: number;
    mapStyle?: string | null;
}>(), {
    center: () => [101.6869, 3.139], // Kuala Lumpur
    zoom: 11,
    mapStyle: null,
});

const emit = defineEmits<{ ready: [MapLibreMap] }>();

const CARTO_LIGHT = 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json';
const CARTO_DARK = 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json';

const container = ref<HTMLDivElement | null>(null);
const map = shallowRef<MapLibreMap | null>(null);
const ready = shallowRef<Promise<MapLibreMap> | null>(null);
let resolveReady: (m: MapLibreMap) => void;
let observer: MutationObserver | null = null;

function isDark(): boolean {
    return document.documentElement.classList.contains('dark');
}

function resolveStyle(): string {
    if (props.mapStyle) return props.mapStyle;
    return isDark() ? CARTO_DARK : CARTO_LIGHT;
}

// Share the instance + a ready promise with descendants.
ready.value = new Promise<MapLibreMap>((resolve) => { resolveReady = resolve; });
provide(MAP_INJECTION_KEY, { map, ready });

onMounted(() => {
    if (!container.value) return;

    const instance = new maplibregl.Map({
        container: container.value,
        style: resolveStyle(),
        center: props.center,
        zoom: props.zoom,
        attributionControl: { compact: true },
    });

    map.value = instance;
    instance.on('load', () => {
        resolveReady(instance);
        emit('ready', instance);
    });

    // Swap basemap when the app toggles dark mode (only when not pinned via map-style).
    if (!props.mapStyle) {
        observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.attributeName === 'class') {
                    instance.setStyle(resolveStyle());
                }
            }
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
    map.value?.remove();
    map.value = null;
});
</script>

<template>
    <div class="relative size-full">
        <div ref="container" class="size-full" />
        <slot />
    </div>
</template>

<style>
/* Blend MapLibre popups/controls with the shadcn theme. */
.maplibregl-popup-content {
    background: hsl(var(--background, 0 0% 100%));
    color: inherit;
    border-radius: var(--radius, 0.5rem);
    box-shadow: 0 4px 12px rgb(0 0 0 / 0.15);
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
}
.dark .maplibregl-popup-content {
    background: #18181b;
    color: #fafafa;
}
.maplibregl-popup-close-button {
    font-size: 1rem;
    padding: 0 0.35rem;
}
</style>
