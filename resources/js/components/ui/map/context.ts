import type { InjectionKey, ShallowRef } from 'vue';
import type { Map as MapLibreMap } from 'maplibre-gl';

export interface MapContext {
    /** The MapLibre map instance (null until mounted). */
    map: ShallowRef<MapLibreMap | null>;
    /** Resolves with the instance once the map's `load` event fires. */
    ready: ShallowRef<Promise<MapLibreMap> | null>;
}

export const MAP_INJECTION_KEY: InjectionKey<MapContext> = Symbol('myra-map');
