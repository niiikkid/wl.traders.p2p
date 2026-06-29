<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import {ref} from "vue";
import TraderAutomationNav from '@/Components/Trader/AutomationNav.vue';
import DevicePingHistoryModal from '@/Modals/DevicePingHistoryModal.vue';
import UserDeviceCard from '@/Components/Device/UserDeviceCard.vue';
import { useDevicesAutoRefresh } from '@/composables/useDevicesAutoRefresh.js';

const devices = ref(usePage().props.devices.data);

const smsAutoCloseEnabled = ref(!!usePage().props.smsAutoCloseEnabled);

const form = useForm({
    name: '',
});

const smsProcessingModeForm = useForm({
    sms_auto_close_orders_enabled: smsAutoCloseEnabled.value,
});

const submit = () => {
    form.post(route('trader.devices.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const updateSmsProcessingMode = (isEnabled) => {
    if (smsProcessingModeForm.processing) {
        return;
    }

    if (smsAutoCloseEnabled.value === isEnabled) {
        return;
    }

    smsProcessingModeForm.sms_auto_close_orders_enabled = !!isEnabled;
    smsProcessingModeForm.patch(route('trader.devices.sms-processing-mode.update'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            smsAutoCloseEnabled.value = !!isEnabled;
        },
        onError: () => {
            smsProcessingModeForm.sms_auto_close_orders_enabled = smsAutoCloseEnabled.value;
        },
    });
};

router.on('success', () => {
    devices.value = usePage().props.devices.data;
    smsAutoCloseEnabled.value = !!usePage().props.smsAutoCloseEnabled;
});

useDevicesAutoRefresh(['devices', 'smsAutoCloseEnabled']);

defineOptions({ layout: AuthenticatedLayout })

const pingModalOpen = ref(false);
const pingModalDevice = ref(null);

const openPingModal = (device) => {
    pingModalDevice.value = device;
    pingModalOpen.value = true;
};

const closePingModal = () => {
    pingModalOpen.value = false;
    pingModalDevice.value = null;
};
</script>

<template>
    <div>
        <Head title="Автоматика — Устройства" />

        <MainTableSection title="Автоматика" subtitle="Устройства" :data="devices" :paginate="false">
            <template v-slot:header>
                <div class="space-y-4 mb-6">
                    <TraderAutomationNav current="devices" />

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <section
                            class="card bg-base-100 shadow-md"
                            aria-labelledby="apk-download-title"
                        >
                            <div class="card-body gap-4 p-4 sm:p-6">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/15 text-primary ring-1 ring-primary/20">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="size-6"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path d="M17 1H7a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2m0 18H7V5h10zm-1-6h-3V8h-2v5H8l4 4z" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 id="apk-download-title" class="card-title text-base sm:text-lg">
                                                Скачайте и установите APK
                                            </h3>
                                            <span class="badge badge-primary badge-outline badge-sm gap-1">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="size-3"
                                                    viewBox="0 0 24 24"
                                                    fill="currentColor"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M16.61 15.15c-.46 0-.84-.37-.84-.83s.38-.82.84-.82s.84.36.84.82s-.38.83-.84.83m-9.2 0c-.46 0-.84-.37-.84-.83s.38-.82.84-.82s.83.36.83.82s-.37.83-.83.83m9.5-5.01l1.67-2.88c.09-.17.03-.38-.13-.47c-.17-.1-.38-.04-.45.13l-1.71 2.91A10.15 10.15 0 0 0 12 8.91c-1.53 0-3 .33-4.27.91L6.04 6.91a.334.334 0 0 0-.47-.13c-.17.09-.22.3-.13.47l1.66 2.88C4.25 11.69 2.29 14.58 2 18h20c-.28-3.41-2.23-6.3-5.09-7.86" />
                                                </svg>
                                                Android
                                            </span>
                                        </div>
                                        <p class="mt-1.5 text-sm text-base-content/70">
                                            Для получения СМС нужно приложение на вашем телефоне — установите APK и подключите устройство созданным токеном.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <a
                                        :href="route('app.download')"
                                        class="btn btn-primary btn-sm sm:btn-md gap-2 shadow-sm"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="size-4"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path d="M5 20h14v-2H5m14-9h-4V3H9v6H5l7 7z" />
                                        </svg>
                                        Скачать APK
                                    </a>
                                    <span class="text-xs text-base-content/50">
                                        Только для Android
                                    </span>
                                </div>
                            </div>
                        </section>

                        <section
                            class="card bg-base-100 shadow-md"
                            aria-labelledby="sms-processing-mode-title"
                        >
                            <div class="card-body gap-3 p-4 sm:p-5">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/15 text-primary ring-1 ring-primary/20">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="size-6"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path d="M17 11h-2V9h2m-4 2h-2V9h2m-4 2H7V9h2m11-7H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 id="sms-processing-mode-title" class="card-title text-base sm:text-lg">
                                                Режим обработки СМС
                                            </h3>
                                            <span
                                                :class="[
                                                    'badge badge-xs',
                                                    smsAutoCloseEnabled ? 'badge-success' : 'badge-warning',
                                                ]"
                                            >
                                                {{ smsAutoCloseEnabled ? 'Авто' : 'Полуавто' }}
                                            </span>
                                        </div>
                                        <p class="text-xs sm:text-sm text-base-content/60 mt-0.5">
                                            Авто — сделки закрываются сами. Полуавто — привязка к сделке, закрытие вручную.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-1 sm:grid-cols-2 gap-2"
                                    role="radiogroup"
                                    aria-labelledby="sms-processing-mode-title"
                                >
                                    <label
                                        :class="[
                                            'flex items-center gap-2.5 rounded-lg border px-3 py-2 transition-colors',
                                            smsAutoCloseEnabled ? 'border-primary bg-primary/10' : 'border-base-300 bg-base-200/40',
                                            smsProcessingModeForm.processing ? 'opacity-70 cursor-progress' : 'cursor-pointer',
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            name="sms-processing-mode"
                                            class="radio radio-xs radio-primary"
                                            :checked="smsAutoCloseEnabled"
                                            :disabled="smsProcessingModeForm.processing"
                                            @change="updateSmsProcessingMode(true)"
                                        >
                                        <span class="text-sm font-medium">Автоматический</span>
                                    </label>

                                    <label
                                        :class="[
                                            'flex items-center gap-2.5 rounded-lg border px-3 py-2 transition-colors',
                                            !smsAutoCloseEnabled ? 'border-primary bg-primary/10' : 'border-base-300 bg-base-200/40',
                                            smsProcessingModeForm.processing ? 'opacity-70 cursor-progress' : 'cursor-pointer',
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            name="sms-processing-mode"
                                            class="radio radio-xs radio-primary"
                                            :checked="!smsAutoCloseEnabled"
                                            :disabled="smsProcessingModeForm.processing"
                                            @change="updateSmsProcessingMode(false)"
                                        >
                                        <span class="text-sm font-medium">Полуавтоматический</span>
                                    </label>
                                </div>

                                <InputError class="text-error text-xs" :message="smsProcessingModeForm.errors.sms_auto_close_orders_enabled" />
                            </div>
                        </section>

                        <div class="card bg-base-100 shadow-md">
                            <div class="card-body gap-3 p-4 sm:p-5">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/15 text-primary ring-1 ring-primary/20">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="size-6"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path d="M22 17h-4v-7h4m1-2h-6a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1M4 6h18V4H4a2 2 0 0 0-2 2v11H0v3h14v-3H4z" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <h2 class="card-title text-base sm:text-lg">
                                            Новый токен устройства
                                        </h2>
                                        <p class="text-xs sm:text-sm text-base-content/60 mt-0.5">
                                            Один токен — одно устройство. Укажите название и создайте.
                                        </p>
                                    </div>
                                </div>

                                <form @submit.prevent="submit" class="space-y-2">
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <TextInput
                                            id="name"
                                            type="text"
                                            class="input input-bordered input-sm w-full sm:flex-1 min-w-0"
                                            v-model="form.name"
                                            required
                                            autofocus
                                            placeholder="Например: Samsung Galaxy S21"
                                            aria-label="Название устройства"
                                        />

                                        <PrimaryButton
                                            type="submit"
                                            class="btn btn-primary btn-sm shrink-0 gap-1.5 shadow-sm"
                                            :disabled="form.processing"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="size-4"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z" />
                                            </svg>
                                            Создать токен
                                        </PrimaryButton>
                                    </div>

                                    <InputError class="text-error text-xs" :message="form.errors.name" />

                                    <p
                                        v-if="form.recentlySuccessful"
                                        class="flex items-center gap-1.5 text-xs text-success"
                                        role="status"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="size-3.5 shrink-0"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="2"
                                            stroke="currentColor"
                                            aria-hidden="true"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        Токен создан
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template v-slot:body>
                <div v-if="!devices.length" class="rounded-2xl border border-dashed border-base-content/15 bg-base-100 p-8 text-center shadow-sm">
                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/20">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-7"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path d="M17 1H7a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2m0 18H7V5h10zm-1-6h-3V8h-2v5H8l4 4z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-base-content">
                        Устройств пока нет
                    </h3>
                    <p class="mt-1 text-sm text-base-content/60">
                        Создайте токен выше и подключите телефон через APK.
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 xl:grid-cols-2"
                >
                    <UserDeviceCard
                        v-for="device in devices"
                        :key="device.id"
                        :device="device"
                        @show-pings="openPingModal"
                    />
                </div>
            </template>
        </MainTableSection>

        <DevicePingHistoryModal
            :open="pingModalOpen"
            :device="pingModalDevice"
            @close="closePingModal"
        />
    </div>
</template>
