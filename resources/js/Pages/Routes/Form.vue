<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RouteMap from '@/Components/RouteMap.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { ActivityMap, RoutePoint, TrackRoute } from '@/types/routes';
import { calculateDistance, formatDistance } from '@/utils/routeMetrics';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    routeModel: TrackRoute | null;
    activities: ActivityMap;
}>();

const form = useForm({
    title: props.routeModel?.title ?? '',
    activity_date: props.routeModel?.activity_date ?? new Date().toISOString().slice(0, 10),
    activity_type: props.routeModel?.activity_type ?? 'walk',
    comment: props.routeModel?.comment ?? '',
    is_shared: props.routeModel?.is_shared ?? false,
    points: props.routeModel?.points ?? ([] as RoutePoint[]),
});

const distance = computed(() => calculateDistance(form.points));
const isEditing = computed(() => props.routeModel !== null);

const setPoints = (points: RoutePoint[]) => {
    form.points = points;
};

const undoPoint = () => {
    form.points = form.points.slice(0, -1);
};

const clearPoints = () => {
    form.points = [];
};

const submit = () => {
    if (props.routeModel) {
        form.put(route('routes.update', props.routeModel.id));
        return;
    }

    form.post(route('routes.store'));
};
</script>

<template>
    <Head :title="isEditing ? 'Редактирование маршрута' : 'Новый маршрут'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        {{ isEditing ? 'Редактирование маршрута' : 'Новый маршрут' }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">Кликайте по карте, чтобы добавить точки вручную.</p>
                </div>
                <Link
                    :href="route('routes.index')"
                    class="text-sm font-medium text-teal-700 hover:text-teal-900"
                >
                    К списку
                </Link>
            </div>
        </template>

        <form class="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-[360px_1fr] lg:px-8" @submit.prevent="submit">
            <section class="space-y-5 rounded border border-gray-200 bg-white p-5 shadow-sm">
                <div>
                    <InputLabel for="title" value="Название" />
                    <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.title" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="activity_date" value="Дата" />
                    <TextInput id="activity_date" v-model="form.activity_date" type="date" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.activity_date" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="activity_type" value="Активность" />
                    <select
                        id="activity_type"
                        v-model="form.activity_type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                    >
                        <option v-for="(label, value) in activities" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.activity_type" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="comment" value="Комментарий" />
                    <textarea
                        id="comment"
                        v-model="form.comment"
                        rows="6"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        placeholder="Заметки, ссылки на фото, детали покрытия"
                    />
                    <InputError :message="form.errors.comment" class="mt-2" />
                </div>

                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input v-model="form.is_shared" type="checkbox" class="mt-1 rounded border-gray-300 text-teal-700 focus:ring-teal-600" />
                    <span>
                        Открыть доступ по ссылке
                        <span class="block text-xs text-gray-500">Ссылка будет доступна всем, у кого она есть.</span>
                    </span>
                </label>

                <div class="grid grid-cols-2 gap-3 border-y border-gray-100 py-4 text-sm">
                    <div>
                        <div class="text-gray-500">Точек</div>
                        <div class="text-lg font-semibold text-gray-900">{{ form.points.length }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Длина</div>
                        <div class="text-lg font-semibold text-gray-900">{{ formatDistance(distance) }}</div>
                    </div>
                </div>

                <InputError :message="form.errors.points" />

                <div class="flex flex-wrap gap-3">
                    <PrimaryButton :disabled="form.processing || form.points.length < 2">
                        {{ isEditing ? 'Сохранить' : 'Создать' }}
                    </PrimaryButton>
                    <SecondaryButton type="button" :disabled="form.points.length === 0" @click="undoPoint">
                        Убрать точку
                    </SecondaryButton>
                    <SecondaryButton type="button" :disabled="form.points.length === 0" @click="clearPoints">
                        Очистить
                    </SecondaryButton>
                </div>
            </section>

            <section>
                <RouteMap :points="form.points" editable @update:points="setPoints" />
            </section>
        </form>
    </AuthenticatedLayout>
</template>
