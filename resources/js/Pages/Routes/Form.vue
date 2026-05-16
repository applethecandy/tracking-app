<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RouteMap from '@/Components/RouteMap.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { ActivityMap, RoutePoint, TrackRoute } from '@/types/routes';
import { calculateDistance, formatDistance } from '@/utils/routeMetrics';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    routeModel: TrackRoute | null;
    activities: ActivityMap;
}>();

const form = useForm({
    title: props.routeModel?.title ?? '',
    activity_date: props.routeModel?.activity_date ?? new Date().toISOString().slice(0, 10),
    duration_minutes: props.routeModel?.duration_minutes?.toString() ?? '',
    activity_type: props.routeModel?.activity_type ?? 'walk',
    comment: props.routeModel?.comment ?? '',
    is_shared: props.routeModel?.is_shared ?? false,
    points: props.routeModel?.points ?? ([] as RoutePoint[]),
});

const distance = computed(() => calculateDistance(form.points));
const isEditing = computed(() => props.routeModel !== null);
const insertMode = ref(false);
const mapFullscreen = ref(false);
const pointSelectMode = ref(false);
const startNextSegment = ref(false);
const selectedPointIndex = ref<number | null>(null);
const undoStack = ref<RoutePoint[][]>([]);
const canUndo = computed(() => undoStack.value.length > 0);

const clonePoints = (points: RoutePoint[]): RoutePoint[] => points.map((point) => ({ ...point }));

const resetMapModes = () => {
    insertMode.value = false;
    pointSelectMode.value = false;
    startNextSegment.value = false;
};

const applyPoints = (points: RoutePoint[], trackUndo = true) => {
    if (trackUndo) {
        undoStack.value.push(clonePoints(form.points));
        undoStack.value = undoStack.value.slice(-100);
    }

    form.points = points;

    if (selectedPointIndex.value !== null && selectedPointIndex.value >= points.length) {
        selectedPointIndex.value = null;
    }
};

const setPoints = (points: RoutePoint[]) => {
    applyPoints(points);
};

const undoChange = () => {
    const previousPoints = undoStack.value.pop();

    if (!previousPoints) {
        return;
    }

    form.points = clonePoints(previousPoints);
    resetMapModes();
    selectedPointIndex.value = null;
};

const clearPoints = () => {
    applyPoints([]);
    resetMapModes();
    selectedPointIndex.value = null;
};

const requestSegmentBreak = () => {
    insertMode.value = false;
    pointSelectMode.value = false;
    startNextSegment.value = true;
};

const segmentBreakStarted = () => {
    startNextSegment.value = false;
};

const selectPoint = (index: number | null) => {
    selectedPointIndex.value = index;
};

const deleteSelectedPoint = () => {
    if (selectedPointIndex.value === null) {
        return;
    }

    applyPoints(form.points.filter((_, index) => index !== selectedPointIndex.value));
    resetMapModes();
    selectedPointIndex.value = null;
};

const toggleInsertMode = () => {
    insertMode.value = !insertMode.value;
    pointSelectMode.value = false;
    startNextSegment.value = false;
    selectedPointIndex.value = null;
};

const pointInserted = () => {
    insertMode.value = false;
};

const setMapFullscreen = (fullscreen: boolean) => {
    mapFullscreen.value = fullscreen;
};

