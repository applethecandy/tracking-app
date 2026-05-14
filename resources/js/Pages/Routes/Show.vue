<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RouteMap from '@/Components/RouteMap.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { ActivityMap, TrackRoute } from '@/types/routes';
import { formatDistance, formatElevation } from '@/utils/routeMetrics';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps<{
    routeModel: TrackRoute;
    activities: ActivityMap;
}>();

const destroyRoute = (id: number) => {
    if (window.confirm('Удалить маршрут?')) {
        router.delete(route('routes.destroy', id));
    }
};
</script>

<template>
    <Head :title="routeModel.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ routeModel.title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ routeModel.activity_date }} · {{ routeModel.activity_label }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link :href="route('routes.png', routeModel.id)">
                        <SecondaryButton>PNG</SecondaryButton>
                    </Link>
                    <a :href="route('routes.gpx', routeModel.id)">
                        <SecondaryButton>GPX</SecondaryButton>
                    </a>
                    <Link :href="route('routes.edit', routeModel.id)">
                        <PrimaryButton>Редактировать</PrimaryButton>
                    </Link>
                    <DangerButton @click="destroyRoute(routeModel.id)">Удалить</DangerButton>
                </div>
            </div>
        </template>

        <main class="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-[1fr_340px] lg:px-8">
            <section>
                <RouteMap :points="routeModel.points" />
            </section>

            <aside class="space-y-5">
                <section class="rounded border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500">Длина</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">{{ formatDistance(routeModel.distance_m) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Точек</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900">{{ routeModel.points.length }}</div>
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

                <section v-if="routeModel.share_url" class="rounded border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-sm font-medium text-gray-900">Публичная ссылка</div>
                    <a class="mt-2 block break-all text-sm text-teal-700 hover:text-teal-900" :href="routeModel.share_url">
                        {{ routeModel.share_url }}
                    </a>
                </section>

                <section v-if="routeModel.comment" class="rounded border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-sm font-medium text-gray-900">Комментарий</div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ routeModel.comment }}</p>
                </section>
            </aside>
        </main>
    </AuthenticatedLayout>
</template>
