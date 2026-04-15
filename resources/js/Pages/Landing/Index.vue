<script setup>
import LandingLayout from '@/Layouts/LandingLayout.vue';
import LandingAmbientBackground from '@/Pages/Landing/LandingAmbientBackground.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: LandingLayout });

const props = defineProps({
    connect_telegram_url: {
        type: String,
        default: null,
    },
    landing_stats: {
        type: Object,
        required: true,
    },
});

const avg_processing_label = computed(() => {
    const v = props.landing_stats?.today?.avg_processing_minutes;
    if (v === null || v === undefined) {
        return '—';
    }
    return `${String(v).replace('.', ',')} мин`;
});

const page = usePage();
const app_name = computed(() => page.props.app?.name ?? 'P2P');
const app_slogan = computed(() => page.props.app?.slogan ?? '');

const mobile_open = ref(false);
const connect_missing_modal = ref(null);

const close_mobile = () => {
    mobile_open.value = false;
};

const open_connect_telegram = () => {
    if (props.connect_telegram_url) {
        window.open(props.connect_telegram_url, '_blank', 'noopener,noreferrer');
        return;
    }
    connect_missing_modal.value?.showModal();
};
</script>

<template>
    <Head>
        <title>{{ app_name }} — P2P-платежи в USDT для бизнеса и трейдеров</title>
        <meta
            name="description"
            content="Приём и вывод фиата через сеть трейдеров с расчётами в USDT. API для мерчантов, прозрачные статусы и сопровождение."
        />
    </Head>

    <LandingAmbientBackground />

    <div class="relative z-10">
        <a
            href="#main"
            class="absolute left-[-9999px] z-[100] inline-block overflow-hidden whitespace-nowrap rounded-btn focus:fixed focus:left-4 focus:top-4 focus:h-auto focus:w-auto focus:overflow-visible focus:border focus:border-base-300 focus:bg-base-200 focus:px-4 focus:py-2 focus:text-sm focus:text-base-content"
            >К содержимому</a
        >

        <header
            class="sticky top-0 z-50 mx-0 border-b border-base-300 bg-base-100/85 backdrop-blur-md md:top-4 md:mx-4 md:rounded-2xl md:border md:shadow-lg md:shadow-black/20"
        >
            <div class="navbar mx-auto min-h-[4.25rem] max-w-6xl px-4 py-2 md:px-6">
                <div class="navbar-start min-w-0 flex-1 gap-3 md:flex-none">
                    <div class="dropdown md:hidden" :class="{ 'dropdown-open': mobile_open }">
                        <div
                            tabindex="0"
                            role="button"
                            class="btn btn-ghost btn-circle btn-lg border border-base-300 text-base-content hover:bg-base-200"
                            aria-label="Открыть меню"
                            :aria-expanded="mobile_open ? 'true' : 'false'"
                            @click="mobile_open = !mobile_open"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </div>
                        <ul
                            tabindex="0"
                            class="menu menu-sm dropdown-content rounded-box z-[60] mt-3 w-52 border border-base-300 bg-base-200 p-2 shadow-lg"
                        >
                            <li>
                                <a href="#features" class="text-base-content" @click="close_mobile">Возможности</a>
                            </li>
                            <li>
                                <a href="#stats" class="text-base-content" @click="close_mobile">Сводка</a>
                            </li>
                            <li>
                                <a href="#how" class="text-base-content" @click="close_mobile">Как это работает</a>
                            </li>
                            <li>
                                <a href="#audience" class="text-base-content" @click="close_mobile">Для кого</a>
                            </li>
                            <li>
                                <a href="#trust" class="text-base-content" @click="close_mobile">Надёжность</a>
                            </li>
                        </ul>
                    </div>
                    <div
                        class="hidden h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primary to-accent text-primary-content sm:grid"
                        aria-hidden="true"
                    >
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2L4 6v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V6l-8-4z"
                                fill-opacity="0.35"
                            />
                            <path d="M12 22c4.5-1.5 8-6 8-11V6l-8-4v20z" />
                        </svg>
                    </div>
                    <div class="min-w-0 leading-tight">
                        <span class="block truncate text-2xl font-bold tracking-tight text-base-content sm:text-3xl">
                            {{ app_name }}
                        </span>
                        <span
                            v-if="app_slogan"
                            class="mt-0.5 hidden text-sm font-medium text-base-content/65 md:block"
                            >{{ app_slogan }}</span
                        >
                    </div>
                </div>
                <div class="navbar-center hidden shrink-0 md:flex">
                    <ul class="menu menu-horizontal gap-0.5 px-1 text-base font-medium text-base-content/75">
                        <li>
                            <a href="#features" class="cursor-pointer rounded-btn hover:bg-base-200 hover:text-base-content"
                                >Возможности</a
                            >
                        </li>
                        <li>
                            <a href="#stats" class="cursor-pointer rounded-btn hover:bg-base-200 hover:text-base-content"
                                >Сводка</a
                            >
                        </li>
                        <li>
                            <a href="#how" class="cursor-pointer rounded-btn hover:bg-base-200 hover:text-base-content"
                                >Как это работает</a
                            >
                        </li>
                        <li>
                            <a href="#audience" class="cursor-pointer rounded-btn hover:bg-base-200 hover:text-base-content"
                                >Для кого</a
                            >
                        </li>
                        <li>
                            <a href="#trust" class="cursor-pointer rounded-btn hover:bg-base-200 hover:text-base-content"
                                >Надёжность</a
                            >
                        </li>
                    </ul>
                </div>
                <div class="navbar-end shrink-0">
                    <a
                        v-if="connect_telegram_url"
                        :href="connect_telegram_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn border-0 bg-gradient-to-r from-warning to-secondary px-6 text-base font-bold text-slate-900 shadow-lg shadow-warning/25 transition duration-200 hover:brightness-110 hover:shadow-xl hover:shadow-warning/20 md:px-7"
                    >
                        Подключиться
                    </a>
                    <button
                        v-else
                        type="button"
                        class="btn border-0 bg-gradient-to-r from-warning to-secondary px-6 text-base font-bold text-slate-900 shadow-lg shadow-warning/25 transition duration-200 hover:brightness-110 hover:shadow-xl hover:shadow-warning/20 md:px-7"
                        @click="open_connect_telegram"
                    >
                        Подключиться
                    </button>
                </div>
            </div>
        </header>

        <main id="main">
            <!-- Герой: копирайт проекта + «абстрактная» панель-превью -->
            <section class="relative mx-auto max-w-6xl px-4 pb-16 pt-10 md:px-6 md:pb-24 md:pt-14 lg:pt-16" aria-labelledby="hero-title">
                <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-14">
                    <div>
                        <p
                            class="inline-flex items-center gap-2 rounded-full border border-base-300 bg-base-200/60 px-3 py-1.5 text-sm font-medium text-base-content/80"
                        >
                            <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"
                                />
                            </svg>
                            P2P · фиат и USDT в одном контуре
                        </p>
                        <h1
                            id="hero-title"
                            class="mt-5 text-5xl font-bold leading-[1.08] tracking-tight text-base-content md:text-6xl"
                        >
                            Деньги двигаются
                            <span class="text-primary">быстрее хаоса</span>
                        </h1>
                        <p class="mt-5 max-w-xl text-lg leading-relaxed text-base-content/70 md:text-xl">
                            {{ app_name }} связывает мерчантов и трейдеров: приём и выплаты в фиате, учёт в USDT, API и
                            webhooks — без лишней операционки между вашим продуктом и ликвидностью.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <a
                                v-if="connect_telegram_url"
                                :href="connect_telegram_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn group border-0 bg-gradient-to-r from-info to-primary px-8 text-base font-bold text-slate-900 shadow-lg shadow-info/30 transition duration-200 hover:brightness-110 hover:shadow-xl hover:shadow-info/25"
                            >
                                Подключиться в Telegram
                                <svg
                                    class="ml-2 h-5 w-5 transition-transform duration-200 group-hover:translate-x-0.5 motion-reduce:group-hover:translate-x-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </a>
                            <button
                                v-else
                                type="button"
                                class="btn group border-0 bg-gradient-to-r from-info to-primary px-8 text-base font-bold text-slate-900 shadow-lg shadow-info/30 transition duration-200 hover:brightness-110 hover:shadow-xl hover:shadow-info/25"
                                @click="open_connect_telegram"
                            >
                                Подключиться в Telegram
                                <svg
                                    class="ml-2 h-5 w-5 transition-transform duration-200 group-hover:translate-x-0.5 motion-reduce:group-hover:translate-x-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                            </button>
                            <a
                                href="#features"
                                class="btn btn-outline cursor-pointer border-base-300 text-base font-semibold text-base-content hover:border-primary/40 hover:bg-base-200"
                            >
                                Смотреть возможности
                            </a>
                        </div>
                        <div class="mt-10 flex flex-wrap gap-4 text-sm text-base-content/70" role="list">
                            <span class="inline-flex items-center gap-2" role="listitem">
                                <svg class="h-5 w-5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                                    />
                                </svg>
                                Роли и разграничение доступа
                            </span>
                            <span class="inline-flex items-center gap-2" role="listitem">
                                <svg class="h-5 w-5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                    <path d="M7 11V7a5 5 0 0110 0v4" />
                                </svg>
                                Webhooks и статусы сделок
                            </span>
                            <span class="inline-flex items-center gap-2" role="listitem">
                                <svg class="h-5 w-5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 6v6l4 2" />
                                </svg>
                                Сопровождение в рабочие часы
                            </span>
                        </div>
                    </div>
                    <div
                        class="relative overflow-hidden rounded-2xl border border-base-300 bg-base-200/80 p-6 shadow-2xl shadow-black/25 backdrop-blur-sm md:p-8"
                        aria-label="Сводка оборота и показателей за сегодня"
                    >
                        <div
                            class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-gradient-to-br from-primary/25 to-accent/20 blur-3xl"
                            aria-hidden="true"
                        />
                        <div class="relative">
                            <div class="flex items-start justify-between gap-4 border-b border-base-300 pb-4">
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wider text-base-content/50">Сводка</div>
                                    <div class="mt-1 text-2xl font-bold tabular-nums text-base-content md:text-3xl">
                                        {{ props.landing_stats.orders_total_usdt }}
                                    </div>
                                    <div class="text-sm text-base-content/60">Оборот всех сделок в USDT</div>
                                </div>
                                <span class="badge badge-success badge-lg shrink-0">Live</span>
                            </div>
                            <p class="mt-4 text-xs leading-snug text-base-content/55">
                                {{ props.landing_stats.period_label }}
                            </p>
                            <div class="mt-3 space-y-3 text-sm">
                                <div class="flex items-center justify-between rounded-xl bg-base-100/50 px-4 py-3">
                                    <span class="text-base-content/70">Объём за сегодня</span>
                                    <strong class="tabular-nums text-base-content">{{ props.landing_stats.today.api_volume_usdt }}</strong>
                                </div>
                                <div class="flex items-center justify-between rounded-xl bg-base-100/50 px-4 py-3">
                                    <span class="text-base-content/70">Объём выплат за сегодня</span>
                                    <strong class="tabular-nums text-base-content">{{ props.landing_stats.today.payouts_volume_usdt }}</strong>
                                </div>
                                <div class="flex items-center justify-between rounded-xl bg-base-100/50 px-4 py-3">
                                    <span class="text-base-content/70">Среднее время до успешного закрытия</span>
                                    <strong class="tabular-nums text-base-content">{{ avg_processing_label }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Полоса чипов как в l.test2 -->
            <section class="border-y border-base-300 bg-base-200/35 py-4" aria-label="Технологии и сценарии">
                <div class="mx-auto flex max-w-6xl flex-wrap justify-center gap-3 px-4 md:px-6">
                    <span class="rounded-full border border-base-300 bg-base-100/60 px-4 py-2 text-sm font-medium text-base-content/80"
                        >REST / Webhooks</span
                    >
                    <span class="rounded-full border border-base-300 bg-base-100/60 px-4 py-2 text-sm font-medium text-base-content/80"
                        >USDT</span
                    >
                    <span class="rounded-full border border-base-300 bg-base-100/60 px-4 py-2 text-sm font-medium text-base-content/80"
                        >P2P-сделки</span
                    >
                    <span class="rounded-full border border-base-300 bg-base-100/60 px-4 py-2 text-sm font-medium text-base-content/80"
                        >Фиат для клиентов</span
                    >
                    <span class="rounded-full border border-base-300 bg-base-100/60 px-4 py-2 text-sm font-medium text-base-content/80"
                        >Роли в кабинете</span
                    >
                </div>
            </section>

            <!-- Краткая сводка-цифры (без выдуманных SLA) -->
            <section id="stats" class="scroll-mt-28 border-b border-base-300 py-14 md:py-16">
                <div class="mx-auto max-w-6xl px-4 md:px-6">
                    <h2 class="sr-only">Ключевые характеристики</h2>
                    <div class="grid grid-cols-2 gap-6 md:grid-cols-4 md:gap-8">
                        <div class="border-l-4 border-primary pl-4">
                            <div class="text-2xl font-bold tabular-nums text-base-content md:text-3xl">P2P</div>
                            <div class="mt-1 text-sm text-base-content/60">сеть исполнителей</div>
                        </div>
                        <div class="border-l-4 border-secondary pl-4">
                            <div class="text-2xl font-bold tabular-nums text-base-content md:text-3xl">USDT</div>
                            <div class="mt-1 text-sm text-base-content/60">расчёты в стейблкоине</div>
                        </div>
                        <div class="border-l-4 border-info pl-4">
                            <div class="text-2xl font-bold tabular-nums text-base-content md:text-3xl">API</div>
                            <div class="mt-1 text-sm text-base-content/60">интеграция и callback</div>
                        </div>
                        <div class="border-l-4 border-success pl-4">
                            <div class="text-2xl font-bold tabular-nums text-base-content md:text-3xl">24/7</div>
                            <div class="mt-1 text-sm text-base-content/60">онлайн-статус трейдеров</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bento: структура из l.test2 + смысл P2P -->
            <section id="features" class="scroll-mt-28 border-t border-base-300 bg-base-200/40 py-20 md:py-24">
                <div class="mx-auto max-w-6xl px-4 md:px-6">
                    <p class="text-sm font-semibold uppercase tracking-wider text-primary">Возможности</p>
                    <h2 class="mt-2 text-3xl font-bold text-base-content md:text-4xl">
                        Платёжный слой, который не мешает масштабироваться
                    </h2>
                    <p class="mt-3 max-w-2xl text-base-content/70">
                        Модули под ваш этап: от приёма и выплат до диспутов и отчётности — в одной панели с
                        разграничением ролей.
                    </p>

                    <div class="mt-12 grid grid-cols-12 gap-4">
                        <article
                            class="col-span-12 flex flex-col rounded-2xl border border-base-300 bg-gradient-to-br from-accent/15 via-base-200 to-base-200 p-6 md:col-span-8 md:row-start-1 md:min-h-[200px]"
                        >
                            <div
                                class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-accent/20 text-accent"
                                aria-hidden="true"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-base-content">Комиссии, лимиты, резервы</h3>
                            <p class="mt-2 max-w-prose text-sm leading-relaxed text-base-content/70">
                                Прозрачные правила по сделкам и выплатам, контроль оборота и уведомления при отклонениях
                                от нормы — чтобы команда не ловила сюрпризы вручную.
                            </p>
                        </article>

                        <article
                            class="col-span-12 flex flex-col rounded-2xl border border-base-300 bg-base-200 p-6 md:col-span-4 md:row-span-2 md:row-start-1 md:min-h-[280px]"
                        >
                            <div
                                class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-primary/15 text-primary"
                                aria-hidden="true"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <polyline points="16 18 22 12 16 6" />
                                    <polyline points="8 6 2 12 8 18" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-base-content">API и webhooks</h3>
                            <p class="mt-2 text-sm leading-relaxed text-base-content/70">
                                Создание платежей и выплат из вашего бэкенда, подписанные callback-и и повторная доставка
                                статусов — как в вашем абстрактном макете, но завязано на реальные сущности сервиса.
                            </p>
                        </article>

                        <article
                            class="col-span-12 rounded-2xl border border-base-300 bg-base-200 p-6 md:col-span-4 md:row-start-2"
                        >
                            <div
                                class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-info/15 text-info"
                                aria-hidden="true"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="2" y1="12" x2="22" y2="12" />
                                    <path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-base-content">Фиат и USDT</h3>
                            <p class="mt-2 text-sm text-base-content/70">
                                Клиент платит привычным способом, учёт идёт в USDT — меньше ручных сверок между
                                казначейством и продуктом.
                            </p>
                        </article>

                        <article
                            class="col-span-12 rounded-2xl border border-base-300 bg-base-200 p-6 md:col-span-4 md:col-start-5 md:row-start-2"
                        >
                            <div
                                class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-secondary/15 text-secondary"
                                aria-hidden="true"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                    <line x1="12" y1="22.08" x2="12" y2="12" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-base-content">Контроль и диспуты</h3>
                            <p class="mt-2 text-sm text-base-content/70">
                                Жизненный цикл заявки, разбор спорных кейсов и права ролей — в одной логике с вашим
                                текущим бэкофисом.
                            </p>
                        </article>

                        <article
                            class="col-span-12 rounded-2xl border border-base-300 bg-base-200 p-6 md:col-span-8 md:row-start-3"
                        >
                            <div
                                class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-success/15 text-success"
                                aria-hidden="true"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="20" x2="18" y2="10" />
                                    <line x1="12" y1="20" x2="12" y2="4" />
                                    <line x1="6" y1="20" x2="6" y2="14" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-base-content">Сводки в кабинете</h3>
                            <p class="mt-2 text-sm text-base-content/70">
                                Обороты, заявки и выплаты в разрезе ролей; меньше выгрузок «руками» в таблицы — ближе к
                                идее аналитического слоя из вашего CTIPay-макета, без обещаний чужой BI из коробки.
                            </p>
                        </article>

                        <article
                            class="col-span-12 rounded-2xl border border-base-300 bg-base-200 p-6 md:col-span-4 md:row-start-3 md:col-start-9"
                        >
                            <div
                                class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-warning/15 text-warning"
                                aria-hidden="true"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-base-content">Скорость обработки</h3>
                            <p class="mt-2 text-sm text-base-content/70">
                                Очереди заявок и уведомления, чтобы команда трейдеров и саппорта не терялась в потоке.
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="how" class="scroll-mt-28 py-20 md:py-24">
                <div class="mx-auto max-w-6xl px-4 md:px-6">
                    <p class="text-sm font-semibold uppercase tracking-wider text-secondary">Процесс</p>
                    <h2 class="mt-2 text-3xl font-bold text-base-content md:text-4xl">От заявки до статуса в вашей системе</h2>
                    <p class="mt-3 max-w-2xl text-base-content/70">
                        Три шага без лишней бюрократии — в духе вашего лендинга, но под реальную модель P2P.
                    </p>
                    <ol class="mt-12 grid gap-6 md:grid-cols-3">
                        <li
                            class="relative rounded-2xl border border-base-300 bg-gradient-to-b from-base-200/90 to-base-100/30 p-6 pt-12"
                        >
                            <span
                                class="absolute left-6 top-0 font-mono text-3xl font-bold leading-none text-base-content/25"
                                >01</span
                            >
                            <h3 class="font-semibold text-base-content">Заявка</h3>
                            <p class="mt-2 text-sm text-base-content/70">
                                Мерчант создаёт платёж или выплату; система уведомляет стороны и фиксирует параметры
                                сделки.
                            </p>
                        </li>
                        <li
                            class="relative rounded-2xl border border-base-300 bg-gradient-to-b from-base-200/90 to-base-100/30 p-6 pt-12"
                        >
                            <span
                                class="absolute left-6 top-0 font-mono text-3xl font-bold leading-none text-base-content/25"
                                >02</span
                            >
                            <h3 class="font-semibold text-base-content">Фиат и подтверждение</h3>
                            <p class="mt-2 text-sm text-base-content/70">
                                Клиент платит удобным способом; трейдер или процессинг подтверждает поступление по
                                правилам сервиса.
                            </p>
                        </li>
                        <li
                            class="relative rounded-2xl border border-base-300 bg-gradient-to-b from-base-200/90 to-base-100/30 p-6 pt-12"
                        >
                            <span
                                class="absolute left-6 top-0 font-mono text-3xl font-bold leading-none text-base-content/25"
                                >03</span
                            >
                            <h3 class="font-semibold text-base-content">USDT и webhook</h3>
                            <p class="mt-2 text-sm text-base-content/70">
                                Итог по балансам в USDT и доставка статуса в ваш бэкенд — замыкание контура для продукта.
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            <section id="audience" class="scroll-mt-28 border-t border-base-300 bg-base-200/30 py-20 md:py-24">
                <div class="mx-auto max-w-6xl px-4 md:px-6">
                    <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
                        <div>
                            <h2 class="text-3xl font-bold text-base-content md:text-4xl">Для мерчантов и трейдеров</h2>
                            <p class="mt-4 text-base-content/70">
                                Одна экосистема: бизнес масштабирует приём и выплаты, исполнители получают доступ к
                                ликвидности и понятной мотивации в USDT.
                            </p>
                            <ul class="mt-8 space-y-4 text-base-content/80">
                                <li class="flex gap-3">
                                    <span class="mt-1 text-primary" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                    <span
                                        ><strong class="text-base-content">Мерчантам</strong> — единый API, статусы для
                                        поддержки и снижение операционной нагрузки.</span
                                    >
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1 text-accent" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                    <span
                                        ><strong class="text-base-content">Трейдерам</strong> — реквизиты, лимиты, выплаты
                                        и прозрачная работа с объёмом.</span
                                    >
                                </li>
                            </ul>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div
                                class="rounded-2xl border border-warning/25 bg-gradient-to-br from-warning/10 to-base-100/0 p-6"
                            >
                                <div class="badge badge-outline border-warning/40 text-warning">Мерчант</div>
                                <p class="mt-4 text-sm text-base-content/80">
                                    Приём и массовые выплаты клиентам, оборот и callback-и из одного кабинета.
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-accent/25 bg-gradient-to-br from-accent/10 to-base-100/0 p-6"
                            >
                                <div class="badge badge-outline border-accent/40 text-accent">Трейдер</div>
                                <p class="mt-4 text-sm text-base-content/80">
                                    Реквизиты, онлайн-статус, заявки и вывод вознаграждения в USDT.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="trust" class="scroll-mt-28 py-20 md:py-24">
                <div class="mx-auto max-w-6xl px-4 md:px-6">
                    <div
                        class="overflow-hidden rounded-3xl border border-base-300 bg-gradient-to-r from-base-200 via-base-200/80 to-base-200 p-8 md:p-12"
                    >
                        <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                            <div>
                                <h2 class="text-2xl font-bold text-base-content md:text-3xl">Надёжность — в деталях процесса</h2>
                                <p class="mt-4 text-base-content/70">
                                    Разграничение ролей, журналы операций и контроль спорных ситуаций помогают держать
                                    сервис предсказуемым при росте нагрузки.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <span class="badge h-10 border-success/30 bg-success/10 px-4 text-success">Шифрование и сессии</span>
                                <span class="badge h-10 border-info/30 bg-info/10 px-4 text-info">Аудит действий</span>
                                <span class="badge h-10 border-warning/30 bg-warning/10 px-4 text-warning">2FA в кабинете</span>
                                <span class="badge h-10 border-accent/30 bg-accent/10 px-4 text-accent"
                                    >Разделение доменов</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA-лента в духе l.test2 -->
            <section
                class="mx-auto mb-20 max-w-6xl scroll-mt-28 px-4 md:mb-24 md:px-6"
                aria-labelledby="cta-title"
            >
                <div
                    class="rounded-3xl border border-base-300 bg-gradient-to-br from-base-200 via-base-200 to-accent/10 p-8 text-center md:p-12"
                >
                    <h2 id="cta-title" class="text-3xl font-bold text-base-content md:text-4xl">
                        Готовы замкнуть денежный контур?
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-base-content/70">
                        Напишите нам в Telegram — обсудим подключение мерчанта или трейдера и ответим на вопросы по
                        сервису.
                    </p>
                    <div class="mt-8 flex flex-wrap justify-center gap-3">
                        <a
                            v-if="connect_telegram_url"
                            :href="connect_telegram_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn border-0 bg-gradient-to-r from-accent via-primary to-info px-8 text-base font-bold text-slate-900 shadow-lg shadow-accent/30 transition duration-200 hover:brightness-110 md:px-10"
                        >
                            Написать в Telegram
                        </a>
                        <button
                            v-else
                            type="button"
                            class="btn border-0 bg-gradient-to-r from-accent via-primary to-info px-8 text-base font-bold text-slate-900 shadow-lg shadow-accent/30 transition duration-200 hover:brightness-110 md:px-10"
                            @click="open_connect_telegram"
                        >
                            Написать в Telegram
                        </button>
                        <a
                            href="#features"
                            class="btn btn-outline border-base-300 px-8 text-base font-semibold text-base-content hover:bg-base-200 md:px-10"
                        >
                            К возможностям
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-base-300 bg-base-200/50 py-10">
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 text-center text-sm text-base-content/50 md:flex-row md:text-left md:px-6"
            >
                <span class="font-semibold text-base-content/70">{{ app_name }}</span>
                <div class="flex flex-col items-center gap-2 md:items-end">
                    <p>© {{ new Date().getFullYear() }} · Маркетинговая страница P2P-сервиса</p>
                    <Link :href="route('login')" class="link link-hover text-base-content/70 text-xs font-medium">
                        Вход для участников
                    </Link>
                </div>
            </div>
        </footer>

        <dialog
            ref="connect_missing_modal"
            class="modal modal-bottom sm:modal-middle"
            aria-labelledby="connect-missing-title"
        >
            <div class="modal-box border border-base-300 bg-base-200 text-base-content">
                <h3 id="connect-missing-title" class="text-lg font-bold">Ссылка на Telegram не настроена</h3>
                <p class="mt-3 text-sm text-base-content/70">
                    Укажите публичную ссылку на чат или канал в админке: раздел настроек — поле «Ссылка поддержки»
                    (HTTPS, например <span class="whitespace-nowrap">https://t.me/…</span>). После сохранения кнопка
                    «Подключиться» откроет её в новой вкладке.
                </p>
                <div class="modal-action">
                    <form method="dialog">
                        <button type="submit" class="btn btn-primary">Понятно</button>
                    </form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit" class="hidden">Закрыть</button>
            </form>
        </dialog>
    </div>
</template>