const togglePointSelectMode = () => {
    pointSelectMode.value = !pointSelectMode.value;
    insertMode.value = false;
    startNextSegment.value = false;
    selectedPointIndex.value = null;
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
                    <InputLabel for="duration_minutes" value="Время в пути, минут" />
                    <TextInput
                        id="duration_minutes"
                        v-model="form.duration_minutes"
                        type="number"
                        min="1"
                        max="10080"
                        class="mt-1 block w-full"
                        placeholder="Необязательно"
                    />
                    <InputError :message="form.errors.duration_minutes" class="mt-2" />
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

                <div>
                    <PrimaryButton :disabled="form.processing || form.points.length < 2">
                        {{ isEditing ? 'Сохранить' : 'Создать' }}
                    </PrimaryButton>
                </div>

                <p v-if="selectedPointIndex !== null" class="text-sm text-gray-600">
                    Выбрана точка {{ selectedPointIndex + 1 }}. Ее можно перетащить на карте или удалить кнопкой.
                </p>

                <p v-if="pointSelectMode" class="text-sm text-teal-700">
                    Кликните по точке, чтобы выбрать ее для удаления. В обычном режиме клик по точке продолжает маршрут.
                </p>

                <p v-if="startNextSegment" class="text-sm text-teal-700">
                    Следующая точка начнет новый участок без соединительной линии.
                </p>

                <p v-if="insertMode" class="text-sm text-teal-700">
                    Кликните по отрезку маршрута, чтобы вставить точку между соседними точками.
                </p>

                <p v-else class="text-xs leading-5 text-gray-500">
                    Для вставки точки кликните по нужному отрезку маршрута.
                </p>
            </section>

            <section class="relative h-[440px]">
                <RouteMap
                    v-if="!mapFullscreen"
                    :fullscreen="false"
                    :points="form.points"
                    :insert-mode="insertMode"
                    :point-select-mode="pointSelectMode"
                    :selected-point-index="selectedPointIndex"
                    :start-next-segment="startNextSegment"
                    editable
                    show-intermediate-markers
                    @point-inserted="pointInserted"
                    @point-selected="selectPoint"
                    @segment-started="segmentBreakStarted"
                    @update:fullscreen="setMapFullscreen"
                    @update:points="setPoints"
                />

                <div class="absolute bottom-4 left-4 z-[1000] flex max-w-[calc(100vw-2rem)] flex-wrap gap-2 rounded-md bg-white/92 p-2 shadow-lg ring-1 ring-black/10 backdrop-blur">
                    <button
                        type="button"
                        class="map-tool-button"
                        :disabled="!canUndo"
                        title="Отменить"
                        aria-label="Отменить"
                        @click="undoChange"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 14l-4 -4l4 -4" />
                            <path d="M5 10h10a4 4 0 1 1 0 8h-3" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="map-tool-button"
                        :class="{ 'is-active': startNextSegment }"
                        :disabled="form.points.length === 0 || startNextSegment"
                        title="Разрыв"
                        aria-label="Разрыв"
                        @click="requestSegmentBreak"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 12h6" />
                            <path d="M14 12h6" />
                            <path d="M10 8l4 8" />
                            <path d="M14 8l-4 8" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="map-tool-button"
                        :class="{ 'is-active': insertMode }"
                        :disabled="form.points.length < 2"
                        title="Вставить точку"
                        aria-label="Вставить точку"
                        @click="toggleInsertMode"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="map-tool-button"
                        :class="{ 'is-active': pointSelectMode }"
                        :disabled="form.points.length === 0"
                        title="Выбрать точку"
                        aria-label="Выбрать точку"
                        @click="togglePointSelectMode"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 4l10 7l-5 1l-2 5l-3 -13z" />
                            <circle cx="17" cy="17" r="3" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="map-tool-button"
                        :disabled="selectedPointIndex === null"
                        title="Удалить выбранную"
                        aria-label="Удалить выбранную"
                        @click="deleteSelectedPoint"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7h16" />
                            <path d="M10 11v6" />
                            <path d="M14 11v6" />
                            <path d="M5 7l1 13h12l1 -13" />
                            <path d="M9 7V4h6v3" />
                        </svg>
                    </button>
                </div>
            </section>
        </form>

        <Teleport to="body">
            <div v-if="mapFullscreen" class="fixed inset-0 z-[9000] h-dvh w-screen bg-gray-100">
                <RouteMap
                    :fullscreen="true"
                    :points="form.points"
                    :insert-mode="insertMode"
                    :point-select-mode="pointSelectMode"
                    :selected-point-index="selectedPointIndex"
                    :start-next-segment="startNextSegment"
                    editable
                    show-intermediate-markers
                    @point-inserted="pointInserted"
                    @point-selected="selectPoint"
                    @segment-started="segmentBreakStarted"
                    @update:fullscreen="setMapFullscreen"
                    @update:points="setPoints"
                />

                <div class="absolute bottom-4 left-4 z-[1000] flex max-w-[calc(100vw-2rem)] flex-wrap gap-2 rounded-md bg-white/92 p-2 shadow-lg ring-1 ring-black/10 backdrop-blur">
                    <button
                        type="button"
                        class="map-tool-button"
                        :disabled="!canUndo"
                        title="Отменить"
                        aria-label="Отменить"
                        @click="undoChange"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 14l-4 -4l4 -4" />
                            <path d="M5 10h10a4 4 0 1 1 0 8h-3" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="map-tool-button"
                        :class="{ 'is-active': startNextSegment }"
                        :disabled="form.points.length === 0 || startNextSegment"
                        title="Разрыв"
                        aria-label="Разрыв"
                        @click="requestSegmentBreak"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 12h6" />
                            <path d="M14 12h6" />
                            <path d="M10 8l4 8" />
                            <path d="M14 8l-4 8" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="map-tool-button"
                        :class="{ 'is-active': insertMode }"
                        :disabled="form.points.length < 2"
                        title="Вставить точку"
                        aria-label="Вставить точку"
                        @click="toggleInsertMode"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="map-tool-button"
                        :class="{ 'is-active': pointSelectMode }"
                        :disabled="form.points.length === 0"
                        title="Выбрать точку"
                        aria-label="Выбрать точку"
                        @click="togglePointSelectMode"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 4l10 7l-5 1l-2 5l-3 -13z" />
                            <circle cx="17" cy="17" r="3" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="map-tool-button"
                        :disabled="selectedPointIndex === null"
                        title="Удалить выбранную"
                        aria-label="Удалить выбранную"
                        @click="deleteSelectedPoint"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7h16" />
                            <path d="M10 11v6" />
                            <path d="M14 11v6" />
                            <path d="M5 7l1 13h12l1 -13" />
                            <path d="M9 7V4h6v3" />
                        </svg>
                    </button>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.map-tool-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    color: #111827;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.map-tool-button svg {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.map-tool-button:hover:not(:disabled),
.map-tool-button.is-active {
    color: #0f766e;
    background: #ecfdf5;
    border-color: #0f766e;
}

.map-tool-button:disabled {
    cursor: not-allowed;
    opacity: 0.42;
}
</style>
