<script setup lang="ts">
import type { RoutePoint } from '@/types/routes';
import { pointSegment, routeSegments } from '@/utils/routeMetrics';
import L, { type LatLngExpression, type Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        points: RoutePoint[];
        editable?: boolean;
        fullscreen?: boolean;
        insertMode?: boolean;
        pointSelectMode?: boolean;
        selectedPointIndex?: number | null;
        showIntermediateMarkers?: boolean;
        startNextSegment?: boolean;
    }>(),
    {
        editable: false,
        fullscreen: false,
        insertMode: false,
        pointSelectMode: false,
        selectedPointIndex: null,
        showIntermediateMarkers: false,
        startNextSegment: false,
    },
);

const emit = defineEmits<{
    'point-inserted': [];
    'point-selected': [index: number | null];
    'update:fullscreen': [fullscreen: boolean];
    'update:points': [points: RoutePoint[]];
    'segment-started': [];
}>();

const MAX_ZOOM = 22;
const VIEWPORT_PADDING = 0.35;
type MapLayer = 'scheme' | 'screenshot' | 'hybrid';

const mapElement = ref<HTMLElement | null>(null);
const activeLayer = ref<MapLayer>('scheme');
const locating = ref(false);
let map: LeafletMap | null = null;
let mapLayers: Record<MapLayer, L.Layer> | null = null;
let lines: L.Polyline[] = [];
let insertLines: L.Polyline[] = [];
let markers: L.Layer[] = [];
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
        maxZoom: MAX_ZOOM,
        zoomControl: false,
    }).setView(center.value, props.points.length > 0 ? 13 : 11);

    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxNativeZoom: 19,
        maxZoom: MAX_ZOOM,
    });

    const cartoNoLabels = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        maxNativeZoom: 19,
        maxZoom: MAX_ZOOM,
    });

    const imagery = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: 'Tiles &copy; Esri',
            maxNativeZoom: 19,
            maxZoom: MAX_ZOOM,
        },
    );
    const hybridLabels = L.tileLayer(
        'https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: 'Labels &copy; Esri',
            maxNativeZoom: 19,
            maxZoom: MAX_ZOOM,
        },
    );
    const hybridTransportation = L.tileLayer(
        'https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Transportation/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: 'Roads &copy; Esri',
            maxNativeZoom: 19,
            maxZoom: MAX_ZOOM,
        },
    );
    const hybrid = L.layerGroup([imagery, hybridTransportation, hybridLabels]);
    mapLayers = {
        scheme: osm,
        screenshot: cartoNoLabels,
        hybrid,
    };

    mapLayers[activeLayer.value].addTo(map);

    if (props.editable) {
        map.on('click', (event) => {
            if (props.insertMode) {
                return;
            }

            const lastPoint = props.points.at(-1);
            const segment = (lastPoint ? pointSegment(lastPoint) : 0) + (props.startNextSegment ? 1 : 0);

            emit('update:points', [
                ...props.points,
                {
                    lat: Number(event.latlng.lat.toFixed(7)),
                    lng: Number(event.latlng.lng.toFixed(7)),
                    segment,
                },
            ]);

            if (props.startNextSegment) {
                emit('segment-started');
            }
        });
    }

    map.on('moveend zoomend', () => renderViewportRouteLayers());

    map.on('locationfound', (event) => {
        locating.value = false;
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
        locating.value = false;
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

watch(
    () => props.insertMode,
    () => renderRoute(),
);

watch(
    () => props.selectedPointIndex,
    () => renderRoute(),
);

watch(
    () => props.fullscreen,
    async () => {
        await nextTick();
        window.setTimeout(() => map?.invalidateSize(false), 80);
    },
);

const setMapLayer = (layer: MapLayer) => {
    if (!map || !mapLayers || activeLayer.value === layer) {
        return;
    }

    map.removeLayer(mapLayers[activeLayer.value]);
    activeLayer.value = layer;
    mapLayers[layer].addTo(map);
};

const zoomIn = () => {
    map?.zoomIn();
};

const zoomOut = () => {
    map?.zoomOut();
};

const locateUser = () => {
    if (!map || locating.value) {
        return;
    }

    locating.value = true;
    map.locate({
        setView: true,
        maxZoom: 16,
        enableHighAccuracy: true,
        timeout: 10000,
    });
};

const toggleFullscreen = () => {
    emit('update:fullscreen', !props.fullscreen);
};

const renderRoute = () => {
    if (!map) {
        return;
    }

    lines.forEach((line) => line.remove());
    lines = [];
    clearViewportRouteLayers();

    const latLngs = props.points.map((point) => [point.lat, point.lng] as LatLngExpression);

    routeSegments(props.points).forEach((segment) => {
        const segmentLatLngs = segment.map((point) => [point.lat, point.lng] as LatLngExpression);

        if (segmentLatLngs.length < 2) {
            return;
        }

        lines.push(L.polyline(segmentLatLngs, {
            color: '#2563eb',
            weight: 5,
            opacity: 0.9,
        }).addTo(map!));
    });

    if (props.editable && props.insertMode) {
        renderInsertLines();
    }

    renderPointMarkers();

    if (
        latLngs.length > 0
        && (!props.editable || (shouldFitInitialEditableRoute && !hasFittedRoute))
    ) {
        map.fitBounds(L.latLngBounds(latLngs), { padding: [32, 32], maxZoom: 15 });
        hasFittedRoute = true;
        renderViewportRouteLayers();
    }
};

const renderViewportRouteLayers = () => {
    if (!map) {
        return;
    }

    clearViewportRouteLayers();

    if (props.editable && props.insertMode) {
        renderInsertLines();
    }

    renderPointMarkers();
};

const clearViewportRouteLayers = () => {
    insertLines.forEach((line) => line.remove());
    insertLines = [];
    markers.forEach((marker) => marker.remove());
    markers = [];
};

const renderPointMarkers = () => {
    props.points.forEach((point, index) => {
        const isEndpoint = index === 0 || index === props.points.length - 1;

        if (!props.showIntermediateMarkers && !isEndpoint) {
            return;
        }

        if (!shouldRenderPoint(point, index, isEndpoint)) {
            return;
        }

        const marker = props.editable
            ? editablePointMarker(point, index, isEndpoint)
            : readonlyPointMarker(point, index, isEndpoint);

        markers.push(marker);
    });
};

const shouldRenderPoint = (point: RoutePoint, index: number, isEndpoint: boolean): boolean => {
    if (!map || isEndpoint || props.selectedPointIndex === index) {
        return true;
    }

    return map.getBounds().pad(VIEWPORT_PADDING).contains([point.lat, point.lng]);
};

const renderInsertLines = () => {
    const visibleBounds = map!.getBounds().pad(VIEWPORT_PADDING);

    props.points.forEach((point, index) => {
        if (index === 0) {
            return;
        }

        const previous = props.points[index - 1];

        if (pointSegment(previous) !== pointSegment(point)) {
            return;
        }

        if (!visibleBounds.intersects(L.latLngBounds([
            [previous.lat, previous.lng],
            [point.lat, point.lng],
        ]))) {
            return;
        }

        const insertLine = L.polyline(
            [
                [previous.lat, previous.lng],
                [point.lat, point.lng],
            ],
            {
                color: '#2563eb',
                opacity: 0,
                weight: 24,
                interactive: true,
            },
        ).addTo(map!);

        insertLine.on('click', (event) => {
            L.DomEvent.stop(event);
            const insertedPoint: RoutePoint = {
                lat: Number(event.latlng.lat.toFixed(7)),
                lng: Number(event.latlng.lng.toFixed(7)),
                segment: pointSegment(previous),
            };

            emit('update:points', [
                ...props.points.slice(0, index),
                insertedPoint,
                ...props.points.slice(index),
            ]);
            emit('point-inserted');
            emit('point-selected', index);
        });

        insertLines.push(insertLine);
    });
};

const readonlyPointMarker = (point: RoutePoint, index: number, isEndpoint: boolean): L.CircleMarker => L.circleMarker([point.lat, point.lng], {
    radius: isEndpoint ? 7 : 5,
    color: '#ffffff',
    fillColor: pointColor(index),
    fillOpacity: 1,
    weight: 2,
}).addTo(map!);

const editablePointMarker = (point: RoutePoint, index: number, isEndpoint: boolean): L.Marker => {
    const marker = L.marker([point.lat, point.lng], {
        bubblingMouseEvents: false,
        draggable: true,
        icon: pointIcon(pointColor(index), isEndpoint ? 14 : 11, props.selectedPointIndex === index),
        keyboard: false,
        title: `Точка ${index + 1}`,
    }).addTo(map!);

    marker.on('click', () => {
        if (props.pointSelectMode) {
            emit('point-selected', props.selectedPointIndex === index ? null : index);
            return;
        }

        if (props.insertMode) {
            return;
        }

        const lastPoint = props.points.at(-1);
        const segment = (lastPoint ? pointSegment(lastPoint) : 0) + (props.startNextSegment ? 1 : 0);

        emit('update:points', [
            ...props.points,
            {
                lat: point.lat,
                lng: point.lng,
                segment,
            },
        ]);

        if (props.startNextSegment) {
            emit('segment-started');
        }
    });

    marker.on('dragend', () => {
        const latLng = marker.getLatLng();
        const nextPoints = props.points.map((currentPoint, pointIndex) => {
            if (pointIndex !== index) {
                return currentPoint;
            }

            return {
                ...currentPoint,
                ele: null,
                lat: Number(latLng.lat.toFixed(7)),
                lng: Number(latLng.lng.toFixed(7)),
            };
        });

        emit('update:points', nextPoints);
        emit('point-selected', index);
    });

    return marker;
};

const pointColor = (index: number): string => {
    if (index === 0) {
        return '#16a34a';
    }

    if (index === props.points.length - 1) {
        return '#dc2626';
    }

    return '#2563eb';
};

const pointIcon = (color: string, size: number, selected: boolean): L.DivIcon => L.divIcon({
    className: 'route-map-point-icon',
    html: `<span style="--point-color:${color};--point-size:${size}px" class="${selected ? 'is-selected' : ''}"></span>`,
    iconAnchor: [size / 2, size / 2],
    iconSize: [size, size],
});

</script>

<template>
    <div
        class="relative h-full min-h-[440px] w-full overflow-hidden rounded border border-gray-200 bg-gray-100"
        :class="{ 'route-map-fullscreen': fullscreen && !editable }"
    >
        <div ref="mapElement" class="absolute inset-0" />

        <div class="absolute left-3 top-3 z-[1000] flex flex-col gap-2">
            <button type="button" class="route-map-control-button" title="Приблизить" aria-label="Приблизить" @click.stop="zoomIn">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
            </button>
            <button type="button" class="route-map-control-button" title="Отдалить" aria-label="Отдалить" @click.stop="zoomOut">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 12h14" />
                </svg>
            </button>
            <button
                type="button"
                class="route-map-control-button"
                :class="{ 'is-loading': locating }"
                :disabled="locating"
                title="Показать мое местоположение"
                aria-label="Показать мое местоположение"
                @click.stop="locateUser"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3v3m0 12v3M3 12h3m12 0h3" />
                    <circle cx="12" cy="12" r="5" />
                    <circle cx="12" cy="12" r="1.5" />
                </svg>
            </button>
            <button
                type="button"
                class="route-map-control-button"
                :title="fullscreen ? 'Выйти из полноэкранного режима' : 'Открыть карту на весь экран'"
                :aria-label="fullscreen ? 'Выйти из полноэкранного режима' : 'Открыть карту на весь экран'"
                @click.stop="toggleFullscreen"
            >
                <svg v-if="fullscreen" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 4v5H4" />
                    <path d="M15 4v5h5" />
                    <path d="M9 20v-5H4" />
                    <path d="M15 20v-5h5" />
                </svg>
                <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 9V4h5" />
                    <path d="M20 9V4h-5" />
                    <path d="M4 15v5h5" />
                    <path d="M20 15v5h-5" />
                </svg>
            </button>
        </div>

        <div class="absolute right-3 top-3 z-[1000] flex flex-col items-end gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
            <button type="button" class="route-map-layer-button" :class="{ 'is-active': activeLayer === 'scheme' }" @click.stop="setMapLayer('scheme')">
                Схема
            </button>
            <button type="button" class="route-map-layer-button" :class="{ 'is-active': activeLayer === 'screenshot' }" @click.stop="setMapLayer('screenshot')">
                Скриншот
            </button>
            <button type="button" class="route-map-layer-button" :class="{ 'is-active': activeLayer === 'hybrid' }" @click.stop="setMapLayer('hybrid')">
                Спутник
            </button>
        </div>
    </div>
</template>

<style scoped>
.route-map-control-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    cursor: pointer;
    background: #ffffff;
    color: #111827;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
    padding: 0;
}

.route-map-fullscreen {
    position: fixed;
    inset: 0;
    z-index: 9000;
    width: 100vw;
    height: 100dvh;
    min-height: 0;
    border-radius: 0;
}

.route-map-control-button svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.route-map-control-button:hover:not(:disabled),
.route-map-control-button.is-loading {
    color: #0f766e;
    background: #ecfdf5;
    border-color: #0f766e;
}

.route-map-control-button.is-loading svg {
    animation: route-map-spin 1s linear infinite;
}

.route-map-control-button:disabled {
    cursor: wait;
}

.route-map-layer-button {
    min-height: 34px;
    padding: 0 12px;
    color: #111827;
    font-size: 12px;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
}

.route-map-layer-button:hover,
.route-map-layer-button.is-active {
    color: #0f766e;
    background: #ecfdf5;
    border-color: #0f766e;
}

@keyframes route-map-spin {
    to {
        transform: rotate(360deg);
    }
}

:global(.route-map-point-icon) {
    background: transparent;
    border: 0;
}

:global(.route-map-point-icon span) {
    display: block;
    width: var(--point-size);
    height: var(--point-size);
    box-sizing: border-box;
    cursor: grab;
    background: var(--point-color);
    border: 2px solid #ffffff;
    border-radius: 9999px;
    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.28);
}

:global(.route-map-point-icon span.is-selected) {
    outline: 3px solid rgba(37, 99, 235, 0.35);
    outline-offset: 2px;
}

:global(.route-map-point-icon span:active) {
    cursor: grabbing;
}
</style>
