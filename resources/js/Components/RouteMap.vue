<script setup lang="ts">
import type { RoutePoint } from '@/types/routes';
import L, { type LatLngExpression, type Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        points: RoutePoint[];
        editable?: boolean;
        showIntermediateMarkers?: boolean;
    }>(),
    {
        editable: false,
        showIntermediateMarkers: false,
    },
);

const emit = defineEmits<{
    'update:points': [points: RoutePoint[]];
}>();

const mapElement = ref<HTMLElement | null>(null);
let map: LeafletMap | null = null;
let line: L.Polyline | null = null;
let markers: L.CircleMarker[] = [];
let userMarker: L.CircleMarker | null = null;
let accuracyCircle: L.Circle | null = null;
let hasFittedRoute = false;
const shouldFitInitialEditableRoute = props.editable && props.points.length > 0;

const center = computed<LatLngExpression>(() => {
    if (props.points.length > 0) {
        return [props.points[0].lat, props.points[0].lng];
    }

    return [55.751244, 37.618423];
});

onMounted(async () => {
    await nextTick();

    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: true,
    }).setView(center.value, props.points.length > 0 ? 13 : 11);

    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    });

    const hot = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors, HOT',
        maxZoom: 19,
    });

    const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenTopoMap contributors',
        maxZoom: 17,
    });

    const cartoNoLabels = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        maxZoom: 19,
    });

    const imagery = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 19,
        },
    );
    const hybridLabels = L.tileLayer(
        'https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: 'Labels &copy; Esri',
            maxZoom: 19,
        },
    );
    const hybridTransportation = L.tileLayer(
        'https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: 'Roads &copy; Esri',
            maxZoom: 19,
        },
    );
    const hybrid = L.layerGroup([imagery, hybridTransportation, hybridLabels]);

    osm.addTo(map);
    L.control.layers({
        OSM: osm,
        'OSM HOT': hot,
        'Топо': topo,
        'Скриншот': cartoNoLabels,
        'Спутник': imagery,
        'Спутник гибрид': hybrid,
    }).addTo(map);
    addGeolocationControl();

    if (props.editable) {
        map.on('click', (event) => {
            emit('update:points', [
                ...props.points,
                {
                    lat: Number(event.latlng.lat.toFixed(7)),
                    lng: Number(event.latlng.lng.toFixed(7)),
                },
            ]);
        });
    }

    map.on('locationfound', (event) => {
        userMarker?.remove();
        accuracyCircle?.remove();

        userMarker = L.circleMarker(event.latlng, {
            radius: 7,
            color: '#ffffff',
            fillColor: '#2563eb',
            fillOpacity: 1,
            weight: 2,
        }).addTo(map!);

        accuracyCircle = L.circle(event.latlng, {
            radius: event.accuracy,
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 0.12,
            weight: 1,
        }).addTo(map!);
    });

    map.on('locationerror', (event) => {
        window.alert(event.message || 'Не удалось определить местоположение.');
    });

    renderRoute();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});

watch(
    () => props.points,
    () => renderRoute(),
    { deep: true },
);

const renderRoute = () => {
    if (!map) {
        return;
    }

    line?.remove();
    markers.forEach((marker) => marker.remove());
    markers = [];

    const latLngs = props.points.map((point) => [point.lat, point.lng] as LatLngExpression);

    line = L.polyline(latLngs, {
        color: '#2563eb',
        weight: 5,
        opacity: 0.9,
    }).addTo(map);

    props.points.forEach((point, index) => {
        const isEndpoint = index === 0 || index === props.points.length - 1;

        if (!props.showIntermediateMarkers && !isEndpoint) {
            return;
        }

        const marker = L.circleMarker([point.lat, point.lng], {
            radius: isEndpoint ? 7 : 5,
            color: '#ffffff',
            fillColor: index === 0 ? '#16a34a' : index === props.points.length - 1 ? '#dc2626' : '#2563eb',
            fillOpacity: 1,
            weight: 2,
        }).addTo(map!);

        markers.push(marker);
    });

    if (
        latLngs.length > 0
        && (!props.editable || (shouldFitInitialEditableRoute && !hasFittedRoute))
    ) {
        map.fitBounds(L.latLngBounds(latLngs), { padding: [32, 32], maxZoom: 15 });
        hasFittedRoute = true;
    }
};

const addGeolocationControl = () => {
    if (!map) {
        return;
    }

    const LocateControl = L.Control.extend({
        onAdd(controlMap: LeafletMap) {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control route-map-locate-control');
            const button = L.DomUtil.create('button', 'route-map-locate', container);
            button.type = 'button';
            button.title = 'Показать мое местоположение';
            button.setAttribute('aria-label', 'Показать мое местоположение');
            button.innerHTML = `
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3v3m0 12v3M3 12h3m12 0h3" />
                    <circle cx="12" cy="12" r="5" />
                    <circle cx="12" cy="12" r="1.5" />
                </svg>
            `;

            L.DomEvent.disableClickPropagation(container);
            L.DomEvent.on(button, 'click', () => {
                controlMap.locate({
                    setView: true,
                    maxZoom: 16,
                    enableHighAccuracy: true,
                    timeout: 10000,
                });
            });

            return container;
        },
    });

    new LocateControl({ position: 'topleft' }).addTo(map);
};
</script>

<template>
    <div
        ref="mapElement"
        class="h-[440px] w-full overflow-hidden rounded border border-gray-200 bg-gray-100"
    />
</template>

<style scoped>
:global(.route-map-locate-control) {
    border: 1px solid rgba(17, 24, 39, 0.18);
    border-radius: 4px;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.18);
    overflow: hidden;
}

:global(.route-map-locate) {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    cursor: pointer;
    background: #ffffff;
    color: #1f2937;
    border: 0;
    padding: 0;
}

:global(.route-map-locate svg) {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

:global(.route-map-locate:hover) {
    background: #f3f4f6;
    color: #0f766e;
}
</style>
