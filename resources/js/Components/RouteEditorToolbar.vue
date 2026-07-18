<script setup lang="ts">
defineProps<{
    canUndo: boolean;
    hasPoints: boolean;
    hasLine: boolean;
    insertMode: boolean;
    splitMode: boolean;
    selectMode: boolean;
    newSegmentMode: boolean;
    selectedCount: number;
    canUseEndpoint: boolean;
    canConnect: boolean;
}>();

defineEmits<{
    undo: [];
    'new-segment': [];
    insert: [];
    split: [];
    select: [];
    delete: [];
    continue: [];
    'set-start': [];
    'set-end': [];
    connect: [];
    help: [];
}>();
</script>

<template>
    <div class="route-editor-toolbar" aria-label="Инструменты редактирования маршрута">
        <button type="button" class="map-tool-button" :disabled="!canUndo" title="Отменить последнее изменение" aria-label="Отменить последнее изменение" @click="$emit('undo')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 14l-4-4 4-4" /><path d="M5 10h10a4 4 0 1 1 0 8h-3" /></svg>
        </button>

        <span class="toolbar-divider" aria-hidden="true" />

        <button type="button" class="map-tool-button" :class="{ 'is-active': newSegmentMode }" :disabled="!hasPoints || newSegmentMode" title="Начать новый участок без соединительной линии" aria-label="Начать новый участок" @click="$emit('new-segment')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h5" /><path d="M15 12h5" /><circle cx="11.5" cy="12" r="1" /><circle cx="13.5" cy="12" r="1" /></svg>
        </button>
        <button type="button" class="map-tool-button" :class="{ 'is-active': insertMode }" :disabled="!hasLine" title="Вставить точку на линии" aria-label="Вставить точку на линии" @click="$emit('insert')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16" /><path d="M12 5v14" /></svg>
        </button>
        <button type="button" class="map-tool-button" :class="{ 'is-active': splitMode }" :disabled="!hasLine" title="Удалить линию и разделить трек" aria-label="Разделить трек по линии" @click="$emit('split')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h6" /><path d="M15 12h6" /><path d="M9 8l6 8" /><path d="M15 8l-6 8" /></svg>
        </button>

        <span class="toolbar-divider" aria-hidden="true" />

        <button type="button" class="map-tool-button" :class="{ 'is-active': selectMode }" :disabled="!hasPoints" :title="selectedCount ? `Выбрано точек: ${selectedCount}` : 'Выделить точки рамкой'" aria-label="Выделить точки рамкой" @click="$emit('select')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3" /><path d="M8 8l8 5-4 1-2 4-2-10z" /></svg>
            <span v-if="selectedCount" class="tool-count">{{ selectedCount }}</span>
        </button>
        <button type="button" class="map-tool-button is-danger" :disabled="selectedCount === 0" title="Удалить все выбранные точки" aria-label="Удалить выбранные точки" @click="$emit('delete')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
        </button>

        <span class="toolbar-divider" aria-hidden="true" />

        <button type="button" class="map-tool-button" :disabled="!canUseEndpoint" title="Продолжить от выбранной конечной точки" aria-label="Продолжить от выбранной конечной точки" @click="$emit('continue')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 16c3-8 7-8 12-4" /><path d="M14 8l4 4-4 4" /><circle cx="5" cy="16" r="2" /></svg>
        </button>
        <button type="button" class="map-tool-button" :disabled="!canUseEndpoint" title="Назначить выбранную точку началом маршрута" aria-label="Назначить началом маршрута" @click="$emit('set-start')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 20V5M6 5h11l-2 3 2 3H6" /><circle cx="6" cy="20" r="1.5" /></svg>
        </button>
        <button type="button" class="map-tool-button" :disabled="!canUseEndpoint" title="Назначить выбранную точку концом маршрута" aria-label="Назначить концом маршрута" @click="$emit('set-end')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 4v15M18 19H7l2-3-2-3h11" /><circle cx="18" cy="4" r="1.5" /></svg>
        </button>
        <button type="button" class="map-tool-button" :disabled="!canConnect" title="Соединить две выбранные конечные точки" aria-label="Соединить выбранные треки" @click="$emit('connect')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 8l6 8M8 15l-3 3a3 3 0 0 1-4-4l3-3M16 9l3-3a3 3 0 0 1 4 4l-3 3" /><path d="M7 12l5-5M12 17l5-5" /></svg>
        </button>

        <span class="toolbar-divider" />

        <button type="button" class="map-tool-button is-help" title="Справка по инструментам" aria-label="Открыть справку по инструментам" @click="$emit('help')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M9.8 9a2.4 2.4 0 1 1 3.2 2.3c-.7.3-1 1-1 1.7v.3M12 17h.01" /></svg>
        </button>
    </div>
</template>

<style scoped>
.route-editor-toolbar {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding: 8px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    scrollbar-width: thin;
}

.map-tool-button {
    position: relative;
    display: flex;
    flex: 0 0 40px;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    color: #1f2937;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    transition: transform 160ms ease, color 160ms ease, background-color 160ms ease, border-color 160ms ease;
}

.map-tool-button svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.map-tool-button:hover:not(:disabled),
.map-tool-button.is-active {
    color: #0f766e;
    background: #ecfdf5;
    border-color: #0f766e;
}

.map-tool-button.is-danger:hover:not(:disabled) {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #dc2626;
}

.map-tool-button.is-help {
    color: #0f766e;
}

.map-tool-button:active:not(:disabled) {
    transform: translateY(1px) scale(0.98);
}

.map-tool-button:disabled {
    cursor: not-allowed;
    opacity: 0.38;
}

.toolbar-divider {
    flex: 0 0 1px;
    align-self: stretch;
    margin: 5px 2px;
    background: #e5e7eb;
}

.toolbar-spacer {
    flex: 1 0 10px;
}

.tool-count {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
    background: #0f766e;
    border: 2px solid #ffffff;
    border-radius: 9999px;
}
</style>
