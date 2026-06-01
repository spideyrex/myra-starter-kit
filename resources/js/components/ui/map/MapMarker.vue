<script setup lang="ts">
/**
 * MapMarker — a point marker at [lng, lat]. If default slot content is given,
 * it's rendered into a popup that opens on marker click.
 */
import { inject, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import maplibregl, { type Marker, type Popup } from 'maplibre-gl';
import { MAP_INJECTION_KEY } from './context';

const props = withDefaults(defineProps<{
    lng: number;
    lat: number;
    color?: string;
}>(), {
    color: '#6366f1',
});

const emit = defineEmits<{ click: [] }>();

const ctx = inject(MAP_INJECTION_KEY);
const popupContent = ref<HTMLDivElement | null>(null);
let marker: Marker | null = null;
let popup: Popup | null = null;

onMounted(() => {
    const map = ctx?.map.value;
    if (!map) return;

    marker = new maplibregl.Marker({ color: props.color }).setLngLat([props.lng, props.lat]);

    // Bind a popup only when slot content exists.
    if (popupContent.value && popupContent.value.childElementCount > 0) {
        popup = new maplibregl.Popup({ offset: 24, closeButton: true }).setDOMContent(popupContent.value);
        marker.setPopup(popup);
    }

    marker.getElement().addEventListener('click', () => emit('click'));
    marker.addTo(map);
});

watch(() => [props.lng, props.lat] as [number, number], (next) => marker?.setLngLat(next));

onBeforeUnmount(() => {
    marker?.remove();
    marker = null;
    popup = null;
});
</script>

<template>
    <!-- Rendered off-map; moved into the popup DOM by setDOMContent. -->
    <div style="display:none">
        <div ref="popupContent">
            <slot />
        </div>
    </div>
</template>
