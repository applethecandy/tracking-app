<script setup lang="ts">
import RouteMap from '@/Components/RouteMap.vue';
import type { TrackRoute } from '@/types/routes';
import { formatDistance, formatDuration, formatElevation } from '@/utils/routeMetrics';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const mapFullscreen = ref(false);

const setMapFullscreen = (fullscreen: boolean) => {
    mapFullscreen.value = fullscreen;
};

defineProps<{
    routeModel: TrackRoute;
}>();
</script>

<template>
    <Head :title="routeModel.title" />

    <main class="min-h-screen bg-gray-100">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-6 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div>
                    <div class="text-sm font-medium uppercase tracking-wide text-teal-700">Открытый маршрут</div>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ routeModel.title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ routeModel.activity_date }} · {{ routeModel.activity_label }}</p>
                </div>
                <div class="flex gap-3">
                    <a
                        :href="routeModel.gpx_url"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        GPX
                    </a>
                    <Link
                        :href="route('login')"
                        class="inline-flex items-center rounded-md border border-transparent bg-teal-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-teal-800"
                    >
                        Войти
                    </Link>
                </div>
            </div>
        </header>

        <section class="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-[1fr_340px] lg:px-8">
            <RouteMap
                :fullscreen="mapFullscreen"
                :points="routeModel.points"
                @update:fullscreen="setMapFullscreen"
            />

            <aside class="space-y-5">
                <section class="rounded border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500">Длина</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">{{ formatDistance(routeModel.distance_m) }}</div>
                        </div>
                        <div v-if="routeModel.duration_minutes">
                            <div class="text-sm text-gray-500">Время</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">{{ formatDuration(routeModel.duration_minutes) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Набор</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">{{ formatElevation(routeModel.elevation_gain_m) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Сброс</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">{{ formatElevation(routeModel.elevation_loss_m) }}</div>
                        </div>
                    </div>
                </section>

                <section v-if="routeModel.comment" class="rounded border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-sm font-medium text-gray-900">Комментарий</div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ routeModel.comment }}</p>
                </section>
            </aside>
        </section>
    </main>
</template>
