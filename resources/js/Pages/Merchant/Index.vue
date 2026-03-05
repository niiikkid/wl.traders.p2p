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
        const currentPage = pageNumber ?? merchants.value?.meta?.current_page;

        if (currentPage) {
            params.page = currentPage;
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
            :data="merchants"
        >
            <template v-slot:button>
                <div v-if="viewStore.isMerchantViewMode">
                    <button
                        @click="openCreateModal"
                        type="button"
                        class="btn btn-primary btn-sm sm:btn-md"
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
                        <div class="mb-4 grid gap-4 md:mb-8 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-3">
                            <div
                                v-for="(merchant, index) in merchants.data"
                                class="card bg-base-100 shadow"
                            >
                                <div class="card-body p-5 sm:p-6">
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="card-title truncate">{{ merchant.name }}</h3>
                                        <TableActionsDropdown>
                                            <TableAction @click="openSettings(merchant)">
                                                Настройки
                                            </TableAction>
                                        </TableActionsDropdown>
                                    </div>

                                    <div class="mt-1 flex items-center gap-2">
                                        <p class="text-sm text-base-content/70">доход за сегодня</p>
                                        <p class="text-sm font-medium">{{ merchant.today_profit }} {{ merchant.profit_currency?.toUpperCase() }}</p>
                                    </div>

                                    <p class="mt-2 text-lg font-semibold leading-tight text-primary truncate">
                                        {{ merchant.domain }}
                                    </p>

                                    <div class="mt-4 text-sm flex items-end justify-start">
                                        <div class="flex items-center text-nowrap">
                                            <template v-if="! merchant.validated_at">
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
