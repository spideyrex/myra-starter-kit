<script setup lang="ts">
/**
 * MapArc — curved connection lines between point pairs (mapcn-style "arcs",
 * e.g. flight routes from a hub). Rendered MapLibre-native as a GeoJSON line
 * layer; each arc is a quadratic-bezier curve so it bows above the straight
 * line between `from` and `to`.
 *
 * API mirrors mapcn's MapArc:
 *   data        { id, from: [lng,lat], to: [lng,lat] }[]
 *   paint       MapLibre line paint (line-color, line-width, line-dasharray…)
 *   hoverPaint  paint overrides applied to the hovered arc (interactive only)
 *   interactive enable hover highlighting (default true)
 *   curvature   how much the arc bows (0 = straight, default 0.25)
 */
import { inject, onMounted, onBeforeUnmount, useId } from 'vue';
import type { Map as MapLibreMap, MapMouseEvent } from 'maplibre-gl';
import { MAP_INJECTION_KEY } from './context';

type LinePaint = Record<string, unknown>;

const props = withDefaults(defineProps<{
    data: { id: string | number; from: [number, number]; to: [number, number] }[];
    paint?: LinePaint;
    hoverPaint?: LinePaint;
    interactive?: boolean;
    curvature?: number;
}>(), {
    paint: () => ({ 'line-color': '#3b82f6', 'line-width': 2, 'line-dasharray': [2, 2] }),
    hoverPaint: () => ({}),
    interactive: true,
    curvature: 0.25,
});

const emit = defineEmits<{ hover: [id: string | number | null] }>();

const ctx = inject(MAP_INJECTION_KEY);
const uid = useId().replace(/[^a-zA-Z0-9]/g, '');
const sourceId = `arc-${uid}`;
const layerId = `arc-layer-${uid}`;
let boundMap: MapLibreMap | null = null;
let hoveredId: number | null = null;

/** Sample a quadratic bezier arc bowing perpendicular to the from→to vector. */
function buildArc(from: [number, number], to: [number, number], steps = 64): [number, number][] {
    const [x1, y1] = from;
    const [x2, y2] = to;
    const mx = (x1 + x2) / 2;
    const my = (y1 + y2) / 2;
    const dx = x2 - x1;
    const dy = y2 - y1;
    const dist = Math.hypot(dx, dy) || 1;
    // Control point offset perpendicular to the segment.
    const cx = mx + (-dy / dist) * dist * props.curvature;
    const cy = my + (dx / dist) * dist * props.curvature;

    const pts: [number, number][] = [];
    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        const it = 1 - t;
        pts.push([
            it * it * x1 + 2 * it * t * cx + t * t * x2,
            it * it * y1 + 2 * it * t * cy + t * t * y2,
        ]);
    }
    return pts;
}

function featureCollection() {
    return {
        type: 'FeatureCollection' as const,
        features: props.data.map((d, i) => ({
            type: 'Feature' as const,
            id: i,
            properties: { arcId: d.id },
            geometry: { type: 'LineString' as const, coordinates: buildArc(d.from, d.to) },
        })),
    };
}

/** Merge base paint with hover overrides into feature-state case expressions. */
function resolvedPaint(): LinePaint {
    const base: LinePaint = { ...props.paint };
    if (props.interactive && props.hoverPaint) {
        for (const [key, hoverVal] of Object.entries(props.hoverPaint)) {
            const baseVal = props.paint[key];
            if (baseVal === undefined) {
                base[key] = hoverVal;
            } else {
                base[key] = ['case', ['boolean', ['feature-state', 'hover'], false], hoverVal, baseVal];
            }
        }
    }
    return base;
}

function addArcs(map: MapLibreMap) {
    if (map.getLayer(layerId)) map.removeLayer(layerId);
    if (map.getSource(sourceId)) map.removeSource(sourceId);

    map.addSource(sourceId, { type: 'geojson', data: featureCollection() });
    map.addLayer({
        id: layerId,
        type: 'line',
        source: sourceId,
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: resolvedPaint() as never,
    });
}

const onMove = (e: MapMouseEvent) => {
    if (!boundMap) return;
    const f = boundMap.queryRenderedFeatures(e.point, { layers: [layerId] })[0];
    const id = (f?.id as number | undefined) ?? null;
    if (id === hoveredId) return;
    if (hoveredId !== null) boundMap.setFeatureState({ source: sourceId, id: hoveredId }, { hover: false });
    hoveredId = id;
    if (id !== null) boundMap.setFeatureState({ source: sourceId, id }, { hover: true });
    boundMap.getCanvas().style.cursor = id !== null ? 'pointer' : '';
    emit('hover', id !== null ? props.data[id]?.id ?? null : null);
};

const onLeave = () => {
    if (!boundMap) return;
    if (hoveredId !== null) boundMap.setFeatureState({ source: sourceId, id: hoveredId }, { hover: false });
    hoveredId = null;
    boundMap.getCanvas().style.cursor = '';
    emit('hover', null);
};

function onStyleData() {
    if (boundMap && !boundMap.getSource(sourceId)) addArcs(boundMap);
}

onMounted(async () => {
    const map = await ctx?.ready.value;
    if (!map) return;
    boundMap = map;
    addArcs(map);
    map.on('styledata', onStyleData);
    if (props.interactive) {
        map.on('mousemove', layerId, onMove);
        map.on('mouseleave', layerId, onLeave);
    }
});

onBeforeUnmount(() => {
    if (!boundMap) return;
    boundMap.off('styledata', onStyleData);
    boundMap.off('mousemove', layerId, onMove);
    boundMap.off('mouseleave', layerId, onLeave);
    try {
        if (boundMap.getLayer(layerId)) boundMap.removeLayer(layerId);
        if (boundMap.getSource(sourceId)) boundMap.removeSource(sourceId);
    } catch { /* map torn down */ }
    boundMap = null;
});
</script>

<template><span style="display:none" /></template>
