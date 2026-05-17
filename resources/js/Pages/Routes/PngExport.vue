<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { TrackRoute } from '@/types/routes';
import { formatDistance, routeSegments } from '@/utils/routeMetrics';
import { Head, Link } from '@inertiajs/vue3';
import * as htmlToImage from 'html-to-image';
import L, { type LatLngExpression, type Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    routeModel: TrackRoute;
}>();

const MAX_ZOOM = 22;
const mapElement = ref<HTMLElement | null>(null);
const previewShell = ref<HTMLElement | null>(null);
const shotElement = ref<HTMLElement | null>(null);
const stageElement = ref<HTMLElement | null>(null);
const hiddenExportRoot = ref<HTMLElement | null>(null);
const routeColor = ref('#1f4d3a');
const lineWeight = ref(14);
const distanceKm = ref((props.routeModel.distance_m / 1000).toFixed(2));
const showTime = ref(props.routeModel.duration_minutes !== null);
const showElevation = ref(props.routeModel.elevation_gain_m > 0);
const showActivityIcon = ref(true);
const isExporting = ref(false);

let map: LeafletMap | null = null;
let routeLines: L.Polyline[] = [];

const latLngs = computed<LatLngExpression[]>(() =>
    props.routeModel.points.map((point) => [point.lat, point.lng] as LatLngExpression),
);

const previewDistance = computed(() => formatDistance(props.routeModel.distance_m));
const formattedDuration = computed(() => {
    if (!props.routeModel.duration_minutes) {
        return '';
    }

    const hours = Math.floor(props.routeModel.duration_minutes / 60).toString().padStart(2, '0');
    const minutes = (props.routeModel.duration_minutes % 60).toString().padStart(2, '0');

    return `${hours}:${minutes}`;
});
const elevationMeters = computed(() => Math.round(props.routeModel.elevation_gain_m).toString());

onMounted(async () => {
    await nextTick();
    initMap();
    updateScale();
    window.addEventListener('resize', updateScale);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateScale);
    map?.remove();
    map = null;
});

watch([routeColor, lineWeight], () => {
    routeLines.forEach((routeLine) => routeLine.setStyle({
        color: routeColor.value,
        weight: lineWeight.value,
    }));
});

const initMap = () => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: false,
        maxZoom: MAX_ZOOM,
        wheelPxPerZoomLevel: 60,
        zoomSnap: 0.25,
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        crossOrigin: true,
        maxNativeZoom: 19,
        maxZoom: MAX_ZOOM,
    }).addTo(map);

    routeLines = drawRouteLines(map);

    fitTrack();
    setTimeout(() => map?.invalidateSize(true), 80);
};

const fitTrack = () => {
    if (!map || latLngs.value.length === 0) {
        return;
    }

    const pad = Math.max(24, lineWeight.value * 2 + 12);
    map.invalidateSize(true);
    map.fitBounds(L.latLngBounds(latLngs.value), {
        padding: [pad, pad],
    });
};

const updateScale = () => {
    if (!stageElement.value || !previewShell.value || !shotElement.value) {
        return;
    }

    const width = stageElement.value.clientWidth;
    const height = stageElement.value.clientHeight;
    const scale = Math.max(0.1, Math.min(width, height) / 1024);
    const previewSize = `${1024 * scale}px`;

    previewShell.value.style.width = previewSize;
    previewShell.value.style.height = previewSize;
    shotElement.value.style.transform = `scale(${scale})`;
    map?.invalidateSize(false);
};

