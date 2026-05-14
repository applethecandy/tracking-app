<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { ActivityMap, TrackRoute } from '@/types/routes';
import { formatDistance, formatElevation } from '@/utils/routeMetrics';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type PaginatedRoutes = {
    data: TrackRoute[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    routes: PaginatedRoutes;
    filters: {
        date_from?: string;
        date_to?: string;
        activity_type?: string;
    };
    activities: ActivityMap;
    totals: {
        routes_count: number;
        distance_m: number;
        elevation_gain_m: number;
    };
}>();

const form = useForm({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    activity_type: props.filters.activity_type ?? '',
});

const applyFilters = () => {
    form.get(route('routes.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    router.get(route('routes.index'));
};
</script>

<template>
    <Head title="Маршруты" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Маршруты</h1>
                    <p class="mt-1 text-sm text-gray-500">Личный журнал ручных маршрутов и нагрузок.</p>
                </div>
                <Link :href="route('routes.create')">
                    <PrimaryButton>Новый маршрут</PrimaryButton>
                </Link>
            </div>
        </template>

        <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-sm text-gray-500">Маршрутов</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ totals.routes_count }}</div>
                </div>
                <div class="rounded border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-sm text-gray-500">Дистанция</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ formatDistance(totals.distance_m) }}</div>
                </div>
                <div class="rounded border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="text-sm text-gray-500">Набор высоты</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-900">{{ formatElevation(totals.elevation_gain_m) }}</div>
                </div>
            </section>

            <form class="grid gap-4 rounded border border-gray-200 bg-white p-5 shadow-sm md:grid-cols-[1fr_1fr_1fr_auto_auto]" @submit.prevent="applyFilters">
                <div>
                    <label class="text-sm font-medium text-gray-700" for="date_from">С даты</label>
                    <input id="date_from" v-model="form.date_from" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700" for="date_to">По дату</label>
                    <input id="date_to" v-model="form.date_to" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700" for="activity">Активность</label>
                    <select id="activity" v-model="form.activity_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="">Все</option>
                        <option v-for="(label, value) in activities" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div class="flex items-end">
                    <PrimaryButton>Применить</PrimaryButton>
                </div>
                <div class="flex items-end">
                    <button type="button" class="text-sm font-medium text-gray-600 hover:text-gray-900" @click="resetFilters">
                        Сбросить
                    </button>
                </div>
            </form>

            <section class="overflow-hidden rounded border border-gray-200 bg-white shadow-sm">
                <div v-if="routes.data.length === 0" class="p-8 text-center text-gray-500">
                    Маршрутов пока нет.
                </div>

                <div v-else class="divide-y divide-gray-100">
                    <Link
                        v-for="item in routes.data"
                        :key="item.id"
                        :href="route('routes.show', item.id)"
                        class="grid gap-3 px-5 py-4 transition hover:bg-gray-50 md:grid-cols-[1fr_auto_auto_auto]"
                    >
                        <div>
                            <div class="font-medium text-gray-900">{{ item.title }}</div>
                            <div class="mt-1 text-sm text-gray-500">{{ item.activity_date }} · {{ item.activity_label }}</div>
                        </div>
                        <div class="text-sm text-gray-700 md:text-right">{{ formatDistance(item.distance_m) }}</div>
                        <div class="text-sm text-gray-700 md:text-right">+{{ formatElevation(item.elevation_gain_m) }}</div>
                        <div class="text-sm text-gray-500 md:text-right">{{ item.is_shared ? 'По ссылке' : 'Личный' }}</div>
                    </Link>
                </div>
            </section>

            <nav v-if="routes.links.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in routes.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded border px-3 py-1 text-sm"
                    :class="link.active ? 'border-teal-700 bg-teal-700 text-white' : 'border-gray-200 bg-white text-gray-700'"
                    v-html="link.label"
                />
            </nav>
        </main>
    </AuthenticatedLayout>
</template>
