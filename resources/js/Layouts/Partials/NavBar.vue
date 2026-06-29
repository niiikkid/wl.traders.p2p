<script setup>
import {Link, router, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";
import {useViewStore} from "@/store/view.js";
import {useUserStore} from "@/store/user.js";
import ViewModeSwitcher from "@/Layouts/Partials/ViewModeSwitcher.vue";
import UserAvatar from "@/Components/User/UserAvatar.vue";
import NewsDropdown from "@/Components/News/NewsDropdown.vue";
import NewsCreateModal from "@/Components/News/NewsCreateModal.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";

const viewStore = useViewStore();
const userStore = useUserStore();

const wallet = ref(usePage().props.data.wallet);

const emit = defineEmits(['toggleSidebar']);
const toggleSidebar = () => {
    emit('toggleSidebar');
}

const formatNumber = (num) => { //TODO move to utils
    const normalizedNum = Number(num ?? 0);

    // Округляем до двух знаков после запятой, если есть дробная часть
    const roundedNum = Math.round(normalizedNum * 100) / 100;

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
    if (viewStore.isMerchantViewMode) {
        return 'Финансы мерчанта';
    }
    return 'Финансы трейдера';
});

const usesTeamLeaderSharedReserve = computed(() => (
    viewStore.isTraderViewMode
    && usePage().props.auth.user?.uses_team_leader_shared_reserve === true
));

const role = usePage().props.auth.role;
const email = usePage().props.auth.user.email;
const canManageNews = computed(() => usePage().props.news?.canManage === true);
const showCreateNewsModal = ref(false);
const newsDropdownRef = ref(null);

const login = computed(() =>
    email.charAt(0).toUpperCase() + email.slice(1)
);

const openCreateNewsModal = () => {
    showCreateNewsModal.value = true;
};

const closeCreateNewsModal = () => {
    showCreateNewsModal.value = false;
};

const onNewsCreated = () => {
    newsDropdownRef.value?.refreshFeed?.();
};

const openFinancePage = () => {
    router.visit(activeFinanceRoute.value, { preserveScroll: true });
};

router.on('success', () => {
    wallet.value = usePage().props.data.wallet;
});
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
            <div class="flex items-center gap-2">
                <div v-show="viewStore.isMerchantViewMode" class="lg:block hidden">
                    <div class="dropdown dropdown-end">
                        <button
                            tabindex="0"
                            type="button"
                            role="button"
                            class="btn btn-ghost normal-case h-auto min-h-0 px-2.5 py-1.5 rounded-xl border border-base-300/70 hover:border-primary/60 hover:bg-primary/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary/40"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 text-primary shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                </svg>
                                <div class="font-semibold text-base-content text-nowrap flex items-center gap-1.5">
                                    <span class="text-base leading-none">{{ walletFormated.merchant_balance }}</span>
                                    <span class="badge badge-ghost badge-sm">USDT</span>
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
                <div v-show="viewStore.isTraderViewMode" class="lg:block hidden">
                    <div class="dropdown dropdown-end">
                        <button
                            tabindex="0"
                            type="button"
                            role="button"
                            class="btn btn-ghost normal-case h-auto min-h-0 px-2.5 py-1.5 rounded-xl border border-base-300/70 hover:border-primary/60 hover:bg-primary/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary/40"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 text-primary shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                </svg>
                                <div class="font-semibold text-base-content text-nowrap flex items-center gap-1.5">
                                    <span class="text-base leading-none">{{ walletFormated.trust_balance }}</span>
                                    <span class="badge badge-ghost badge-sm">USDT</span>
                                </div>
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
                                            <p class="flex items-center gap-1.5">
                                                <span>Резерв:</span>
                                                <span
                                                    v-if="usesTeamLeaderSharedReserve"
                                                    class="badge badge-neutral badge-xs"
                                                >
                                                    тимлидерский
                                                </span>
                                                <span v-else>{{ traderFinanceOverview.trustReserveAmount }} USDT</span>
                                            </p>
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
                <ViewModeSwitcher v-if="userStore.isAdmin" />
                <NewsDropdown ref="newsDropdownRef" />
                <button
                    v-if="canManageNews"
                    type="button"
                    class="btn btn-ghost btn-sm btn-square text-primary hover:bg-primary/10"
                    title="Создать новость"
                    @click.prevent="openCreateNewsModal"
                >
                    <span class="sr-only">Создать новость</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 opacity-90" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
                <div class="flex items-center">
                    <div class="dropdown dropdown-end">
                        <div tabindex="0" role="button" class="btn btn-ghost btn-sm h-auto min-h-0 gap-2 rounded-lg px-2 py-1.5 normal-case font-normal">
                            <UserAvatar :user="$page.props.auth.user" size="sm" ring />
                            <div class="hidden md:block text-left max-w-[9rem]">
                                <p class="text-sm font-medium text-base-content truncate leading-tight" role="none">
                                    {{ login }}
                                </p>
                                <p class="text-xs text-base-content/50 truncate leading-tight" role="none">
                                    {{ role.name }}
                                </p>
                            </div>
                            <svg class="hidden md:block size-3.5 text-base-content/50 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
                            </svg>
                        </div>
                        <ul tabindex="0" class="menu menu-sm dropdown-content mt-2 z-[1] w-52 p-2 shadow bg-base-100 rounded-box border border-base-300/60">
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
                                    <div v-show="viewStore.isTraderViewMode" class="flex items-center">
                                        <svg class="w-5 h-5 text-primary mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                                        </svg>
                                        <div class="font-semibold flex items-center gap-2">
                                            <span class="text-base text-base-content">{{ walletFormated.trust_balance }}</span>
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
    <NewsCreateModal
        :show="showCreateNewsModal"
        @close="closeCreateNewsModal"
        @created="onNewsCreated"
    />
    <ConfirmModal />
</template>

<style scoped>

</style>
