<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {onUnmounted, ref} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FilterCheckbox from "@/Components/Filters/Partials/FilterCheckbox.vue";
import DateTime from "@/Components/DateTime.vue";
import NumberInput from "@/Components/NumberInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import UserAvatar from '@/Components/User/UserAvatar.vue';
import MoneyValue from '@/Components/MoneyValue.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';

const traders = ref(usePage().props.traders);
const extendedAccessEnabled = ref(usePage().props.extendedAccessEnabled ?? false);
const commissionSettings = ref(usePage().props.commissionSettings || {
    flexible_enabled: false,
    min: null,
    max: null,
});
const onlineForm = useForm({
    is_online: 0,
});
const commissionModal = ref(null);
const commissionProcessing = ref(false);
const commissionErrors = ref({});
const selectedTrader = ref(null);
const commissionForm = ref({
    commission: null,
});
const isCooldown = ref(false);
let cooldownTimer = null;

onUnmounted(() => {
    if (cooldownTimer) {
        clearTimeout(cooldownTimer);
        cooldownTimer = null;
    }
});

router.on('success', () => {
    traders.value = usePage().props.traders;
    extendedAccessEnabled.value = usePage().props.extendedAccessEnabled ?? false;
    commissionSettings.value = usePage().props.commissionSettings || commissionSettings.value;
});

const toggleOnline = (trader) => {
    onlineForm
        .transform((data) => {
            data.is_online = trader.is_online;

            trader.is_online = !trader.is_online;
            data.is_online = trader.is_online;

            return data;
        })
        .patch(route('leader.traders.toggle-online', trader.id), {
            preserveScroll: true,
            onFinish: () => {
                if (cooldownTimer) {
                    clearTimeout(cooldownTimer);
                }

                isCooldown.value = true;
                cooldownTimer = setTimeout(() => {
                    isCooldown.value = false;
                    cooldownTimer = null;
                }, 300);
            },
        });
};

const openTrader = (trader) => {
    router.visit(route('leader.traders.show', trader.id), {preserveScroll: true});
};

const formatPercent = (value) => {
    const number = Number(value);
    if (!Number.isFinite(number)) {
        return '-';
    }

    return `${number.toFixed(2)}%`;
};

const openCommissionModal = (trader) => {
    if (!commissionSettings.value?.flexible_enabled) {
        return;
    }

    selectedTrader.value = trader;
    commissionErrors.value = {};

    commissionForm.value.commission = trader.team_leader_individual_commission_percentage
        ?? trader.team_leader_effective_commission_percentage
        ?? commissionSettings.value.min
        ?? 0;

    commissionModal.value?.showModal();
};