const exportPng = async () => {
    if (!hiddenExportRoot.value) {
        return;
    }

    isExporting.value = true;

    try {
        const exportNode = await createExportNode();
        const dataUrl = await htmlToImage.toPng(exportNode, {
            backgroundColor: 'transparent',
            cacheBust: true,
            height: 1024,
            pixelRatio: 1,
            width: 1024,
        });

        const filename = (props.routeModel.title || 'route')
            .replace(/[\\/:*?"<>|]+/g, '-')
            .trim();
        const link = document.createElement('a');
        link.download = `${filename || 'route'}.png`;
        link.href = dataUrl;
        link.click();
    } finally {
        hiddenExportRoot.value.innerHTML = '';
        isExporting.value = false;
    }
};

const createExportNode = async (): Promise<HTMLElement> => {
    if (!hiddenExportRoot.value) {
        throw new Error('Export root is not ready.');
    }

    hiddenExportRoot.value.innerHTML = '';

    const shot = document.createElement('div');
    shot.className = 'png-shot export-shot';
    shot.style.cssText = [
        'position:relative',
        'width:1024px',
        'height:1024px',
        'overflow:hidden',
        'border-radius:18px',
        'background:#f5f7f8',
        'box-shadow:0 0 0 1px #dfe6ea inset',
        'font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,Cantarell,"Noto Sans",sans-serif',
    ].join(';');

    const mapNode = document.createElement('div');
    mapNode.style.cssText = 'position:absolute;inset:0;width:1024px;height:1024px;';
    shot.appendChild(mapNode);

    if (distanceKm.value) {
        shot.appendChild(createMetricNode('tl', Number(distanceKm.value).toFixed(2), 'КМ'));
    }

    if (showElevation.value) {
        shot.appendChild(createMetricNode('tr', `+${elevationMeters.value}`, 'набор высоты, м'));
    }

    if (showTime.value && formattedDuration.value) {
        shot.appendChild(createMetricNode('br', formattedDuration.value, 'ВРЕМЯ'));
    }

    if (showActivityIcon.value) {
        shot.appendChild(createActivityIconNode());
    }

    hiddenExportRoot.value.appendChild(shot);

    const exportMap = L.map(mapNode, {
        attributionControl: false,
        zoomControl: false,
        maxZoom: MAX_ZOOM,
        wheelPxPerZoomLevel: 60,
        zoomSnap: 0.25,
    });

    const tiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        crossOrigin: true,
        maxNativeZoom: 19,
        maxZoom: MAX_ZOOM,
    }).addTo(exportMap);

    await nextTick();
    exportMap.invalidateSize(true);

    if (map) {
        exportMap.setView(map.getCenter(), map.getZoom(), { animate: false });
    } else {
        const pad = Math.max(24, lineWeight.value * 2 + 12);
        exportMap.fitBounds(L.latLngBounds(latLngs.value), { padding: [pad, pad] });
    }

    drawRouteLines(exportMap);

    await waitForTiles(tiles);

    return shot;
};

const drawRouteLines = (targetMap: LeafletMap): L.Polyline[] => routeSegments(props.routeModel.points)
    .map((segment) => segment.map((point) => [point.lat, point.lng] as LatLngExpression))
    .filter((segmentLatLngs) => segmentLatLngs.length >= 2)
    .map((segmentLatLngs) => L.polyline(segmentLatLngs, {
        color: routeColor.value,
        lineCap: 'round',
        lineJoin: 'round',
        opacity: 1,
        weight: lineWeight.value,
    }).addTo(targetMap));

const createMetricNode = (position: 'tl' | 'tr' | 'br', value: string, label: string): HTMLElement => {
    const wrapper = document.createElement('div');
    wrapper.style.cssText = [
        'position:absolute',
        'z-index:500',
        'color:' + routeColor.value,
        metricPositionStyle(position),
    ].join(';');

    const metric = document.createElement('div');
    metric.className = 'metric';
    metric.style.cssText = [
        'font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,Cantarell,"Noto Sans",sans-serif',
        'font-size:64px',
        'font-weight:800',
        'letter-spacing:1px',
        'line-height:1',
    ].join(';');
    metric.textContent = value;

    const labelNode = document.createElement('div');
    labelNode.className = 'label';
    labelNode.style.cssText = [
        'font-family:system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,Cantarell,"Noto Sans",sans-serif',
        'margin-top:4px',
        `font-size:${label.length > 3 ? 18 : 20}px`,
        'font-weight:500',
        'opacity:0.9',
    ].join(';');
    labelNode.textContent = label;

    wrapper.append(metric, labelNode);

    return wrapper;
};

