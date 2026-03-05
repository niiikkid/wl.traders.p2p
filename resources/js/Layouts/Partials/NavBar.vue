<script setup>
import {Link, router, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";
import {useViewStore} from "@/store/view.js";
import ThemeToggle from "@/Components/ThemeToggle.vue";

const viewStore = useViewStore();

const wallet = ref(usePage().props.data.wallet);

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

const role = usePage().props.auth.role;
const email = usePage().props.auth.user.email;

const login = computed(() =>
    email.charAt(0).toUpperCase() + email.slice(1)
)

router.on('success', (event) => {
    wallet.value = usePage().props.data.wallet;
})

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
                            <div class="text-xs font-medium text-base-content/70">Надежный процессинг</div>
                        </div>
                        <div class="lg:hidden">
                            <div class="text-[1.95rem] font-semibold">{{$page.props.app.name}}</div>
                            <div class="text-[0.65rem] text-base-content/70">Надежный процессинг</div>
                        </div>
                    </Link>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div v-show="viewStore.isMerchantViewMode" class="lg:flex items-center hidden text-nowrap">
                    <svg class="w-6 h-6 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                    </svg>
                    <div class="font-semibold text-base-content">
                        <span class="text-lg mx-1">{{ walletFormated.merchant_balance }}</span>
                        <span class="badge badge-ghost">USDT</span>
                    </div>
                </div>
                <div v-show="viewStore.isTraderViewMode" class="lg:flex items-center hidden text-nowrap">
                    <svg class="w-6 h-6 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8H5m12 0a1 1 0 0 1 1 1v2.6M17 8l-4-4M5 8a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.6M5 8l4-4 4 4m6 4h-4a2 2 0 1 0 0 4h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1Z"/>
                    </svg>
                    <div class="font-semibold text-base-content">
                        <span class="text-lg mx-1">{{ walletFormated.trust_balance }}</span>
                        <span class="badge badge-ghost">USDT</span>
                    </div>
                    <span class="ml-3 inline-flex items-center text-sm me-2 px-3 py-1.5 rounded-full badge badge-outline">
                            <svg class="w-4 h-4 mr-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                 <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v3m-3-6V7a3 3 0 1 1 6 0v4m-8 0h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z"/>
                              </svg>
                            {{ wallet.reserve_balance }} USDT
                        </span>
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
