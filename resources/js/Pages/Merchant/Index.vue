<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {useViewStore} from "@/store/view.js";
import {useModalStore} from "@/store/modal.js";
import MerchantCreateModal from "@/Modals/Merchant/MerchantCreateModal.vue";
import MerchantSettingsModal from "@/Modals/Merchant/MerchantSettingsModal.vue";
import TableActionsDropdown from "@/Components/Table/TableActionsDropdown.vue";
import TableAction from "@/Components/Table/TableAction.vue";
import CopyableOrderUid from "@/Components/CopyableOrderUid.vue";
import {computed, ref} from 'vue';

const viewStore = useViewStore();
const modalStore = useModalStore();

const page = usePage();
const merchants = ref(page.props.merchants);
const loading = ref(false);

const isAdminView = computed(() => viewStore.isAdminViewMode);

const fetchMerchants = async (pageNumber = null) => {
    loading.value = true;

    try {
        const prefix = isAdminView.value ? 'admin.' : '';
        const params = {};

        if (isAdminView.value) {
            const currentPage = pageNumber ?? merchants.value?.meta?.current_page;

            if (currentPage) {
                params.page = currentPage;
            }
        }

        const {data} = await axios.get(route(`${prefix}merchants.data`), {
            params,
            headers: {Accept: 'application/json'},
        });

        merchants.value = data;
    } catch (error) {
        console.error('[MerchantIndex] Не удалось обновить список мерчантов', error);
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    modalStore.openMerchantCreateModal({
        onCreated: fetchMerchants,
    });
};

const openSettings = (merchant) => {
    modalStore.openMerchantSettingsModal({
        merchantId: merchant.id,
        onUpdated: fetchMerchants,
    });
};

const merchantStatusIconWrapClass = (merchant) => {
    if (!merchant.validated_at) {
        return 'bg-warning/15 text-warning ring-warning/30';
    }
    if (merchant.banned_at) {
        return 'bg-error/15 text-error ring-error/30';
    }
    if (merchant.active) {
        return 'bg-success/15 text-success ring-success/30';
    }
    return 'bg-base-content/10 text-base-content/50 ring-base-content/20';
};

router.on('success', () => {
    merchants.value = usePage().props.merchants;
});

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Мерчанты" />

        <MainTableSection
            title="Мерчанты"
            :data="viewStore.isAdminViewMode ? merchants : merchants.data"
            :paginate="viewStore.isAdminViewMode"
        >
            <template v-slot:button>
                <div v-if="viewStore.isMerchantViewMode">
                    <button
                        @click="openCreateModal"
                        type="button"
                        class="btn btn-primary btn-sm"
                    >
                        Создать мерчант
                    </button>
                </div>
            </template>
            <template v-slot:body>
                <div v-if="viewStore.isAdminViewMode" class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Владелец</th>
                                        <th>Статус</th>
                                        <th class="text-center">
                                            <span class="sr-only">Действия</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="merchant in merchants.data">
                                        <th class="whitespace-nowrap">{{ merchant.id }}</th>
                                        <td>
                                            <div class="truncate max-w-48">{{ merchant.name }}</div>
                                            <div class="text-xs truncate max-w-36 text-base-content/70">{{ merchant.domain }}</div>
                                        </td>
                                        <td>
                                            {{ merchant.owner.email }}
                                        </td>
                                        <td>
                                            <div class="flex items-center text-nowrap">
                                                <template v-if="!merchant.validated_at">
                                                    <div class="h-2.5 w-2.5 rounded-full bg-warning me-2"></div> На модерации
                                                </template>
                                                <template v-else-if="merchant.banned_at">
                                                    <div class="h-2.5 w-2.5 rounded-full bg-error me-2"></div> Заблокирован
                                                </template>
                                                <template v-else-if="merchant.active">
                                                    <div class="h-2.5 w-2.5 rounded-full bg-success me-2"></div> Включен
                                                </template>
                                                <template v-else>
                                                    <div class="h-2.5 w-2.5 rounded-full bg-error me-2"></div> Выключен
                                                </template>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <TableActionsDropdown>
                                                <TableAction @click="openSettings(merchant)">
                                                    Настройки
                                                </TableAction>
                                            </TableActionsDropdown>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="merchant in merchants.data"
                                :key="merchant.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Компактная шапка: ID и статус -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="text-base-content font-medium">{{ merchant.id }}</span>
                                        </div>
                                        <div class="flex items-center text-nowrap">
                                            <template v-if="!merchant.validated_at">
                                                <div class="h-2.5 w-2.5 rounded-full bg-warning me-2"></div>
                                                <span class="text-xs">На модерации</span>
                                            </template>
                                            <template v-else-if="merchant.banned_at">
                                                <div class="h-2.5 w-2.5 rounded-full bg-error me-2"></div>
                                                <span class="text-xs">Заблокирован</span>
                                            </template>
                                            <template v-else-if="merchant.active">
                                                <div class="h-2.5 w-2.5 rounded-full bg-success me-2"></div>
                                                <span class="text-xs">Включен</span>
                                            </template>
                                            <template v-else>
                                                <div class="h-2.5 w-2.5 rounded-full bg-error me-2"></div>
                                                <span class="text-xs">Выключен</span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Основная информация -->
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="truncate max-w-48 min-w-48">{{ merchant.name }}</div>
                                            <div class="text-xs truncate max-w-36 text-base-content/70">{{ merchant.domain }}</div>
                                        </div>
                                        <div class="hidden sm:block">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                <span class="text-base-content truncate">{{ merchant.owner.email }}</span>
                                            </div>
                                        </div>
                                        <TableActionsDropdown>
                                            <TableAction @click="openSettings(merchant)">
                                                Настройки
                                            </TableAction>
                                        </TableActionsDropdown>
                                    </div>
                                    <div class="block sm:hidden border-b border-base-content/10 my-1 w-full"></div>
                                    <div class="sm:hidden">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-primary shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                            <span class="text-base-content truncate">{{ merchant.owner.email }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <section v-if="viewStore.isMerchantViewMode" class="antialiased">
                    <div class="mx-auto">
                        <div class="mb-4 grid grid-cols-1 gap-4 md:mb-8 md:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="merchant in merchants.data"
                                :key="merchant.id"
                                class="card bg-base-100 shadow-sm ring-1 ring-base-content/5 transition-shadow hover:shadow-md"
                            >
                                <div class="card-body gap-5 p-5 sm:p-6">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="mb-2 flex items-center gap-2">
                                                <span class="badge badge-ghost badge-sm h-auto min-h-0 gap-0 px-2 py-1 font-normal normal-case">
                                                    <span class="block min-w-0 shrink-0 overflow-hidden">
                                                        ID: 
                                                        <CopyableOrderUid
                                                            :uuid="merchant.uuid ?? ''"
                                                            class="block max-w-full truncate text-left text-xs text-base-content"
                                                        />
                                                    </span>
                                                </span>
                                                <span
                                                    v-if="! merchant.validated_at"
                                                    class="badge badge-warning badge-sm"
                                                >
                                                    Модерация
                                                </span>
                                                <span
                                                    v-else-if="merchant.banned_at"
                                                    class="badge badge-error badge-sm"
                                                >
                                                    Заблокирован
                                                </span>
                                                <span
                                                    v-else-if="merchant.active"
                                                    class="badge badge-success badge-sm"
                                                >
                                                    Включен
                                                </span>
                                                <span
                                                    v-else
                                                    class="badge badge-error badge-sm"
                                                >
                                                    Выключен
                                                </span>
                                            </div>
                                            <h3 class="text-xl font-semibold leading-tight text-base-content truncate">
                                                {{ merchant.name }}
                                            </h3>
                                        </div>

                                        <div
                                            class="grid size-12 shrink-0 place-items-center rounded-2xl ring-1"
                                            :class="merchantStatusIconWrapClass(merchant)"
                                        >
                                            <!-- store-timings: moderation (SVG Repo) -->
                                            <svg
                                                v-if="! merchant.validated_at"
                                                class="size-7"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 64 64"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                            >
                                                <path d="M43.21,54.62H12a2.93,2.93,0,0,1-3-2.84V26.19" />
                                                <line x1="49.01" y1="26.36" x2="49.01" y2="37.37" />
                                                <polyline points="23.26 54.55 23.26 37.48 34.84 37.48 34.84 54.55" />
                                                <path d="M5.45,18.2s-1.1,7.76,6.45,9a7.15,7.15,0,0,0,6.1-2A7.43,7.43,0,0,0,29,25a7.37,7.37,0,0,0,5,2.49,11.77,11.77,0,0,0,5.89-2.15,6.67,6.67,0,0,0,4.68,2.15,8,8,0,0,0,7.92-9.3L47.79,8.08a1,1,0,0,0-.94-.66H11a1,1,0,0,0-.94.66Z" />
                                                <line x1="5.45" y1="18.2" x2="52.54" y2="18.2" />
                                                <line x1="18.05" y1="18.2" x2="18.05" y2="7.42" />
                                                <line x1="29.05" y1="18.2" x2="29.05" y2="7.42" />
                                                <line x1="40.02" y1="18.2" x2="40.02" y2="7.42" />
                                                <circle cx="49.01" cy="46.97" r="9.6" />
                                                <polyline points="49.01 40.86 49.01 47.27 52.58 50.01" />
                                            </svg>
                                            <!-- store: blocked / active / disabled (SVG Repo) -->
                                            <svg
                                                v-else
                                                class="size-7"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 64 64"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                            >
                                                <path d="M52,27.18V52.76a2.92,2.92,0,0,1-3,2.84H15a2.92,2.92,0,0,1-3-2.84V27.17" />
                                                <polyline points="26.26 55.52 26.26 38.45 37.84 38.45 37.84 55.52" />
                                                <path d="M8.44,19.18s-1.1,7.76,6.45,8.94a7.17,7.17,0,0,0,6.1-2A7.43,7.43,0,0,0,32,26a7.4,7.4,0,0,0,5,2.49,11.82,11.82,0,0,0,5.9-2.15,6.66,6.66,0,0,0,4.67,2.15,8,8,0,0,0,7.93-9.3L50.78,9.05a1,1,0,0,0-.94-.65H14a1,1,0,0,0-.94.66Z" />
                                                <line x1="8.44" y1="19.18" x2="55.54" y2="19.18" />
                                                <line x1="21.04" y1="19.18" x2="21.04" y2="8.4" />
                                                <line x1="32.05" y1="19.18" x2="32.05" y2="8.4" />
                                                <line x1="43.01" y1="19.18" x2="43.01" y2="8.4" />
                                            </svg>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3">
                                        <div class="rounded-2xl bg-base-200/70 p-4">
                                            <p class="text-xs font-medium uppercase tracking-wide text-base-content/50">
                                                Доход сегодня
                                            </p>
                                            <p class="mt-2 text-2xl font-bold leading-none text-base-content">
                                                {{ merchant.today_profit }} <span class="mt-1 text-sm text-primary/70">{{ merchant.profit_currency?.toUpperCase() }}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="card-actions items-center justify-between border-t border-base-content/10 pt-4">
                                        <span class="text-sm text-base-content/60">Управление мерчантом</span>
                                        <button
                                            @click="openSettings(merchant)"
                                            type="button"
                                            class="btn btn-primary btn-sm"
                                        >
                                            Настройки
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </template>
        </MainTableSection>
        <MerchantCreateModal />
        <MerchantSettingsModal />
    </div>
</template>