const metricPositionStyle = (position: 'tl' | 'tr' | 'br'): string => {
    if (position === 'tl') {
        return 'left:24px;top:24px';
    }

    if (position === 'tr') {
        return 'right:24px;top:24px;text-align:right';
    }

    return 'right:24px;bottom:24px;text-align:right';
};

const createActivityIconNode = (): HTMLElement => {
    const wrapper = document.createElement('div');
    wrapper.style.cssText = [
        'position:absolute',
        'left:24px',
        'bottom:24px',
        'z-index:500',
        'width:112px',
        'height:112px',
        'color:' + routeColor.value,
    ].join(';');
    wrapper.innerHTML = activityIconSvg(props.routeModel.activity_type);

    return wrapper;
};

const activityIconSvg = (activity: string): string => {
    const common = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.35" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="width:100%;height:100%;display:block"';

    if (activity === 'run') {
        return `<svg ${common}><path d="M11.007 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M4 17l5 1l.75 -1.5" /><path d="M15 21v-4l-4 -3l1 -6" /><path d="M7 12v-3l5 -1l3 3l3 1" /></svg>`;
    }

    if (activity === 'skateboard') {
        return `<svg ${common}><path d="M5.5 15h3.5l.75 -1.5" /><path d="M14 19v-5l-2.5 -3l2.5 -4" /><path d="M8 8l3 -1h4l1 3h3" /><path d="M17.5 21a.5 .5 0 1 0 0 -1a.5 .5 0 0 0 0 1" /><path d="M3 18c0 .552 .895 1 2 1h14c1.105 0 2 -.448 2 -1" /><path d="M6.5 21a.5 .5 0 1 0 0 -1a.5 .5 0 0 0 0 1" /><path d="M14.007 4a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /></svg>`;
    }

    if (activity === 'car') {
        return `<svg ${common}><path d="M5 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M15 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5 17h-2v-6l2 -5h9l4 5h1a2 2 0 0 1 2 2v4h-2m-4 0h-6m-6 -6h15m-6 0v-5" /></svg>`;
    }

    return `<svg ${common}><path d="M12 4a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M7 21l3 -4" /><path d="M16 21l-2 -4l-3 -3l1 -6" /><path d="M6 12l2 -3l4 -1l3 3l3 1" /></svg>`;
};

const waitForTiles = (tiles: L.TileLayer): Promise<void> => new Promise((resolve) => {
    let resolved = false;
    const finish = () => {
        if (resolved) {
            return;
        }

        resolved = true;
        window.setTimeout(resolve, 120);
    };

    tiles.once('load', finish);
    window.setTimeout(finish, 2500);
});
</script>

