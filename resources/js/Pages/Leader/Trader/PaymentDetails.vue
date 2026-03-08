<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {onMounted, ref} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import PaymentDetail from "@/Components/PaymentDetail.vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";

const page = usePage();
const tableFiltersStore = useTableFiltersStore();

const trader = ref(page.props.trader);
const paymentDetails = ref(page.props.paymentDetails);
const currentTab = ref('active');

const openPage = (tab) => {
    tableFiltersStore.setTab(tab);
    tableFiltersStore.setCurrentPage(1);

    router.visit(route(route().current(), {trader: trader.value.id}), {
        preserveScroll: true,
        data: tableFiltersStore.getQueryData,
    });
};

router.on('success', () => {
    trader.value = usePage().props.trader;
    paymentDetails.value = usePage().props.paymentDetails;
    currentTab.value = tableFiltersStore.getTab || 'active';
});

onMounted(() => {
    if (tableFiltersStore.getTab === '') {
        tableFiltersStore.setTab('active');
    }

    currentTab.value = tableFiltersStore.getTab || 'active';
});

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head :title="`Трейдер #${trader.id} - Реквизиты`" />

        <MainTableSection
            title="Карточка трейдера"
            :data="paymentDetails"
            :info="`Трейдер: ${trader.email}`"
        >
            <template #header>
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="breadcrumbs text-sm">
                        <ul>
                            <li>
                                <button class="link link-hover" @click="router.visit(route('leader.traders.index'))">Трейдеры</button>
                            </li>
                            <li>{{ trader.email }}</li>
                        </ul>
                    </div>

                    <ul class="flex flex-wrap text-sm font-medium text-center">
                        <li class="me-2">
                            <button class="btn btn-sm btn-primary">Реквизиты</button>
                        </li>
                        <li class="me-2">
                            <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.orders.index', {trader: trader.id}))">Сделки</button>
                        </li>
                        <li class="me-2">
                            <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.disputes.index', {trader: trader.id}))">Споры</button>
                        </li>
                        <li class="me-2">
                            <button class="btn btn-sm btn-outline" @click="router.visit(route('leader.traders.finances.index', {trader: trader.id}))">Финансы</button>
                        </li>
                    </ul>
                </div>

                <div class="flex items-center justify-between gap-3 mt-2">
                    <div class="inline-flex items-center gap-2">
                        <span class="badge badge-outline">ID {{ trader.id }}</span>
                        <span class="badge badge-success" v-if="trader.is_online">Онлайн</span>
                        <span class="badge badge-ghost" v-else>Оффлайн</span>
                    </div>

                    <ul class="flex flex-wrap text-sm font-medium text-center">
                        <li class="me-2">
                            <a @click.prevent="openPage('active')" href="#" :class="currentTab === 'active' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'">
                                Активные
                            </a>
                        </li>
                        <li class="me-2">
                            <a @click.prevent="openPage('archived')" href="#" :class="currentTab === 'archived' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'">
                                Архив
                            </a>
                        </li>
                    </ul>
                </div>
            </template>

            <template #table-filters>
                <FiltersPanel name="leader-trader-payment-details">
                    <InputFilter
                        name="id"
                        placeholder="ID реквизита"
                    />
                    <InputFilter
                        name="name"
                        placeholder="Название"
                    />
                    <DropdownFilter
                        name="detailTypes"
                        title="Тип реквизита"
                    />
                    <InputFilter
                        name="paymentGateway"
                        placeholder="Платежный метод"
                    />
                    <InputFilter
                        name="paymentDetail"
                        placeholder="Реквизит"
                    />
                    <FilterCheckbox
                        name="active"
                        title="Включенные"
                    />
                </FiltersPanel>
            </template>

            <template #body>
                <div class="relative">
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th>ID</th>
                                        <th>Реквизит</th>
                                        <th>Тип</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="detail in paymentDetails.data" :key="detail.id" class="hover">
                                        <th class="font-medium whitespace-nowrap">{{ detail.id }}</th>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <GatewayLogo :img_path="detail.payment_gateway.logo_path" :name="detail.payment_gateway.name" class="w-10 h-10" />
                                                <PaymentDetail
                                                    :detail="detail.detail"
                                                    :type="detail.detail_type"
                                                    :name="detail.name"
                                                />
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">{{ detail.detail_type }}</td>
                                        <td class="whitespace-nowrap">
                                            <span class="badge badge-success badge-sm" v-if="detail.is_active">Активен</span>
                                            <span class="badge badge-ghost badge-sm" v-else>Выключен</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="xl:hidden space-y-2">
                        <div v-for="detail in paymentDetails.data" :key="detail.id" class="card bg-base-100 shadow-sm">
                            <div class="card-body p-4 pt-2 pb-3">
                                <div class="flex justify-between items-center border-b border-base-content/10 mb-2 pb-2">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="text-base-content/70">ID:</span>
                                        <span class="font-medium">{{ detail.id }}</span>
                                    </div>
                                    <span class="badge badge-success badge-sm" v-if="detail.is_active">Активен</span>
                                    <span class="badge badge-ghost badge-sm" v-else>Выключен</span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <GatewayLogo :img_path="detail.payment_gateway.logo_path" :name="detail.payment_gateway.name" class="w-10 h-10" />
                                    <div class="min-w-0">
                                        <PaymentDetail
                                            :detail="detail.detail"
                                            :type="detail.detail_type"
                                            :name="detail.name"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>

