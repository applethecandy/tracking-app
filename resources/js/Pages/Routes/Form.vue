<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RouteEditorToolbar from '@/Components/RouteEditorToolbar.vue';
import RouteMap from '@/Components/RouteMap.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { ActivityMap, RoutePoint, TrackRoute } from '@/types/routes';
import { connectEndpoints, endpointIndexes, moveEndpointToRouteEdge, normalizeSegments, splitSegmentBefore } from '@/utils/routeEditing';
import { calculateDistance, formatDistance, pointSegment } from '@/utils/routeMetrics';
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
const splitMode = ref(false);
const startNextSegment = ref(false);
const continuingRoute = ref(false);
const selectedPointIndexes = ref<number[]>([]);
const undoStack = ref<RoutePoint[][]>([]);
const gpxError = ref('');
const showToolsHelp = ref(false);
const canUndo = computed(() => undoStack.value.length > 0);
const hasRouteLine = computed(() => form.points.some((point, index) =>
    index > 0 && pointSegment(form.points[index - 1]) === pointSegment(point),
));
const routeEndpointIndexes = computed(() => endpointIndexes(form.points));
const selectedEndpointIndexes = computed(() => selectedPointIndexes.value.filter((index) => routeEndpointIndexes.value.includes(index)));
const canUseSelectedEndpoint = computed(() => selectedPointIndexes.value.length === 1 && selectedEndpointIndexes.value.length === 1);
const canConnectSelectedEndpoints = computed(() => {
    if (selectedPointIndexes.value.length !== 2 || selectedEndpointIndexes.value.length !== 2) {
        return false;
    }

    const [first, second] = selectedEndpointIndexes.value;
    return pointSegment(form.points[first]) !== pointSegment(form.points[second]);
});

const clonePoints = (points: RoutePoint[]): RoutePoint[] => points.map((point) => ({ ...point }));

const resetMapModes = () => {
    insertMode.value = false;
    pointSelectMode.value = false;
    splitMode.value = false;
    startNextSegment.value = false;
    continuingRoute.value = false;
};

const applyPoints = (points: RoutePoint[], trackUndo = true) => {
    if (trackUndo) {
        undoStack.value.push(clonePoints(form.points));
        undoStack.value = undoStack.value.slice(-100);
    }

    form.points = points;

    selectedPointIndexes.value = selectedPointIndexes.value.filter((index) => index < points.length);
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
    selectedPointIndexes.value = [];
};

const clearPoints = () => {
    applyPoints([]);
    resetMapModes();
    selectedPointIndexes.value = [];
};

const requestSegmentBreak = () => {
    insertMode.value = false;
    pointSelectMode.value = false;
    splitMode.value = false;
    continuingRoute.value = false;
    startNextSegment.value = true;
};

const segmentBreakStarted = () => {
    startNextSegment.value = false;
};

const selectPoints = (indexes: number[]) => {
    selectedPointIndexes.value = [...new Set(indexes)].filter((index) => index >= 0 && index < form.points.length);
};

const deleteSelectedPoints = () => {
    if (selectedPointIndexes.value.length === 0) {
        return;
    }

    const selected = new Set(selectedPointIndexes.value);
    applyPoints(normalizeSegments(form.points.filter((_, index) => !selected.has(index))));
    resetMapModes();
    selectedPointIndexes.value = [];
};

const toggleInsertMode = () => {
    insertMode.value = !insertMode.value;
    pointSelectMode.value = false;
    splitMode.value = false;
    startNextSegment.value = false;
    continuingRoute.value = false;
    selectedPointIndexes.value = [];
};

const pointInserted = () => {
    insertMode.value = false;
};

const toggleSplitMode = () => {
    splitMode.value = !splitMode.value;
    insertMode.value = false;
    pointSelectMode.value = false;
    startNextSegment.value = false;
    continuingRoute.value = false;
    selectedPointIndexes.value = [];
};

