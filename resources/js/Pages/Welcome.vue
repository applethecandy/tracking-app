<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
}>();
</script>

<template>
    <Head title="Tracking" />

    <main class="min-h-screen bg-gray-100">
        <section class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-4 py-12">
            <div class="grid gap-10 lg:grid-cols-[1fr_420px] lg:items-center">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-wide text-teal-700">Tracking</div>
                    <h1 class="mt-4 text-4xl font-semibold tracking-normal text-gray-950 sm:text-5xl">
                        Журнал маршрутов, которые вы прошли или проехали
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg leading-8 text-gray-600">
                        Создавайте маршрут точками на карте, сохраняйте активность, дату и комментарий, смотрите статистику по периодам и выгружайте GPX.
                    </p>
                    <div v-if="canLogin" class="mt-8 flex flex-wrap gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="route('routes.index')"
                            class="inline-flex items-center rounded-md bg-teal-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-teal-800"
                        >
                            Открыть маршруты
                        </Link>

                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="inline-flex items-center rounded-md bg-teal-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-teal-800"
                            >
                                Войти
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-5 py-3 text-sm font-semibold text-gray-800 transition hover:bg-gray-50"
                            >
                                Зарегистрироваться
                            </Link>
                        </template>
                    </div>
                </div>

                <div class="rounded border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="space-y-5">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                            <div>
                                <div class="font-medium text-gray-900">Скейт по набережной</div>
                                <div class="mt-1 text-sm text-gray-500">14.05.2026 · Скейтбординг</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900">8,42 км</div>
                                <div class="text-sm text-gray-500">+0 м</div>
                            </div>
                        </div>
                        <div class="h-64 rounded bg-[linear-gradient(135deg,#dbeafe_0%,#f8fafc_45%,#ccfbf1_100%)] p-4">
                            <svg viewBox="0 0 360 220" class="h-full w-full">
                                <path d="M22 174 C 76 154, 98 66, 160 86 S 220 176, 336 44" fill="none" stroke="#2563eb" stroke-width="8" stroke-linecap="round" />
                                <circle cx="22" cy="174" r="10" fill="#16a34a" stroke="#fff" stroke-width="4" />
                                <circle cx="336" cy="44" r="10" fill="#dc2626" stroke="#fff" stroke-width="4" />
                            </svg>
                        </div>
                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <div class="text-gray-500">Неделя</div>
                                <div class="font-semibold text-gray-900">23,6 км</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Маршрутов</div>
                                <div class="font-semibold text-gray-900">5</div>
                            </div>
                            <div>
                                <div class="text-gray-500">GPX</div>
                                <div class="font-semibold text-gray-900">готов</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