const saveCommission = () => {
    if (!selectedTrader.value) {
        return;
    }

    commissionProcessing.value = true;
    commissionErrors.value = {};

    axios.patch(route('leader.traders.update-commission', selectedTrader.value.id), {
        commission: commissionForm.value.commission,
    }, {
        headers: { Accept: 'application/json' },
    })
        .then((response) => {
            const payload = response.data?.data || {};
            const trader = traders.value.data.find((item) => item.id === selectedTrader.value.id);
            if (trader) {
                trader.team_leader_individual_commission_percentage = payload.team_leader_individual_commission_percentage;
                trader.team_leader_effective_commission_percentage = payload.team_leader_effective_commission_percentage;
            }

            commissionModal.value?.close();
        })
        .catch((error) => {
            if (error.response?.data?.errors) {
                commissionErrors.value = error.response.data.errors;
            }
        })
        .finally(() => {
            commissionProcessing.value = false;
        });
};

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head title="Трейдеры" />

        <MainTableSection
            title="Трейдеры"
            :data="traders"
            :info="extendedAccessEnabled
                ? 'Список ваших трейдеров с быстрым переходом в подробную информацию.'
                : 'Список ваших трейдеров и статистика по сделкам.'"
        >
            <template #table-filters>
                <FiltersPanel name="leader-traders">
                    <InputFilter
                        name="user"
                        placeholder="Поиск (почта или имя)"
                    />
                    <template v-if="extendedAccessEnabled">
                        <FilterCheckbox
                            name="online"
                            title="Онлайн"
                        />
                        <FilterCheckbox
                            name="traffic_disabled"
                            title="Трафик выключен"
                        />
                    </template>
                </FiltersPanel>
            </template>

            <template #body>
                <div class="relative">
                    <DataTable>
                        <template #head>
                                        <th>Трейдер</th>
                                        <th>Сделок</th>
                                        <th>Доход</th>
                                        <th v-if="extendedAccessEnabled">Реквизитов</th>
                                        <th v-if="extendedAccessEnabled">Комиссия ТЛ</th>
                                        <th v-if="extendedAccessEnabled">Работает</th>
                                        <th>Создан</th>
                                        <th v-if="extendedAccessEnabled" class="text-right">Действия</th>
                        </template>
                                    <tr v-for="trader in traders.data" :key="trader.id" class="hover">
                                        <td class="whitespace-nowrap">
                                            <div class="inline-flex items-center gap-2">
                                                <UserAvatar :user="trader" />
                                                <div>
                                                    <div>{{ trader.email }}</div>
                                                    <div class="text-xs text-base-content/70">{{ trader.name }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap tabular-nums">{{ trader.orders_count }}</td>
                                        <td class="whitespace-nowrap">
                                            <MoneyValue :value="trader.total_profit" currency="usdt" />
                                        </td>
                                        <td v-if="extendedAccessEnabled" class="whitespace-nowrap tabular-nums">
                                            {{ trader.payment_details_count }}
                                        </td>
                                        <td v-if="extendedAccessEnabled" class="whitespace-nowrap">
                                            <div class="flex flex-col gap-1">
                                                <div class="inline-flex items-center gap-0.5">
                                                    <span class="font-medium">
                                                        {{ formatPercent(trader.team_leader_effective_commission_percentage) }}
                                                    </span>
                                                    <button
                                                        v-if="commissionSettings.flexible_enabled"
                                                        type="button"
                                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-base-content/45 transition hover:bg-base-content/10 hover:text-primary"
                                                        title="Изменить комиссию"
                                                        @click="openCommissionModal(trader)"
                                                    >
                                                        <svg class="size-3 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497zM15 5l4 4" />
                                                        </svg>
                                                        <span class="sr-only">Изменить комиссию</span>
                                                    </button>
                                                </div>
                                                <span v-if="trader.team_leader_individual_commission_percentage !== null" class="text-xs opacity-70">
                                                    Индивидуальная
                                                </span>
                                            </div>
                                        </td>
                                        <td v-if="extendedAccessEnabled" class="whitespace-nowrap">
                                            <input
                                                type="checkbox"
                                                :checked="trader.is_online"
                                                class="toggle toggle-success"
                                                :disabled="onlineForm.processing || isCooldown"
                                                @change="toggleOnline(trader)"
                                            >
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <DateTime :data="trader.created_at" :plural="true" />
                                        </td>
                                        <td v-if="extendedAccessEnabled" class="text-right">
                                            <button
                                                type="button"
                                                class="btn btn-xs btn-square btn-ghost text-primary opacity-70 hover:opacity-100"
                                                title="Открыть"
                                                @click="openTrader(trader)"
                                            >
                                                <svg class="size-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                                </svg>
                                                <span class="sr-only">Открыть</span>
                                            </button>
                                        </td>
                                    </tr>
                    </DataTable>

                    <DataCardList>
                            <DataCard v-for="trader in traders.data" :key="trader.id">
                                <div class="flex justify-end items-center border-b border-base-content/10 mb-2 pb-2">
                                    <DateTime :data="trader.created_at" :plural="true" />
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <div class="inline-flex items-center gap-2 min-w-0">
                                        <UserAvatar :user="trader" />
                                        <div class="min-w-0">
                                            <div class="truncate">{{ trader.email }}</div>
                                            <div class="text-xs text-base-content/70 truncate">{{ trader.name }}</div>
                                        </div>
                                    </div>
                                    <span v-if="extendedAccessEnabled" class="tabular-nums text-sm">
                                        {{ trader.payment_details_count }} рекв.
                                    </span>
                                </div>

                                <div class="flex items-center justify-between border-t border-base-content/10 pt-2 mt-2">
                                    <div class="flex items-center gap-2">
                                        <div class="text-base-content/70 text-xs">Сделок</div>
                                        <div class="font-medium tabular-nums">{{ trader.orders_count }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-base-content/70 text-xs">Доход</div>
                                        <MoneyValue :value="trader.total_profit" currency="usdt" compact />
                                    </div>
                                </div>

                                <div v-if="extendedAccessEnabled" class="mt-2 text-sm inline-flex items-center gap-1">
                                    <span class="text-base-content/70">Комиссия ТЛ:</span>
                                    <span class="font-medium">
                                        {{ formatPercent(trader.team_leader_effective_commission_percentage) }}
                                    </span>
                                    <button
                                        v-if="commissionSettings.flexible_enabled"
                                        type="button"
                                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-base-content/45 transition hover:bg-base-content/10 hover:text-primary"
                                        title="Изменить комиссию"
                                        @click="openCommissionModal(trader)"
                                    >
                                        <svg class="size-3 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497zM15 5l4 4" />
                                        </svg>
                                        <span class="sr-only">Изменить комиссию</span>
                                    </button>
                                </div>

                                <div v-if="extendedAccessEnabled" class="flex justify-end items-center mt-3">
                                    <div class="inline-flex items-center gap-3">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-xs text-base-content/70">Работает:</span>
                                            <input
                                                type="checkbox"
                                                :checked="trader.is_online"
                                                class="toggle toggle-success toggle-sm"
                                                :disabled="onlineForm.processing || isCooldown"
                                                @change="toggleOnline(trader)"
                                            >
                                        </div>
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-square btn-ghost text-primary opacity-70 hover:opacity-100 shrink-0"
                                            title="Открыть"
                                            @click="openTrader(trader)"
                                        >
                                            <svg class="size-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                            </svg>
                                            <span class="sr-only">Открыть</span>
                                        </button>
                                    </div>
                                </div>
                            </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>

        <dialog ref="commissionModal" class="modal modal-bottom sm:modal-middle" tabindex="0">
            <div class="modal-box w-[calc(100vw-2rem)] max-w-sm rounded-2xl border border-base-300/60 p-0 shadow-xl">
                <div class="flex items-start justify-between gap-3 border-b border-base-content/10 px-5 py-4">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold">Комиссия трейдера</h3>
                        <p class="mt-0.5 truncate text-sm text-base-content/70">
                            {{ selectedTrader?.email }}
                        </p>
                    </div>
                    <form method="dialog">
                        <button type="submit" class="btn btn-sm btn-circle btn-ghost shrink-0" aria-label="Закрыть">✕</button>
                    </form>
                </div>

                <div class="px-5 py-4">
                    <p class="text-xs text-base-content/60">
                        Допустимый диапазон:
                        <span class="font-medium text-base-content/80 tabular-nums">
                            {{ formatPercent(commissionSettings.min) }} — {{ formatPercent(commissionSettings.max) }}
                        </span>
                    </p>

                    <div class="mt-4">
                        <InputLabel
                            for="leader-trader-commission"
                            value="Комиссия, %"
                            :error="!!commissionErrors.commission?.[0]"
                        />
                        <NumberInput
                            id="leader-trader-commission"
                            v-model="commissionForm.commission"
                            class="mt-1.5 w-full"
                            step="0.01"
                            :min="commissionSettings.min ?? 0"
                            :max="commissionSettings.max ?? 100"
                            :error="!!commissionErrors.commission?.[0]"
                            :disabled="commissionProcessing"
                            @input="commissionErrors.commission = null"
                        />
                        <InputError class="mt-1" :message="commissionErrors.commission?.[0]" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-base-content/10 px-5 py-3">
                    <form method="dialog">
                        <button type="submit" class="btn btn-sm btn-ghost">Отмена</button>
                    </form>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        :disabled="commissionProcessing"
                        :class="{ 'btn-disabled': commissionProcessing }"
                        @click="saveCommission"
                    >
                        Сохранить
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit" aria-label="Закрыть">close</button>
            </form>
        </dialog>
    </div>
</template>