const splitSegment = (pointIndex: number) => {
    applyPoints(splitSegmentBefore(form.points, pointIndex));
    splitMode.value = false;
};

const setMapFullscreen = (fullscreen: boolean) => {
    mapFullscreen.value = fullscreen;
};

const togglePointSelectMode = () => {
    pointSelectMode.value = !pointSelectMode.value;
    insertMode.value = false;
    splitMode.value = false;
    startNextSegment.value = false;
    continuingRoute.value = false;
    selectedPointIndexes.value = [];
};

const continueFromEndpoint = (pointIndex?: number) => {
    const targetIndex = pointIndex ?? selectedEndpointIndexes.value[0];
    const points = moveEndpointToRouteEdge(form.points, targetIndex, 'end');

    if (!points) {
        return;
    }

    applyPoints(points);
    insertMode.value = false;
    pointSelectMode.value = false;
    splitMode.value = false;
    startNextSegment.value = false;
    continuingRoute.value = true;
    selectedPointIndexes.value = [];
};

const setRouteEdge = (edge: 'start' | 'end') => {
    const targetIndex = selectedEndpointIndexes.value[0];
    const points = moveEndpointToRouteEdge(form.points, targetIndex, edge);

    if (!points) {
        return;
    }

    applyPoints(points);
    resetMapModes();
    selectedPointIndexes.value = [];
};

const connectSelectedTracks = () => {
    const [firstIndex, secondIndex] = selectedEndpointIndexes.value;
    const points = connectEndpoints(form.points, firstIndex, secondIndex);

    if (!points) {
        return;
    }

    applyPoints(points);
    resetMapModes();
    selectedPointIndexes.value = [];
};

const importGpx = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    gpxError.value = '';

    if (!file) {
        return;
    }

    try {
        const text = await file.text();
        const parsed = parseGpx(text, file.name);

        if (parsed.points.length < 2) {
            gpxError.value = 'В GPX не найден маршрут минимум из двух точек.';
            return;
        }

        form.title = parsed.title || form.title;
        form.activity_date = parsed.activityDate || form.activity_date;
        form.duration_minutes = parsed.durationMinutes ? parsed.durationMinutes.toString() : form.duration_minutes;
        form.activity_type = parsed.activityType || form.activity_type;
        applyPoints(parsed.points);
        resetMapModes();
        selectedPointIndexes.value = [];
    } catch (error) {
        gpxError.value = error instanceof Error ? error.message : 'Не удалось прочитать GPX.';
    } finally {
        input.value = '';
    }
};

const parseGpx = (contents: string, filename: string): { title: string; activityDate: string; activityType: string; durationMinutes: number | null; points: RoutePoint[] } => {
    const parser = new DOMParser();
    const xml = parser.parseFromString(contents, 'application/xml');

    if (xml.querySelector('parsererror')) {
        throw new Error('Файл не похож на корректный GPX.');
    }

    const segments = elements(xml, 'trkseg');
    const points: RoutePoint[] = [];
    const times: Date[] = [];

    if (segments.length > 0) {
        segments.forEach((segment, segmentIndex) => {
            elements(segment, 'trkpt').forEach((point) => {
                const parsedPoint = parseGpxPoint(point, segmentIndex);

                if (parsedPoint) {
                    points.push(parsedPoint.point);

                    if (parsedPoint.time) {
                        times.push(parsedPoint.time);
                    }
                }
            });
        });
    } else {
        elements(xml, 'rtept').forEach((point) => {
            const parsedPoint = parseGpxPoint(point, 0);

            if (parsedPoint) {
                points.push(parsedPoint.point);

                if (parsedPoint.time) {
                    times.push(parsedPoint.time);
                }
            }
        });
    }

    const firstTime = times[0] ?? firstGpxTime(xml);
    const lastTime = times.at(-1) ?? null;

    return {
        title: firstNestedText(xml, 'trk', 'name') || firstNestedText(xml, 'rte', 'name') || filename.replace(/\.[^.]+$/, ''),
        activityDate: firstTime ? firstTime.toISOString().slice(0, 10) : '',
        activityType: activityFromGpxType(firstNestedText(xml, 'trk', 'type') || firstNestedText(xml, 'rte', 'type')),
        durationMinutes: firstTime && lastTime && lastTime > firstTime
            ? Math.max(1, Math.round((lastTime.getTime() - firstTime.getTime()) / 60000))
            : null,
        points,
    };
};

