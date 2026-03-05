<script setup>
import InputLabel from "@/Components/InputLabel.vue";
import InputHelper from "@/Components/InputHelper.vue";
import TextInput from "@/Components/TextInput.vue";
import {computed, ref, watch} from "vue";
import GatewayLogo from "@/Components/GatewayLogo.vue";

const emit = defineEmits(['updated']);

const props = defineProps({
    merchantId: {
        type: Number,
        required: true,
    },
    gatewaySettings: {
        type: [Object, Array],
        default: () => ({}),
    },
    paymentGateways: {
        type: Object,
        default: () => ({ data: [] }),
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
});

const deepClone = (value, fallback) => {
    if (value === undefined || value === null) {
        return fallback ?? null;
    }

    try {
        return JSON.parse(JSON.stringify(value));
    } catch (e) {
        return fallback ?? value;
    }
};

const gatewayEditMode = ref(false);
const processing = ref(false);
const localGatewaySettings = ref(deepClone(props.gatewaySettings, {}));
const macros = ref({
    commission: null,
    reservation_time: null,
});

watch(
    () => props.gatewaySettings,
    (value) => {
        localGatewaySettings.value = deepClone(value, {});
    },
    { immediate: true, deep: true }
);

const paymentGatewayList = computed(() => props.paymentGateways?.data ?? []);

const groupedGateways = computed(() => {
    const grouped = {};

    paymentGatewayList.value.forEach((gateway) => {
        const key = gateway.currency;
        if (!grouped[key]) {
            grouped[key] = [];
        }
        grouped[key].push(gateway);
    });

    return grouped;
});

const getSetting = (gatewayId, settingName) => {
    const gateway = localGatewaySettings.value[gatewayId] ?? {};

    if (settingName === 'active') {
        return gateway[settingName] !== undefined ? gateway[settingName] : true;
    }

    return gateway[settingName] ?? null;
};

const normalizeValue = (value, min = 1, max = 1000) => {
    if (value === "" || value === null || value === undefined) {
        return null;
    }

    const num = Number(value);

    if (Number.isNaN(num)) {
        return min;
    }

    return Math.min(Math.max(num, min), max);
};

const setSetting = (gatewayId, settingName, value) => {
    const settings = {...localGatewaySettings.value};

    if (!settings[gatewayId]) {
        settings[gatewayId] = {};
    }

    let normalizedValue = value;

    if (settingName === "custom_gateway_commission") {
        normalizedValue = normalizeValue(value, 0, 100);
    }

    if (settingName === "custom_gateway_reservation_time") {
        normalizedValue = normalizeValue(value, 1, 10000);
    }

    settings[gatewayId][settingName] = normalizedValue;
    localGatewaySettings.value = settings;
};

const submitGatewaySettings = () => {
    if (processing.value) {
        return;
    }

    processing.value = true;

    axios.patch(route("merchants.gateway-settings.update", props.merchantId), {
        gateway_settings: localGatewaySettings.value,
    }, {
        headers: {Accept: 'application/json'},
    }).then(({data}) => {
        emit('updated', data);
        gatewayEditMode.value = false;
    }).finally(() => {
        processing.value = false;
    });
};

const applyMacros = (type) => {
    if (type === "commission") {
        paymentGatewayList.value.forEach((gateway) => {
            setSetting(gateway.id, 'custom_gateway_commission', macros.value.commission);
        });
    }

    if (type === "reservation_time") {
        paymentGatewayList.value.forEach((gateway) => {
            setSetting(gateway.id, 'custom_gateway_reservation_time', macros.value.reservation_time);
        });
    }
};
</script>

<template>
    <div class="space-y-3">
        <div class="lg:flex block justify-between items-center">
            <div class="flex items-center">
                <button
                    v-if="gatewayEditMode === false"
                    @click.prevent="gatewayEditMode = true"
                    type="button"
                    class="btn btn-outline btn-primary btn-xs"
                >
                    Изменить
                </button>
                <button
                    v-else
                    @click.prevent="submitGatewaySettings"
                    type="button"
                    class="btn btn-success btn-xs"
                    :class="{ 'btn-disabled': processing }"
                    :disabled="processing"
                >
                    Сохранить
                </button>
            </div>
        </div>
        <div
            v-if="gatewayEditMode === true && isAdmin"
            class="p-5 sm:p-8 bg-base-100 shadow rounded-box"
        >
            <div>
                <header>
                    <h2 class="text-lg font-medium text-base-content">
                        Макросы для настроек
                    </h2>
                </header>
                <form class="mt-6 space-y-6">
                    <div class="grid lg:grid-cols-2 grid-cols-1 gap-6">
                        <div>
                            <InputLabel for="commission_macros" value="Комиссия" />

                            <TextInput
                                id="commission_macros"
                                v-model="macros.commission"
                                class="mt-1 block w-full"
                                step="1"
                                @input="applyMacros('commission')"
                            />

                            <InputHelper
                                model-value="Установит у всех методов указанную комиссию."
                            ></InputHelper>
                        </div>
                        <div>
                            <InputLabel
                                for="reservation_time_macros"
                                value="Время на сделку"
                            />

                            <TextInput
                                id="reservation_time_macros"
                                v-model="macros.reservation_time"
                                class="mt-1 block w-full"
                                step="1"
                                @input="applyMacros('reservation_time')"
                            />

                            <InputHelper
                                model-value="Установит у всех методов указанную время на сделку"
                            ></InputHelper>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="mb-5" v-for="(gateways, currency) in groupedGateways" :key="currency">
            <div>
        <span
            class="badge badge-neutral"
        >
          {{ currency.toUpperCase() }}
        </span>
            </div>
            <div class="mt-3 gap-3 grid 2xl:grid-cols-4 xl:grid-cols-2">
                <div
                    class="rounded-box bg-base-300 shadow"
                    v-for="gateway in gateways"
                    :key="gateway.id"
                >
                    <div
                        class="rounded-box text-sm font-semibold py-2 px-3"
                        :class="
                                        getSetting(gateway.id, 'active')
                                        ? 'bg-base-200'
                                        : 'bg-error/30'
                                      "
                    >
                        <div class="flex justify-between gap-2 items-center">
                            <div>
                                <GatewayLogo :img_path="gateway.logo_path" class="w-8 h-8 text-base-content/70"/>
                            </div>
                            <div :class="getSetting(gateway.id, 'custom_gateway_commission') > 0 ||
                                          getSetting(gateway.id, 'custom_gateway_commission') === 0 ? 'w-20' : 'w-25'">
                                <div
                                    class="truncate"
                                    :class="
                                        getSetting(gateway.id, 'active')
                                        ? 'text-base-content'
                                        : 'text-base-content'
                                      "
                                >
                                    {{ gateway.original_name }}
                                </div>
                            </div>
                            <div
                                class="text-base-content text-xl flex justify-between items-end gap-2"
                                :class="
                                        getSetting(gateway.id, 'active')
                                        ? 'text-base-content'
                                        : 'text-base-content'
                                    "
                                 >
                                <div class="flex items-center gap-2">
                                    <template
                                        v-if="
                                          getSetting(gateway.id, 'custom_gateway_commission') > 0 ||
                                          getSetting(gateway.id, 'custom_gateway_commission') === 0
                                        "
                                    >
                                        <div class="text-sm text-error line-through">
                                            {{ gateway.total_service_commission_rate_for_orders }}%
                                        </div>
                                        <div class="text-base-content">
                                            {{ getSetting(gateway.id, "custom_gateway_commission") }}%
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div>{{ gateway.total_service_commission_rate_for_orders }}%</div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="gatewayEditMode === true"
                        class="py-2 px-4 flex justify-between items-center"
                    >
                        <span class="text-xs text-base-content/70">Включен</span>
                        <label class="cursor-pointer flex items-center gap-2">
                            <input
                                type="checkbox"
                                class="toggle toggle-primary toggle-sm"
                                :checked="getSetting(gateway.id, 'active')"
                                @change="setSetting(gateway.id, 'active', $event.target.checked)"
                            />
                        </label>
                    </div>
                    <div
                        v-if="isAdmin && gatewayEditMode === true"
                        class="py-2 px-4 flex justify-between items-center"
                    >
                        <span class="text-xs text-base-content/70">Комиссия</span>
                        <input
                            type="text"
                            class="input input-bordered input-sm w-20 text-center"
                            :value="getSetting(gateway.id, 'custom_gateway_commission')"
                            @input="setSetting(gateway.id, 'custom_gateway_commission', $event.target.value)"
                        />
                    </div>
                    <div
                        v-if="isAdmin && gatewayEditMode === true"
                        class="py-2 px-4 flex justify-between items-center"
                    >
                        <span class="text-xs text-base-content/70">Время на сделку</span>
                        <input
                            type="text"
                            class="input input-bordered input-sm w-20 text-center"
                            :value="getSetting(gateway.id, 'custom_gateway_reservation_time')"
                            @input="setSetting(gateway.id, 'custom_gateway_reservation_time', $event.target.value)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