<template>
    <Head :title="`PNG · ${routeModel.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">PNG-экспорт</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ routeModel.title }} · {{ previewDistance }}</p>
                </div>
                <Link :href="route('routes.show', routeModel.id)" class="text-sm font-medium text-teal-700 hover:text-teal-900">
                    К маршруту
                </Link>
            </div>
        </template>

        <main class="mx-auto grid w-full max-w-7xl gap-4 p-3 sm:p-4 lg:grid-cols-[320px_minmax(0,1fr)] lg:items-start">
            <aside class="rounded border border-gray-200 bg-slate-200 p-4 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Параметры</h2>

                <div class="mt-4">
                    <div>
                        <label for="distanceKm" class="block text-xs font-medium text-gray-700">Дистанция, км</label>
                        <input
                            id="distanceKm"
                            v-model="distanceKm"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-slate-50 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        />
                    </div>
                </div>

                <div class="mt-4 space-y-3 text-sm text-gray-700">
                    <label class="flex items-center gap-3">
                        <input v-model="showTime" type="checkbox" class="rounded border-gray-300 text-teal-700 focus:ring-teal-600" :disabled="!formattedDuration" />
                        <span>Показать время{{ formattedDuration ? ` (${formattedDuration})` : ' (не указано)' }}</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input v-model="showElevation" type="checkbox" class="rounded border-gray-300 text-teal-700 focus:ring-teal-600" />
                        <span>Показать набор высоты (+{{ elevationMeters }} м)</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input v-model="showActivityIcon" type="checkbox" class="rounded border-gray-300 text-teal-700 focus:ring-teal-600" />
                        <span>Показать иконку активности</span>
                    </label>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <label for="routeColor" class="block text-xs font-medium text-gray-700">Цвет</label>
                        <input id="routeColor" v-model="routeColor" type="color" class="mt-1 h-10 w-full cursor-pointer rounded border-0 bg-transparent p-0" />
                    </div>
                    <div>
                        <label for="lineWeight" class="block text-xs font-medium text-gray-700">Толщина</label>
                        <input id="lineWeight" v-model="lineWeight" type="range" min="1" max="32" step="1" class="mt-3 w-full" />
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <PrimaryButton type="button" :disabled="isExporting" @click="exportPng">
                        Скачать PNG
                    </PrimaryButton>
                    <SecondaryButton type="button" @click="fitTrack">
                        Вместить трек
                    </SecondaryButton>
                </div>

                <p class="mt-4 text-xs leading-5 text-gray-600">
                    Карта: CARTO Positron без подписей, данные OpenStreetMap. Холст экспорта 1024×1024.
                </p>
            </aside>

            <section ref="stageElement" class="flex aspect-square w-full items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-100 lg:min-h-[calc(100vh-160px)]">
                <div ref="previewShell" class="relative shrink-0">
                    <div
                        ref="shotElement"
                        class="png-shot absolute left-0 top-0 h-[1024px] w-[1024px] origin-top-left overflow-hidden rounded-[18px] bg-[#f5f7f8] shadow-[0_0_0_1px_#dfe6ea_inset]"
                    >
                        <div ref="mapElement" class="absolute inset-0" />

                        <div
                            v-if="distanceKm"
                            class="absolute left-6 top-6 z-[500] text-[#1f4d3a]"
                            :style="{ color: routeColor }"
                        >
                            <div class="metric">{{ Number(distanceKm).toFixed(2) }}</div>
                            <div class="label">КМ</div>
                        </div>

                        <div
                            v-if="showElevation"
                            class="absolute right-6 top-6 z-[500] text-right text-[#1f4d3a]"
                            :style="{ color: routeColor }"
                        >
                            <div class="metric">+{{ elevationMeters }}</div>
                            <div class="label label-long">набор высоты, м</div>
                        </div>

                        <div
                            v-if="showActivityIcon"
                            class="absolute bottom-6 left-6 z-[500] h-28 w-28 text-[#1f4d3a]"
                            :style="{ color: routeColor }"
                            v-html="activityIconSvg(routeModel.activity_type)"
                        />

                        <div
                            v-if="showTime && formattedDuration"
                            class="absolute bottom-6 right-6 z-[500] text-right text-[#1f4d3a]"
                            :style="{ color: routeColor }"
                        >
                            <div class="metric">{{ formattedDuration }}</div>
                            <div class="label">ВРЕМЯ</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <div ref="hiddenExportRoot" class="fixed -left-[10000px] top-0 h-[1024px] w-[1024px] overflow-hidden" />
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.leaflet-control-container) {
    display: none;
}

.png-shot {
    font-family:
        system-ui,
        -apple-system,
        "Segoe UI",
        Roboto,
        Ubuntu,
        Cantarell,
        "Noto Sans",
        sans-serif;
}

.metric {
    font-size: 64px;
    font-weight: 800;
    letter-spacing: 1px;
    line-height: 1;
}

.label {
    margin-top: 4px;
    font-size: 20px;
    font-weight: 500;
    opacity: 0.9;
}

.label-long {
    font-size: 18px;
}
</style>