const parseGpxPoint = (element: Element, segment: number): { point: RoutePoint; time: Date | null } | null => {
    const lat = Number(element.getAttribute('lat'));
    const lng = Number(element.getAttribute('lon'));

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }

    const eleText = directText(element, 'ele');
    const timeText = directText(element, 'time');

    return {
        point: {
            lat: Number(lat.toFixed(7)),
            lng: Number(lng.toFixed(7)),
            ele: eleText !== '' && Number.isFinite(Number(eleText)) ? Number(eleText) : null,
            segment,
        },
        time: timeText ? parseDate(timeText) : null,
    };
};

const elements = (root: Document | Element, tagName: string): Element[] => [
    ...Array.from(root.getElementsByTagName(tagName)),
    ...Array.from(root.getElementsByTagNameNS('*', tagName)),
].filter((element, index, all) => all.indexOf(element) === index);

const firstNestedText = (root: Document, parentTagName: string, childTagName: string): string => {
    const parent = elements(root, parentTagName)[0];

    return parent ? directText(parent, childTagName) : '';
};

const directText = (root: Element, tagName: string): string => {
    const child = Array.from(root.children).find((element) => element.localName === tagName || element.tagName === tagName);

    return child?.textContent?.trim() ?? '';
};

const firstGpxTime = (root: Document): Date | null => {
    const metadata = elements(root, 'metadata')[0];
    const timeText = metadata ? directText(metadata, 'time') : directText(root.documentElement, 'time');

    return timeText ? parseDate(timeText) : null;
};

const activityFromGpxType = (value: string): string => {
    const normalized = value.toLowerCase();

    if (normalized.includes('run')) {
        return 'run';
    }

    if (normalized.includes('walk') || normalized.includes('hike')) {
        return 'walk';
    }

    if (normalized.includes('car') || normalized.includes('drive')) {
        return 'car';
    }

    if (normalized.includes('skate')) {
        return 'skateboard';
    }

    return '';
};

