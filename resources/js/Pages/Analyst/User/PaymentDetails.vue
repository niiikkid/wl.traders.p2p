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
import PaymentDetailLimit from "@/Components/PaymentDetailLimit.vue";
import PaymentDetailOrdersLimit from "@/Components/PaymentDetailOrdersLimit.vue";
import TableCellPopover from "@/Components/Table/TableCellPopover.vue";
import TableInfoDropdown from "@/Components/Table/TableInfoDropdown.vue";

const page = usePage();
const tableFiltersStore = useTableFiltersStore();

const user = ref(page.props.user);
const paymentDetails = ref(page.props.paymentDetails);
const currentTab = ref('active');

const normalizeNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    return Number(String(value).replace(/\s/g, '').replace(',', '.')) || 0;
};

const percentFrom = (current, limit) => {
    const currentValue = normalizeNumber(current);
    const limitValue = normalizeNumber(limit);

    if (limitValue <= 0) {
        return 0;
    }

    return Math.min(100, (currentValue / limitValue) * 100);
};

const hasLimit = (limit) => {
    return normalizeNumber(limit) > 0;
};

const progressClass = (percent, has_limit = true) => {
    if (!has_limit) {
        return 'text-base-content/40';
    }

    if (percent < 40) {
        return 'text-success';
    }

    if (percent < 80) {
        return 'text-warning';
    }

    return 'text-error';
};

const percentLabel = (percent) => {
    if (!Number.isFinite(percent)) {
        return '0%';
    }

    return `${Math.round(percent)}%`;
};

const radialStyle = (value) => {
    return {
        '--value': value,
        '--size': '2.4rem',
        '--thickness': '3px',
    };
};

const openPage = (tab) => {
    tableFiltersStore.setTab(tab);
    tableFiltersStore.setCurrentPage(1);

    router.visit(route(route().current(), {user: user.value.id}), {
        preserveScroll: true,
        data: tableFiltersStore.getQueryData,
    });
};

