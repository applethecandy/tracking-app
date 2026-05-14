<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { TrackRoute } from '@/types/routes';
import { formatDistance } from '@/utils/routeMetrics';
import { Head, Link } from '@inertiajs/vue3';
import * as htmlToImage from 'html-to-image';
import L, { type LatLngExpression, type Map as LeafletMap } from 'leaflet';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    routeModel: TrackRoute;
}>();

const mapElement = ref<HTMLElement | null>(null);
const shotElement = ref<HTMLElement | null>(null);
const stageElement = ref<HTMLElement | null>(null);
const routeColor = ref('#1f4d3a');
const lineWeight = ref(14);
const distanceKm = ref((props.routeModel.distance_m / 1000).toFixed(2));
const routeTime = ref('');
const isExporting = ref(false);

let map: LeafletMap | null = null;
let routeLine: L.Polyline | null = null;

const latLngs = computed<LatLngExpression[]>(() =>
    props.routeModel.points.map((point) => [point.lat, point.lng] as LatLngExpression),
);

const previewDistance = computed(() => formatDistance(props.routeModel.distance_m));

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
    routeLine?.setStyle({
        color: routeColor.value,
        weight: lineWeight.value,
    });
});

const initMap = () => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: false,
        wheelPxPerZoomLevel: 60,
        zoomSnap: 0.25,
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        crossOrigin: true,
    }).addTo(map);

    routeLine = L.polyline(latLngs.value, {
        color: routeColor.value,
        weight: lineWeight.value,
        opacity: 1,
        lineCap: 'round',
        lineJoin: 'round',
    }).addTo(map);

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
    if (!stageElement.value || !shotElement.value) {
        return;
    }

    const width = stageElement.value.clientWidth;
    const height = stageElement.value.clientHeight;
    const scale = Math.max(0.1, Math.min(width, height) / 1024);

    shotElement.value.style.transform = `scale(${scale})`;
    map?.invalidateSize(false);
};

const exportPng = async () => {
    if (!shotElement.value) {
        return;
    }

    isExporting.value = true;
    const previousTransform = shotElement.value.style.transform;

    try {
        fitTrack();
        await nextTick();

        shotElement.value.style.transform = 'none';
        map?.invalidateSize(false);
        await new Promise((resolve) => window.setTimeout(resolve, 80));

        const dataUrl = await htmlToImage.toPng(shotElement.value, {
            backgroundColor: 'transparent',
            width: 1024,
            height: 1024,
            pixelRatio: 1,
            cacheBust: true,
        });

        const filename = (props.routeModel.title || 'route')
            .replace(/[\\/:*?"<>|]+/g, '-')
            .trim();
        const link = document.createElement('a');
        link.download = `${filename || 'route'}.png`;
        link.href = dataUrl;
        link.click();
    } finally {
        shotElement.value.style.transform = previousTransform;
        updateScale();
        isExporting.value = false;
    }
};
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

        <main class="grid min-h-[calc(100vh-129px)] gap-4 p-4 lg:grid-cols-[320px_1fr]">
            <aside class="rounded border border-gray-200 bg-slate-200 p-4 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Параметры</h2>

                <div class="mt-4 grid grid-cols-2 gap-3">
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
                    <div>
                        <label for="routeTime" class="block text-xs font-medium text-gray-700">Время</label>
                        <input
                            id="routeTime"
                            v-model="routeTime"
                            type="time"
                            step="60"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-slate-50 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        />
                    </div>
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

            <section ref="stageElement" class="flex min-h-[520px] items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-100">
                <div
                    ref="shotElement"
                    class="png-shot relative h-[1024px] w-[1024px] origin-top overflow-hidden rounded-[18px] bg-[#f5f7f8] shadow-[0_0_0_1px_#dfe6ea_inset]"
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
                        v-if="routeTime"
                        class="absolute bottom-6 right-6 z-[500] text-right text-[#1f4d3a]"
                        :style="{ color: routeColor }"
                    >
                        <div class="metric">{{ routeTime }}</div>
                        <div class="label">ВРЕМЯ</div>
                    </div>
                </div>
            </section>
        </main>
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
</style>
