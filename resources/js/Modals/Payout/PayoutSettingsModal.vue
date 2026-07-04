<script setup>
import ModalNext from '@/Components/Modals/Next/ModalNext.vue';
import ModalHeaderNext from '@/Components/Modals/Next/ModalHeaderNext.vue';
import ModalBodyNext from '@/Components/Modals/Next/ModalBodyNext.vue';
import ModalFooterNext from '@/Components/Modals/Next/ModalFooterNext.vue';
import { storeToRefs } from 'pinia';
import { useModalStore } from '@/store/modal.js';
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import CurrencyDisplay from '@/Components/Currency/CurrencyDisplay.vue';

const modalStore = useModalStore();
const { payoutSettingsModal } = storeToRefs(modalStore);

const loading = ref(false);
const processing = ref(false);
const errors = ref({});
const currencies = ref([]);
const form = ref({
    settings: {},
});

const close = () => {
    modalStore.closeModal('payoutSettings');
};

const errorMessage = (code, field) => {
    const key = `settings.${code}.${field}`;
    return errors.value?.[key]?.[0] ?? null;
};

const hasFieldError = (code, field) => !!errorMessage(code, field);

const resolveCode = (currency) => (currency?.code || '').toLowerCase();

const setDefaults = (payload) => {
    const list = payload?.currencies || [];
    const settings = payload?.settings || {};

    currencies.value = list;
    const nextSettings = {};

    list.forEach((currency) => {
        const code = resolveCode(currency);
        const current = settings?.[code] || settings?.[code.toUpperCase()] || {};
        nextSettings[code] = {
            total_commission_rate: current.total_commission_rate ?? 5,
            trader_commission_rate: current.trader_commission_rate ?? 4,
            reservation_time_for_payouts: current.reservation_time_for_payouts ?? 20,
        };
    });

    form.value.settings = nextSettings;
};

const loadData = () => {
    loading.value = true;
    axios.get(route('admin.payouts.settings-data'))
        .then((response) => {
            const data = response.data?.data || response.data || {};
            setDefaults(data);
            loading.value = false;
        })
        .catch(() => {
            loading.value = false;
        });
};

const submit = () => {
    processing.value = true;
    errors.value = {};

    axios.patch(route('admin.payouts.settings.update'), {
        settings: form.value.settings,
    }, {
        headers: { Accept: 'application/json' },
    })
        .then((response) => {
            processing.value = false;
            if (response.data?.success || response.status === 200) {
                close();
                router.reload({
                    only: ['payouts'],
                    preserveScroll: true,
                });
            }
        })
        .catch((error) => {
            processing.value = false;
            if (error.response?.data?.errors) {
                errors.value = error.response.data.errors;
            } else if (error.response?.data?.message) {
                errors.value = { message: [error.response.data.message] };
            }
        });
};

const settingsList = computed(() => currencies.value.map((currency) => {
    const code = resolveCode(currency);
    return {
        currency,
        code,
        settings: form.value.settings?.[code] ?? {},
    };
}));

watch(
    () => payoutSettingsModal.value.showed,
    (state) => {
        if (state) {
            errors.value = {};
            loadData();
        } else {
            errors.value = {};
            currencies.value = [];
            form.value.settings = {};
        }
    },
);
</script>