router.on('success', () => {
    user.value = usePage().props.user;
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
        <Head :title="`Пользователь #${user.id} - Реквизиты`" />

        <MainTableSection
            title="Карточка пользователя"
            :data="paymentDetails"
            :info="`Пользователь: ${user.email}`"
        >
            <template #header>
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div class="breadcrumbs text-sm">
                        <ul>
                            <li>
                                <button class="link link-hover" @click="router.visit(route('analyst.users.index'))">Пользователи</button>
                            </li>
                            <li>{{ user.email }}</li>
                        </ul>
                    </div>

                    <ul class="flex flex-wrap text-sm font-medium text-center">
                        <li class="me-2">
                            <button class="btn btn-sm btn-primary">Реквизиты</button>
                        </li>
                    </ul>
                </div>

                <div class="flex items-center justify-between gap-3 mt-2">
                    <div class="inline-flex items-center gap-2">
                        <span class="badge badge-outline">ID {{ user.id }}</span>
                        <span class="badge badge-success" v-if="user.is_online">Онлайн</span>
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
                <FiltersPanel name="analyst-user-payment-details">
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
                                        <th>Лимиты</th>
                                        <th>Статус</th>
                                        <th><span class="sr-only">Настройки</span></th>
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
                                        <td class="text-nowrap">
                                            <TableCellPopover>
                                                <template #trigger>
                                                    <div class="flex items-center gap-2">
                                                        <div class="relative grid place-items-center">
                                                            <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                            <div
                                                                class="radial-progress absolute inset-0"
                                                                :class="progressClass(
                                                                    percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity),
                                                                    hasLimit(detail.max_pending_orders_quantity)
                                                                )"
                                                                :style="radialStyle(percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity))"
                                                                role="progressbar"
                                                                :aria-valuenow="percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity)"
                                                            >
                                                                <span class="text-[10px] leading-none">
                                                                    {{ percentLabel(percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity)) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="relative grid place-items-center">
                                                            <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                            <div
                                                                class="radial-progress absolute inset-0"
                                                                :class="progressClass(
                                                                    percentFrom(detail.current_daily_successful_orders_count, detail.daily_successful_orders_limit),
                                                                    hasLimit(detail.daily_successful_orders_limit)
                                                                )"
                                                                :style="radialStyle(percentFrom(detail.current_daily_successful_orders_count, detail.daily_successful_orders_limit))"
                                                                role="progressbar"
                                                                :aria-valuenow="percentFrom(detail.current_daily_successful_orders_count, detail.daily_successful_orders_limit)"
                                                            >
                                                                <span class="text-[10px] leading-none">
                                                                    {{ percentLabel(percentFrom(detail.current_daily_successful_orders_count, detail.daily_successful_orders_limit)) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="relative grid place-items-center">
                                                            <div class="radial-progress text-base-300/60" :style="radialStyle(100)"></div>
                                                            <div
                                                                class="radial-progress absolute inset-0"
                                                                :class="progressClass(
                                                                    percentFrom(detail.current_daily_limit, detail.daily_limit),
                                                                    hasLimit(detail.daily_limit)
                                                                )"
                                                                :style="radialStyle(percentFrom(detail.current_daily_limit, detail.daily_limit))"
                                                                role="progressbar"
                                                                :aria-valuenow="percentFrom(detail.current_daily_limit, detail.daily_limit)"
                                                            >
                                                                <span class="text-[10px] leading-none">
                                                                    {{ percentLabel(percentFrom(detail.current_daily_limit, detail.daily_limit)) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div class="grid gap-3 text-sm">
                                                    <div class="grid gap-1">
                                                        <div class="text-xs text-base-content/70">Активных сделок</div>
                                                        <div class="flex justify-end mb-1">
                                                            <div class="relative text-nowrap">
                                                                <span
                                                                    class="text-xs font-semibold"
                                                                    :class="{
                                                                        'text-success': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) < 40,
                                                                        'text-warning': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) >= 40 && percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) < 80,
                                                                        'text-error': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) >= 80
                                                                    }"
                                                                >
                                                                    {{ detail.pending_orders_count }}
                                                                </span>
                                                                <span class="mx-1 opacity-70">из</span>
                                                                <span class="text-xs font-semibold">
                                                                    {{ detail.max_pending_orders_quantity }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <progress
                                                            class="progress w-full"
                                                            :class="{
                                                                'progress-success': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) < 40,
                                                                'progress-warning': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) >= 40 && percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) < 80,
                                                                'progress-error': percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity) >= 80
                                                            }"
                                                            :value="percentFrom(detail.pending_orders_count, detail.max_pending_orders_quantity)"
                                                            max="100"
                                                        ></progress>
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <div class="text-xs text-base-content/70">Количество сделок за день</div>
                                                        <PaymentDetailOrdersLimit
                                                            :current_daily_successful_orders_count="detail.current_daily_successful_orders_count"
                                                            :daily_successful_orders_limit="detail.daily_successful_orders_limit"
                                                        />
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <div class="text-xs text-base-content/70">Объём сделок за день</div>
                                                        <PaymentDetailLimit
                                                            :current_daily_limit="detail.current_daily_limit"
                                                            :daily_limit="detail.daily_limit"
                                                        />
                                                    </div>
                                                    <div v-if="hasLimit(detail.monthly_limit)" class="grid gap-1">
                                                        <div class="text-xs text-base-content/70">Объём сделок за месяц</div>
                                                        <PaymentDetailLimit
                                                            :current_daily_limit="detail.current_monthly_limit"
                                                            :daily_limit="detail.monthly_limit"
                                                        />
                                                    </div>
                                                </div>
                                            </TableCellPopover>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="badge badge-success badge-sm" v-if="detail.is_active">Активен</span>
                                            <span class="badge badge-ghost badge-sm" v-else>Выключен</span>
                                        </td>
                                        <td class="text-right">
                                            <TableInfoDropdown>
                                                <div class="grid gap-2 text-sm">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-base-content/70">Интервал:</span>
                                                        <span class="text-right">{{ detail.order_interval_minutes !== null ? detail.order_interval_minutes + ' мин' : '-' }}</span>
                                                    </div>
                                                    <div class="grid gap-1">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-base-content/70">Мин:</span>
                                                            <span class="text-right">{{ detail.min_order_amount !== null ? detail.min_order_amount : '∞' }}</span>
                                                        </div>
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-base-content/70">Макс:</span>
                                                            <span class="text-right">{{ detail.max_order_amount !== null ? detail.max_order_amount : '∞' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableInfoDropdown>
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
                                <div class="mt-2 grid gap-1 text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Сумма:</span>
                                        <span class="text-right">
                                            {{ detail.min_order_amount !== null ? detail.min_order_amount : '∞' }}
                                            -
                                            {{ detail.max_order_amount !== null ? detail.max_order_amount : '∞' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Активных сделок:</span>
                                        <span class="text-right">{{ detail.pending_orders_count }} / {{ detail.max_pending_orders_quantity }}</span>
                                    </div>
                                    <div class="grid gap-1">
                                        <div class="text-base-content/70">Лимит сделок/день:</div>
                                        <PaymentDetailOrdersLimit
                                            :current_daily_successful_orders_count="detail.current_daily_successful_orders_count"
                                            :daily_successful_orders_limit="detail.daily_successful_orders_limit"
                                        />
                                    </div>
                                    <div class="grid gap-1">
                                        <div class="text-base-content/70">Объем/день:</div>
                                        <PaymentDetailLimit
                                            :current_daily_limit="detail.current_daily_limit"
                                            :daily_limit="detail.daily_limit"
                                        />
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Интервал:</span>
                                        <span class="text-right">{{ detail.order_interval_minutes !== null ? detail.order_interval_minutes + ' мин' : '-' }}</span>
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
