<script setup>
import {Link, router, usePage} from "@inertiajs/vue3";
import {computed, onMounted, ref} from "vue";
import {useViewStore} from "@/store/view.js";
import ThemeToggle from "@/Components/ThemeToggle.vue";

const viewStore = useViewStore();

const wallet = ref(usePage().props.data.wallet);
const authUser = ref(usePage().props.auth.user);
const isTraderTopOpen = ref(false);
const isTraderTopLoading = ref(false);
const traderTopError = ref('');
const traderTopLoaded = ref(false);
const traderTopData = ref({
    top: [],
    current_trader: null,
    reset_at: null,
});
const hideNameInTraderTop = ref(Boolean(authUser.value?.hide_name_in_trader_top ?? true));

const emit = defineEmits(['toggleSidebar']);
const toggleSidebar = () => {
    emit('toggleSidebar');
}

const formatNumber = (num) => { //TODO move to utils
    // Округляем до двух знаков после запятой, если есть дробная часть
    const roundedNum = Math.round(num * 100) / 100;

    // Форматируем число с разделителями тысяч
    return roundedNum.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const walletFormated = computed(() => {
    return {
        merchant_balance: formatNumber(wallet.value.merchant_balance),
        trust_balance: formatNumber(wallet.value.trust_balance),
        reserve_balance: formatNumber(wallet.value.reserve_balance),
    }
});
const walletStats = computed(() => usePage().props.walletStats ?? usePage().props.data?.wallet_stats ?? null);

const traderFinanceOverview = computed(() => {
    const stats = walletStats.value;

    return {
        secondaryCurrency: (stats?.currency?.secondary ?? 'RUB').toUpperCase(),
        trustReserveAmount: stats?.base?.trustReserveAmount ?? walletFormated.value.reserve_balance,
        trustWithdrawalAmount: stats?.lockedForWithdrawalBalances?.trust?.primary ?? '0',
        escrowOrdersPrimary: stats?.escrowBalances?.orders?.balance?.primary ?? '0',
        escrowOrdersSecondary: stats?.escrowBalances?.orders?.balance?.secondary ?? '0',
        escrowOrdersCount: stats?.escrowBalances?.orders?.count ?? 0,
        escrowDisputesPrimary: stats?.escrowBalances?.disputes?.balance?.primary ?? '0',
        escrowDisputesSecondary: stats?.escrowBalances?.disputes?.balance?.secondary ?? '0',
        escrowDisputesCount: stats?.escrowBalances?.disputes?.count ?? 0,
    };
});

const merchantFinanceOverview = computed(() => {
    const stats = walletStats.value;

    return {
        merchantWithdrawalAmount: stats?.lockedForWithdrawalBalances?.merchant?.primary ?? '0',
    };
});

const activeFinanceRoute = computed(() => {
    if (viewStore.isMerchantViewMode) {
        return route('merchant.finances.index');
    }
    return route('wallet.index');
});

const activeFinanceTitle = computed(() => {
    return viewStore.isMerchantViewMode ? 'Финансы мерчанта' : 'Финансы трейдера';
});

const role = usePage().props.auth.role;
const email = usePage().props.auth.user.email;

const login = computed(() =>
    email.charAt(0).toUpperCase() + email.slice(1)
)

const currentTraderTopRankLabel = computed(() => {
    const rank = authUser.value?.weekly_top_rank ?? null;

    if (!rank) {
        return 'Вне топа';
    }

    return `${rank} место`;
});

const shouldShowCurrentTraderSeparated = computed(() => {
    const current = traderTopData.value.current_trader;
    return !!current && Number(current.rank) > 10;
});

const getTraderLeaderboardIndexUrl = () => {
    try {
        return route('trader.leaderboard.index');
    } catch (error) {
        return '/trader/leaderboard';
    }
};

const getTraderLeaderboardHideNameUrl = () => {
    try {
        return route('trader.leaderboard.hide-name.update');
    } catch (error) {
        return '/trader/leaderboard/hide-name';
    }
};

const formatResetAt = computed(() => {
    if (!traderTopData.value.reset_at) {
        return '-';
    }

    const date = new Date(traderTopData.value.reset_at);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const loadTraderTop = async () => {
    isTraderTopLoading.value = true;
    traderTopError.value = '';

    try {
        const response = await window.axios.get(getTraderLeaderboardIndexUrl(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = response?.data?.data ?? response?.data ?? {};

        const hasValidPayloadShape = payload !== null
            && typeof payload === 'object'
            && Array.isArray(payload?.top);

        if (!hasValidPayloadShape) {
            throw new Error('Некорректный формат ответа');
        }

        traderTopData.value = {
            top: payload.top,
            current_trader: payload?.current_trader ?? null,
            reset_at: payload?.reset_at ?? null,
        };
        traderTopLoaded.value = true;
    } catch (error) {
        traderTopError.value = error?.response?.data?.message ?? 'Не удалось загрузить топ трейдеров';
    } finally {
        isTraderTopLoading.value = false;
    }
};

const toggleTraderTop = async () => {
    isTraderTopOpen.value = !isTraderTopOpen.value;
};

const toggleHideNameInTop = async () => {
    const nextValue = !hideNameInTraderTop.value;
    hideNameInTraderTop.value = nextValue;

    try {
        const response = await window.axios.patch(getTraderLeaderboardHideNameUrl(), {
            hide_name_in_trader_top: nextValue,
        });

        const persistedValue = Boolean(response?.data?.hide_name_in_trader_top ?? nextValue);
        hideNameInTraderTop.value = persistedValue;

        if (authUser.value) {
            authUser.value.hide_name_in_trader_top = persistedValue;
        }
    } catch (error) {
        hideNameInTraderTop.value = !nextValue;
    }
};

const openFinancePage = () => {
    router.visit(activeFinanceRoute.value, { preserveScroll: true });
};

router.on('success', (event) => {
    wallet.value = usePage().props.data.wallet;
    authUser.value = usePage().props.auth.user;
    hideNameInTraderTop.value = Boolean(authUser.value?.hide_name_in_trader_top ?? true);
    isTraderTopOpen.value = false;
})

onMounted(async () => {
    if (!traderTopLoaded.value && !isTraderTopLoading.value) {
        await loadTraderTop();
    }
});

// Вынос переключателя темы в отдельный компонент
</script>

<template>
    <div class=" bg-base-100 shadow-sm z-50 w-full">
        <div class="navbar lg:container mx-auto px-4">
            <div class="flex-1">
                <div class="flex items-center justify-start rtl:justify-end">
                    <!--data-drawer-target="logo-sidebar" data-drawer-toggle="logo-sidebar" aria-controls="logo-sidebar"-->
                    <button
                        type="button"
                        class="btn btn-ghost btn-square mr-2 lg:hidden"
                        @click.prevent="toggleSidebar"
                    >
                        <span class="sr-only">Открыть меню</span>
                        <svg class="w-8 h-8" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
                        </svg>
                    </button>
                    <Link :href="route('dashboard')" class="flex ms-2 md:me-24 text-base-content">
                        <div class="hidden lg:block">
                            <div class="text-4xl font-semibold">{{$page.props.app.name}}</div>
                            <div class="text-xs font-medium text-base-content/70">{{$page.props.app.slogan}}</div>
                        </div>
                        <div class="lg:hidden">
                            <div class="text-[1.95rem] font-semibold">{{$page.props.app.name}}</div>
                            <div class="text-[0.65rem] text-base-content/70">{{$page.props.app.slogan}}</div>
                        </div>
                    </Link>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div v-show="viewStore.isMerchantViewMode" class="lg:block hidden">
                    <div class="dropdown dropdown-end">
                        <button
                            tabindex="0"
                            type="button"
                            role="button"
                            class="btn btn-ghost normal-case h-auto min-h-0 px-3 py-2 rounded-xl"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg class="w-6 h-6 text-primary shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                </svg>
                                <div class="font-semibold text-base-content text-nowrap flex items-center gap-2">
                                    <span class="text-lg leading-none">{{ walletFormated.merchant_balance }}</span>
                                    <span class="badge badge-ghost">USDT</span>
                                </div>
                            </div>
                        </button>
                        <div tabindex="0" class="dropdown-content z-[60] mt-2 w-80 max-w-[calc(100vw-2rem)] card bg-base-100 border border-base-300 shadow">
                            <div class="card-body p-4 space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-semibold text-sm">{{ activeFinanceTitle }}</h3>
                                    <button type="button" class="btn btn-xs btn-primary" @click="openFinancePage">Открыть</button>
                                </div>
                                <div class="rounded-lg bg-base-200 p-3">
                                    <p class="text-xs text-base-content/70">Доступный баланс</p>
                                    <p class="text-lg font-semibold">{{ walletFormated.merchant_balance }} USDT</p>
                                    <div class="mt-2 text-xs text-base-content/65">
                                        Ожидает вывода: {{ merchantFinanceOverview.merchantWithdrawalAmount }} USDT
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-show="viewStore.isTraderViewMode" class="lg:flex items-center hidden text-nowrap">
                    <div class="dropdown dropdown-end" :class="isTraderTopOpen ? 'dropdown-open' : ''">
                        <button
                            tabindex="0"
                            type="button"
                            role="button"
                            class="btn btn-sm btn-outline btn-accent normal-case h-auto min-h-0 py-1.5 px-3 leading-tight"
                            @click.prevent="toggleTraderTop"
                        >
                            <span class="flex flex-col items-start">
                                <span class="text-xs">Топ трейдеров</span>
                                <span class="text-[11px] opacity-70">{{ currentTraderTopRankLabel }}</span>
                            </span>
                        </button>
                        <div tabindex="0" class="card card-sm dropdown-content border border-accent/20 bg-base-100 rounded-box z-[60] mt-3 w-[26rem] max-w-[calc(100vw-2rem)] shadow whitespace-normal">
                            <div class="card-body p-4 space-y-3 whitespace-normal">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-sm">ТОП-10 трейдеров за неделю</h3>
                                        <p class="text-xs text-base-content/70 mt-1 leading-tight break-words whitespace-normal">
                                            При равном количестве сделок выше тот, у кого меньше среднее время обработки.
                                        </p>
                                        <p class="text-xs text-base-content/70 break-words whitespace-normal">
                                            Сброс топа: {{ formatResetAt }}
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-ghost btn-xs shrink-0" @click.prevent="isTraderTopOpen = false">Закрыть</button>
                                </div>

                                <div v-if="isTraderTopLoading" class="flex justify-center py-4">
                                    <span class="loading loading-spinner loading-sm"></span>
                                </div>
                                <div v-else-if="traderTopError" class="alert alert-error py-2">
                                    <span class="text-xs">{{ traderTopError }}</span>
                                </div>
                                <div v-else-if="!traderTopData.top.length" class="alert py-2">
                                    <span class="text-xs">За последние 7 дней пока нет данных для топа.</span>
                                </div>
                                <div v-else class="space-y-1">
                                    <div class="grid grid-cols-[2.25rem_minmax(0,1fr)_6rem_7rem] items-center gap-2 px-2 pb-1 border-b border-base-300">
                                        <span class="text-[10px] uppercase tracking-wide text-base-content/60">#</span>
                                        <span class="text-[10px] uppercase tracking-wide text-base-content/60">Трейдер</span>
                                        <span class="text-[10px] uppercase tracking-wide text-base-content/60 text-right whitespace-nowrap">Сделки</span>
                                        <span class="text-[10px] uppercase tracking-wide text-base-content/60 text-right whitespace-nowrap">Среднее время</span>
                                    </div>
                                    <div
                                        v-for="item in traderTopData.top"
                                        :key="`top-${item.trader_id}`"
                                        class="grid grid-cols-[2.25rem_minmax(0,1fr)_6rem_7rem] items-center gap-2 rounded-lg px-2 py-1.5"
                                        :class="Number(item.trader_id) === Number(authUser?.id ?? 0) ? 'bg-primary/10 border border-primary/20' : 'bg-base-200/40'"
                                    >
                                        <span class="text-xs font-semibold">#{{ item.rank }}</span>
                                        <span class="text-xs truncate">{{ item.nickname }}</span>
                                        <span class="text-xs text-right whitespace-nowrap tabular-nums">{{ item.operations_count }} сделок</span>
                                        <span class="text-xs text-right whitespace-nowrap tabular-nums">{{ item.avg_processing_human }}</span>
                                    </div>

                                    <div v-if="shouldShowCurrentTraderSeparated" class="space-y-1 pt-1">
                                        <div class="flex items-center gap-2 px-2">
                                            <div class="h-px flex-1 bg-base-300"></div>
                                            <span class="text-[10px] uppercase tracking-wide text-base-content/50">другие трейдеры</span>
                                            <div class="h-px flex-1 bg-base-300"></div>
                                        </div>

                                        <div
                                            class="grid grid-cols-[2.25rem_minmax(0,1fr)_6rem_7rem] items-center gap-2 rounded-lg px-2 py-1.5 bg-primary/10 border border-primary/20"
                                        >
                                            <span class="text-xs font-semibold">#{{ traderTopData.current_trader.rank }}</span>
                                            <span class="text-xs truncate">{{ traderTopData.current_trader.nickname }}</span>
                                            <span class="text-xs text-right whitespace-nowrap tabular-nums">{{ traderTopData.current_trader.operations_count }} сделок</span>
                                            <span class="text-xs text-right whitespace-nowrap tabular-nums">{{ traderTopData.current_trader.avg_processing_human }}</span>
                                        </div>
                                    </div>

                                    <div class="border-t border-base-300 pt-2 mt-2 flex items-center justify-between">
                                        <span class="text-xs">Скрыть мое имя в топе</span>
                                        <input
                                            type="checkbox"
                                            class="toggle toggle-sm toggle-primary"
                                            :checked="hideNameInTraderTop"
                                            @change="toggleHideNameInTop"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown dropdown-end ml-6">
                        <button
                            tabindex="0"
                            type="button"
                            role="button"
                            class="btn btn-ghost normal-case h-auto min-h-0 px-3 py-2 rounded-xl"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg class="w-6 h-6 text-primary shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                </svg>
                                <div class="font-semibold text-base-content text-nowrap flex items-center gap-2">
                                    <span class="text-lg leading-none">{{ walletFormated.trust_balance }}</span>
                                    <span class="badge badge-ghost">USDT</span>
                                </div>
                                <span class="inline-flex items-center text-sm px-3 py-1.5 rounded-full badge badge-outline">
                                    <svg class="w-4 h-4 mr-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
                                    </svg>
                                    {{ walletFormated.reserve_balance }} USDT
                                </span>
                            </div>
                        </button>
                        <div tabindex="0" class="dropdown-content z-[60] mt-2 w-80 max-w-[calc(100vw-2rem)] card bg-base-100 border border-base-300 shadow">
                            <div class="card-body p-4 space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-semibold text-sm">{{ activeFinanceTitle }}</h3>
                                    <button type="button" class="btn btn-xs btn-primary" @click="openFinancePage">Открыть</button>
                                </div>
                                <div class="grid gap-2">
                                    <div class="rounded-lg bg-base-200 p-3">
                                        <p class="text-xs text-base-content/70">Траст баланс</p>
                                        <p class="text-base font-semibold">{{ walletFormated.trust_balance }} USDT</p>
                                        <div class="mt-2 space-y-1 text-xs text-base-content/65">
                                            <p>Резерв: {{ traderFinanceOverview.trustReserveAmount }} USDT</p>
                                            <p>Ожидает вывода: {{ traderFinanceOverview.trustWithdrawalAmount }} USDT</p>
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-base-200 p-3">
                                        <p class="text-xs text-base-content/70">Холд в сделках</p>
                                        <p class="text-sm font-semibold">
                                            {{ traderFinanceOverview.escrowOrdersPrimary }} USDT
                                        </p>
                                        <p class="text-xs text-base-content/70 mt-0.5">
                                            ≈ {{ traderFinanceOverview.escrowOrdersSecondary }} {{ traderFinanceOverview.secondaryCurrency }} -
                                            Сделок: {{ traderFinanceOverview.escrowOrdersCount }}
                                        </p>
                                    </div>
                                    <div class="rounded-lg bg-base-200 p-3">
                                        <p class="text-xs text-base-content/70">Холд в спорах</p>
                                        <p class="text-sm font-semibold">
                                            {{ traderFinanceOverview.escrowDisputesPrimary }} USDT
                                        </p>
                                        <p class="text-xs text-base-content/70 mt-0.5">
                                            ≈ {{ traderFinanceOverview.escrowDisputesSecondary }} {{ traderFinanceOverview.secondaryCurrency }} -
                                            Споров: {{ traderFinanceOverview.escrowDisputesCount }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="dropdown dropdown-end">
                        <div tabindex="0" role="button" class="flex items-center space-x-4 cursor-pointer py-3 px-4 pr-2 rounded-xl hover:bg-base-300">
                            <!--                                <div class="flex text-sm bg-base-100 rounded-full">
                                                                <span class="sr-only">Open user menu</span>
                                                                <img :src="'https://api.dicebear.com/9.x/'+$page.props.auth.user.avatar_style+'/svg?seed='+$page.props.auth.user.avatar_uuid" class="w-15 h-15 rounded-full border border-base-300" alt="user photo">
                                                            </div>-->
                            <div class="avatar">
                                <div class="ring-primary ring-offset-base-100 bg-base-300 w-12 rounded-full ring-2 ring-offset-2">
                                    <img :src="'https://api.dicebear.com/9.x/'+$page.props.auth.user.avatar_style+'/svg?seed='+$page.props.auth.user.avatar_uuid" alt="user photo">
                                </div>
                            </div>
                            <div class="sm:block hidden">
                                <p class="text-lg text-base-content" role="none">
                                    {{ login }}
                                </p>
                                <p class="text-md text-base-content/50" role="none">
                                    {{ role.name }}
                                </p>
                            </div>
                            <div class="sm:block hidden">
                                <svg class="w-6 h-4 text-base-content" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/>
                                </svg>
                            </div>
                        </div>
                        <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] w-55 lg:w-45 p-2 shadow bg-base-100 rounded-box">
                            <li class="lg:hidden block menu-title px-4">Пользователь</li>
                            <li class="lg:hidden block px-2 hover:bg-transparent active:bg-transparent focus:bg-transparent pointer-events-none">
                                <div class="text-base font-medium text-base-content/70 truncate">{{ login }}</div>
                                <div class="mt-2 block">
                                    <div v-show="viewStore.isMerchantViewMode" class="flex items-center">
                                        <svg class="w-5 h-5 text-primary mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                        </svg>
                                        <div class="font-semibold flex items-center gap-2">
                                            <span class="text-base text-base-content mr-1">{{ walletFormated.merchant_balance }}</span>
                                            <span class="badge badge-ghost badge-sm">USDT</span>
                                        </div>
                                    </div>
                                    <div v-show="viewStore.isTraderViewMode">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-primary mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                            </svg>
                                            <div class="font-semibold">
                                                <span class="text-base text-base-content mr-1">{{ walletFormated.trust_balance }}</span>
                                                <span class="badge badge-ghost badge-sm">USDT</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-show="viewStore.isTraderViewMode" class="flex items-center mt-2">
                                        <svg class="w-5 h-5 text-primary mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
                                        </svg>
                                        <div class="font-semibold">
                                            <span class="text-base text-base-content mr-1">{{ wallet.reserve_balance }}</span>
                                            <span class="badge badge-ghost badge-sm">USDT</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="menu-title px-4">Меню</li>
                            <li class="px-2">
                                <Link :href="route('profile.edit')" class="justify-start text-base">
                                    Профиль
                                </Link>
                            </li>
                            <li class="px-2">
                                <Link :href="route('logout')" method="post" class="justify-start text-base">
                                    Выход
                                </Link>
                            </li>
                            <li class="px-4"></li>
                            <div class="px-4">
                                <div class="block">
                                    <ThemeToggle />
                                </div>
                            </div>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <nav class="">
        <div>
            <div class="flex items-center justify-between">

            </div>
        </div>
    </nav>
</template>

<style scoped>

</style>
