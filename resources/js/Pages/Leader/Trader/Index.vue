<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {onUnmounted, ref} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import DateTime from "@/Components/DateTime.vue";
import NumberInput from "@/Components/NumberInput.vue";
import InputError from "@/Components/InputError.vue";
import UserAvatar from '@/Components/User/UserAvatar.vue';

const traders = ref(usePage().props.traders);
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
            info="Список ваших трейдеров с быстрым переходом в подробную информацию."
        >
            <template #table-filters>
                <FiltersPanel name="leader-traders">
                    <InputFilter
                        name="user"
                        placeholder="Поиск (почта или имя)"
                    />
                    <FilterCheckbox
                        name="online"
                        title="Онлайн"
                    />
                    <FilterCheckbox
                        name="traffic_disabled"
                        title="Трафик выключен"
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
                                        <th>Трейдер</th>
                                        <th>Реквизитов</th>
                                        <th>Комиссия ТЛ</th>
                                        <th>Статус</th>
                                        <th>Работает</th>
                                        <th>Создан</th>
                                        <th class="text-right">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="trader in traders.data" :key="trader.id" class="hover">
                                        <th class="font-medium whitespace-nowrap">{{ trader.id }}</th>
                                        <td class="whitespace-nowrap">
                                            <div class="inline-flex items-center gap-2">
                                                <UserAvatar :user="trader" />
                                                <div>
                                                    <div>{{ trader.email }}</div>
                                                    <div class="text-xs text-base-content/70">{{ trader.name }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="badge badge-outline">{{ trader.payment_details_count }}</span>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <div class="flex flex-col gap-1">
                                                <span class="font-medium">
                                                    {{ formatPercent(trader.team_leader_effective_commission_percentage) }}
                                                </span>
                                                <span v-if="trader.team_leader_individual_commission_percentage !== null" class="text-xs opacity-70">
                                                    Индивидуальная
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <div class="inline-flex items-center gap-2">
                                                <span class="badge badge-success badge-sm" v-if="trader.is_online">Онлайн</span>
                                                <span class="badge badge-ghost badge-sm" v-else>Оффлайн</span>
                                                <span class="badge badge-error badge-sm" v-if="trader.stop_traffic">Трафик off</span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
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
                                        <td class="text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <button
                                                    v-if="commissionSettings.flexible_enabled"
                                                    class="btn btn-xs btn-outline"
                                                    @click="openCommissionModal(trader)"
                                                >
                                                    Комиссия
                                                </button>
                                                <button class="btn btn-xs btn-primary" @click="openTrader(trader)">
                                                    Открыть
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="xl:hidden space-y-2">
                        <div v-for="trader in traders.data" :key="trader.id" class="card bg-base-100 shadow-sm">
                            <div class="card-body p-4 pt-2 pb-3">
                                <div class="flex justify-between items-center border-b border-base-content/10 mb-2 pb-2">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="text-base-content/70">ID:</span>
                                        <span class="font-medium">{{ trader.id }}</span>
                                    </div>
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
                                    <span class="badge badge-outline">{{ trader.payment_details_count }}</span>
                                </div>

                                <div class="mt-2 text-sm">
                                    <span class="text-base-content/70">Комиссия ТЛ:</span>
                                    <span class="font-medium ml-1">
                                        {{ formatPercent(trader.team_leader_effective_commission_percentage) }}
                                    </span>
                                </div>

                                <div class="flex justify-between items-center mt-3">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="badge badge-success badge-sm" v-if="trader.is_online">Онлайн</span>
                                        <span class="badge badge-ghost badge-sm" v-else>Оффлайн</span>
                                        <span class="badge badge-error badge-sm" v-if="trader.stop_traffic">Трафик off</span>
                                    </div>
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
                                        <button class="btn btn-xs btn-primary" @click="openTrader(trader)">
                                            Открыть
                                        </button>
                                        <button
                                            v-if="commissionSettings.flexible_enabled"
                                            class="btn btn-xs btn-outline"
                                            @click="openCommissionModal(trader)"
                                        >
                                            Комиссия
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <dialog ref="commissionModal" class="modal modal-bottom sm:modal-middle" tabindex="0">
            <div class="modal-box">
                <h3 class="font-semibold text-lg">Комиссия трейдера</h3>
                <p class="text-sm opacity-70 mt-1">
                    {{ selectedTrader?.email }}
                </p>
                <p class="text-xs opacity-70 mt-1">
                    Допустимый диапазон: {{ formatPercent(commissionSettings.min) }} - {{ formatPercent(commissionSettings.max) }}
                </p>

                <div class="mt-4">
                    <NumberInput
                        id="leader-trader-commission"
                        v-model="commissionForm.commission"
                        class="w-full"
                        step="0.01"
                        :min="commissionSettings.min ?? 0"
                        :max="commissionSettings.max ?? 100"
                        :error="!!commissionErrors.commission?.[0]"
                        :disabled="commissionProcessing"
                        @input="commissionErrors.commission = null"
                    />
                    <InputError class="mt-1" :message="commissionErrors.commission?.[0]" />
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button type="submit" class="btn btn-sm">Отмена</button>
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