<template>
    <ModalNext :show="payoutSettingsModal.showed" max-width="3xl" @close="close">
        <ModalHeaderNext title="Настройки выплат по валютам" @close="close" />

        <ModalBodyNext>
            <div v-if="loading" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md text-primary" />
            </div>

            <div v-else class="space-y-3">
                <p class="text-xs leading-snug text-base-content/60">
                    Глобальные значения по умолчанию, если платёжный метод не указан.
                </p>

                <div
                    v-if="errors.message?.[0]"
                    role="alert"
                    class="alert alert-error alert-soft py-2 text-xs"
                >
                    <span>{{ errors.message[0] }}</span>
                </div>

                <div
                    v-if="settingsList.length"
                    class="overflow-x-auto rounded-box border border-base-300/60 bg-base-100"
                >
                    <table class="table table-xs table-pin-rows">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-wide text-base-content/50">
                                <th class="min-w-[8rem] bg-base-200/40 py-2 font-medium">
                                    Валюта
                                </th>
                                <th class="w-24 bg-base-200/40 py-2 text-right font-medium">
                                    Total, %
                                </th>
                                <th class="w-24 bg-base-200/40 py-2 text-right font-medium">
                                    Трейдер, %
                                </th>
                                <th class="w-24 bg-base-200/40 py-2 text-right font-medium">
                                    Время, мин
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in settingsList"
                                :key="item.code"
                                class="hover:bg-base-200/30"
                            >
                                <td class="py-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <CurrencyDisplay
                                            :currency="item.currency.code"
                                            :show-label="true"
                                            size="sm"
                                            :icon-size="18"
                                        />
                                        <span class="truncate text-xs text-base-content/70">
                                            {{ item.currency.name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-2 text-right">
                                    <input
                                        :id="`total-${item.code}`"
                                        v-model="form.settings[item.code].total_commission_rate"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        placeholder="5"
                                        class="input input-bordered input-sm h-7 min-h-7 w-full max-w-[4.5rem] py-0 text-right tabular-nums"
                                        :class="{ 'input-error': hasFieldError(item.code, 'total_commission_rate') }"
                                    />
                                    <p
                                        v-if="errorMessage(item.code, 'total_commission_rate')"
                                        class="mt-0.5 text-right text-[10px] text-error"
                                    >
                                        {{ errorMessage(item.code, 'total_commission_rate') }}
                                    </p>
                                </td>
                                <td class="py-2 text-right">
                                    <input
                                        :id="`trader-${item.code}`"
                                        v-model="form.settings[item.code].trader_commission_rate"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        placeholder="4"
                                        class="input input-bordered input-sm h-7 min-h-7 w-full max-w-[4.5rem] py-0 text-right tabular-nums"
                                        :class="{ 'input-error': hasFieldError(item.code, 'trader_commission_rate') }"
                                    />
                                    <p
                                        v-if="errorMessage(item.code, 'trader_commission_rate')"
                                        class="mt-0.5 text-right text-[10px] text-error"
                                    >
                                        {{ errorMessage(item.code, 'trader_commission_rate') }}
                                    </p>
                                </td>
                                <td class="py-2 text-right">
                                    <input
                                        :id="`time-${item.code}`"
                                        v-model="form.settings[item.code].reservation_time_for_payouts"
                                        type="number"
                                        min="1"
                                        placeholder="20"
                                        class="input input-bordered input-sm h-7 min-h-7 w-full max-w-[4.5rem] py-0 text-right tabular-nums"
                                        :class="{ 'input-error': hasFieldError(item.code, 'reservation_time_for_payouts') }"
                                    />
                                    <p
                                        v-if="errorMessage(item.code, 'reservation_time_for_payouts')"
                                        class="mt-0.5 text-right text-[10px] text-error"
                                    >
                                        {{ errorMessage(item.code, 'reservation_time_for_payouts') }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-else
                    class="rounded-box border border-dashed border-base-300/70 py-8 text-center text-xs text-base-content/60"
                >
                    Нет доступных валют.
                </p>

                <p class="text-[11px] leading-snug text-base-content/45">
                    Время резерва — сколько минут даётся трейдеру на отправку fiat после взятия выплаты.
                </p>
            </div>
        </ModalBodyNext>

        <ModalFooterNext>
            <button type="button" class="btn btn-sm btn-ghost" @click="close">
                Отмена
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :class="{ 'btn-disabled': processing || loading }"
                :disabled="processing || loading"
                @click="submit"
            >
                <span v-if="processing" class="loading loading-spinner loading-xs" />
                Сохранить
            </button>
        </ModalFooterNext>
    </ModalNext>
</template>