const parseDate = (value: string): Date | null => {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
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

                <div v-if="!isEditing" class="rounded border border-dashed border-gray-300 bg-gray-50 p-4">
                    <InputLabel for="gpx_import" value="Импорт GPX" />
                    <input
                        id="gpx_import"
                        type="file"
                        accept=".gpx,application/gpx+xml,application/xml,text/xml"
                        class="mt-2 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-800"
                        @change="importGpx"
                    />
                    <p v-if="gpxError" class="mt-2 text-sm text-red-600">{{ gpxError }}</p>
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

                <p v-if="selectedPointIndexes.length" class="text-sm text-gray-600">
                    Выбрано точек: {{ selectedPointIndexes.length }}. Конечных среди них: {{ selectedEndpointIndexes.length }}.
                </p>

                <p v-if="pointSelectMode" class="text-sm text-teal-700">
                    Протяните рамку по карте или кликайте по точкам. Ctrl/Cmd добавляет рамку к текущему выбору.
                </p>

                <p v-if="startNextSegment" class="text-sm text-teal-700">
                    Следующая точка начнет новый участок без соединительной линии.
                </p>

                <p v-if="insertMode" class="text-sm text-teal-700">
                    Кликните по отрезку маршрута, чтобы вставить точку между соседними точками.
                </p>

                <p v-if="splitMode" class="text-sm text-red-700">
                    Кликните по линии, которую нужно удалить. Трек будет разделён на два участка.
                </p>

                <p v-if="continuingRoute" class="text-sm text-teal-700">
                    Продолжение включено. Добавляйте новые точки кликами по карте.
                </p>

                <p v-if="!insertMode && !splitMode && !pointSelectMode && !continuingRoute && !startNextSegment" class="text-xs leading-5 text-gray-500">
                    Клик по карте или существующей точке добавляет новую точку маршрута. Для продолжения от другого конца используйте «Выделить» и «Продолжить».
                </p>
            </section>

            <section class="relative h-[440px]">
                <RouteMap
                    v-if="!mapFullscreen"
                    :fullscreen="false"
                    :points="form.points"
                    :insert-mode="insertMode"
                    :point-select-mode="pointSelectMode"
                    :selected-point-indexes="selectedPointIndexes"
                    :split-mode="splitMode"
                    :start-next-segment="startNextSegment"
                    editable
                    show-intermediate-markers
                    @point-inserted="pointInserted"
                    @point-selection-changed="selectPoints"
                    @segment-split="splitSegment"
                    @segment-started="segmentBreakStarted"
                    @update:fullscreen="setMapFullscreen"
                    @update:points="setPoints"
                />

                <RouteEditorToolbar
                    class="absolute bottom-4 left-4 z-[1000] max-w-[calc(100%-2rem)]"
                    :can-undo="canUndo"
                    :has-points="form.points.length > 0"
                    :has-line="hasRouteLine"
                    :insert-mode="insertMode"
                    :split-mode="splitMode"
                    :select-mode="pointSelectMode"
                    :new-segment-mode="startNextSegment"
                    :selected-count="selectedPointIndexes.length"
                    :can-use-endpoint="canUseSelectedEndpoint"
                    :can-connect="canConnectSelectedEndpoints"
                    @undo="undoChange"
                    @new-segment="requestSegmentBreak"
                    @insert="toggleInsertMode"
                    @split="toggleSplitMode"
                    @select="togglePointSelectMode"
                    @delete="deleteSelectedPoints"
                    @continue="continueFromEndpoint()"
                    @set-start="setRouteEdge('start')"
                    @set-end="setRouteEdge('end')"
                    @connect="connectSelectedTracks"
                    @help="showToolsHelp = true"
                />
            </section>
        </form>

        <Teleport to="body">
            <div v-if="mapFullscreen" class="fixed inset-0 z-[9000] h-dvh w-screen bg-gray-100">
                <RouteMap
                    :fullscreen="true"
                    :points="form.points"
                    :insert-mode="insertMode"
                    :point-select-mode="pointSelectMode"
                    :selected-point-indexes="selectedPointIndexes"
                    :split-mode="splitMode"
                    :start-next-segment="startNextSegment"
                    editable
                    show-intermediate-markers
                    @point-inserted="pointInserted"
                    @point-selection-changed="selectPoints"
                    @segment-split="splitSegment"
                    @segment-started="segmentBreakStarted"
                    @update:fullscreen="setMapFullscreen"
                    @update:points="setPoints"
                />

                <div class="absolute left-16 top-3 z-[1000] rounded-md bg-white/92 p-1.5 text-sm shadow-lg ring-1 ring-black/10 backdrop-blur">
                    <div class="min-w-20 rounded bg-white px-2.5 py-1.5 sm:min-w-24 sm:px-3 sm:py-2">
                        <div class="text-xs text-gray-500">Длина</div>
                        <div class="font-semibold text-gray-900">{{ formatDistance(distance) }}</div>
                    </div>
                </div>

                <RouteEditorToolbar
                    class="absolute bottom-4 left-4 z-[1000] max-w-[calc(100%-2rem)]"
                    :can-undo="canUndo"
                    :has-points="form.points.length > 0"
                    :has-line="hasRouteLine"
                    :insert-mode="insertMode"
                    :split-mode="splitMode"
                    :select-mode="pointSelectMode"
                    :new-segment-mode="startNextSegment"
                    :selected-count="selectedPointIndexes.length"
                    :can-use-endpoint="canUseSelectedEndpoint"
                    :can-connect="canConnectSelectedEndpoints"
                    @undo="undoChange"
                    @new-segment="requestSegmentBreak"
                    @insert="toggleInsertMode"
                    @split="toggleSplitMode"
                    @select="togglePointSelectMode"
                    @delete="deleteSelectedPoints"
                    @continue="continueFromEndpoint()"
                    @set-start="setRouteEdge('start')"
                    @set-end="setRouteEdge('end')"
                    @connect="connectSelectedTracks"
                    @help="showToolsHelp = true"
                />
            </div>
        </Teleport>

        <Modal :show="showToolsHelp" max-width="lg" @close="showToolsHelp = false">
            <div class="p-6 sm:p-7">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Инструменты карты</h2>
                        <p class="mt-1 text-sm text-gray-500">Краткая памятка по редактированию трека.</p>
                    </div>
                    <button type="button" class="help-close-button" aria-label="Закрыть справку" @click="showToolsHelp = false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>

                <dl class="help-list">
                    <div><dt>Отменить</dt><dd>Возвращает состояние точек до последнего изменения. Доступно до 100 шагов.</dd></div>
                    <div><dt>Новый участок</dt><dd>Следующий клик по карте начинает отдельный участок без соединительной линии.</dd></div>
                    <div><dt>Вставить точку</dt><dd>Нажмите инструмент, затем кликните по линии между соседними точками.</dd></div>
                    <div><dt>Разделить трек</dt><dd>Нажмите на линию, которую нужно удалить. Один участок станет двумя.</dd></div>
                    <div><dt>Выделить</dt><dd>Протяните рамку вокруг точек или выбирайте их кликами. Ctrl/Cmd сохраняет прежний выбор при выделении рамкой.</dd></div>
                    <div><dt>Удалить</dt><dd>Удаляет все выбранные точки одним действием.</dd></div>
                    <div><dt>Продолжить</dt><dd>В режиме выделения выберите одну конечную точку, нажмите этот инструмент и продолжайте маршрут кликами по карте.</dd></div>
                    <div><dt>Сделать началом / концом</dt><dd>Перестраивает направление и порядок участков так, чтобы выбранная конечная точка стала началом или концом всего маршрута.</dd></div>
                    <div><dt>Соединить</dt><dd>Выберите две конечные точки разных участков. Участки развернутся при необходимости и соединятся линией.</dd></div>
                </dl>

                <div class="mt-5 rounded-md bg-teal-50 px-4 py-3 text-sm leading-6 text-teal-900">
                    Начала участков отмечены зелёным, концы — красным. Одиночный участок без линии отмечается бирюзовым.
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.help-close-button {
    display: flex;
    flex: 0 0 36px;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    color: #4b5563;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    transition: transform 160ms ease, color 160ms ease, border-color 160ms ease, background-color 160ms ease;
}

.help-close-button svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.help-close-button:hover {
    color: #0f766e;
    background: #ecfdf5;
    border-color: #0f766e;
}

.help-close-button:active {
    transform: scale(0.98);
}

.help-list {
    margin-top: 6px;
}

.help-list > div {
    display: grid;
    grid-template-columns: minmax(120px, 0.65fr) minmax(0, 1.8fr);
    gap: 20px;
    padding: 13px 0;
    border-bottom: 1px solid #f3f4f6;
}

.help-list dt {
    color: #111827;
    font-size: 14px;
    font-weight: 600;
}

.help-list dd {
    color: #4b5563;
    font-size: 14px;
    line-height: 1.55;
}

@media (max-width: 639px) {
    .help-list > div {
        grid-template-columns: 1fr;
        gap: 4px;
    }
}
</style>
