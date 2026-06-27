<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import {ref} from "vue";
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';
import TraderAutomationNav from '@/Components/Trader/AutomationNav.vue';
import DeviceTokenCopy from '@/Components/Trader/DeviceTokenCopy.vue';
import DevicePingHistoryModal from '@/Modals/DevicePingHistoryModal.vue';

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
})

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
        <Head title="Устройства" />

        <MainTableSection title="Устройства" :data="devices" :paginate="false">
            <template v-slot:header>
                <div class="space-y-4 mb-6">
                    <TraderAutomationNav current="devices" />

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <section
                            class="card bg-base-100 shadow-md"
                            aria-labelledby="apk-download-title"
                        >
                            <div class="card-body gap-4 p-4 sm:p-6">
                                <div class="flex items-start gap-4">
                                    <div class="rounded-2xl bg-primary/15 p-3 text-primary ring-1 ring-primary/20 shrink-0">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="size-8"
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

                        <div class="card bg-base-100 shadow-md">
                            <div class="card-body p-4 sm:p-6 gap-4">
                                <div>
                                    <h3 class="card-title">Режим обработки СМС</h3>
                                    <p class="text-sm text-base-content/70 mt-1">
                                        В автоматическом режиме система сама закрывает сделки по входящим поступлениям. В полуавтоматическом — СМС привязывается к сделке, а закрываете ее вы вручную.
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label
                                        :class="[
                                            'card border',
                                            smsAutoCloseEnabled ? 'border-primary bg-primary/10' : 'bg-base-200 border-base-300',
                                            smsProcessingModeForm.processing ? 'opacity-70 cursor-progress' : 'cursor-pointer',
                                        ]"
                                    >
                                        <div class="card-body p-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="font-medium text-xs">Автоматический</div>
                                                <input
                                                    type="radio"
                                                    name="sms-processing-mode"
                                                    class="radio radio-xs radio-primary"
                                                    :checked="smsAutoCloseEnabled"
                                                    :disabled="smsProcessingModeForm.processing"
                                                    @change="updateSmsProcessingMode(true)"
                                                >
                                            </div>
                                        </div>
                                    </label>

                                    <label
                                        :class="[
                                            'card border',
                                            !smsAutoCloseEnabled ? 'border-primary bg-primary/10' : 'bg-base-200 border-base-300',
                                            smsProcessingModeForm.processing ? 'opacity-70 cursor-progress' : 'cursor-pointer',
                                        ]"
                                    >
                                        <div class="card-body p-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="font-medium text-xs">Полуавтоматический</div>
                                                <input
                                                    type="radio"
                                                    name="sms-processing-mode"
                                                    class="radio radio-xs radio-primary"
                                                    :checked="!smsAutoCloseEnabled"
                                                    :disabled="smsProcessingModeForm.processing"
                                                    @change="updateSmsProcessingMode(false)"
                                                >
                                            </div>
                                        </div>
                                    </label>
                                </div>

                                <InputError class="text-error text-sm" :message="smsProcessingModeForm.errors.sms_auto_close_orders_enabled" />
                            </div>
                        </div>

                        <div class="card bg-base-100 shadow-md">
                            <div class="card-body gap-3 p-4 sm:p-5">
                                <div class="flex items-start gap-3">
                                    <div class="rounded-2xl bg-primary/15 p-2.5 text-primary ring-1 ring-primary/20 shrink-0">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="size-7"
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
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <DataTable>
                        <template #head>
                                    <th scope="col" class="px-6 py-3">
                                        Название
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Токен
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Статус
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Последний пинг
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right">

                                    </th>
                        </template>
                                <template v-for="device in devices" :key="device.id">
                                <tr>
                                    <th scope="row" class="px-6 py-3 font-medium whitespace-nowrap text-base-content">
                                        {{ device.name }}
                                    </th>
                                    <td class="px-6 py-3">
                                        <DeviceTokenCopy :token="device.token" />
                                    </td>
                                    <td class="px-6 py-3">
                                        <span :class="['badge', device.android_id ? 'badge-success' : 'badge-warning']" class="badge-sm text-nowrap">
                                            {{ device.android_id ? 'Подключено' : 'Не подключено' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <DateTime v-if="device.latest_ping_at" :data="device.latest_ping_at" :plural="true" />
                                        <span v-else>нет данных</span>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-sm btn-square"
                                            aria-label="История пингов"
                                            @click="openPingModal(device)"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="size-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                aria-hidden="true"
                                            >
                                                <path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                </template>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                            <DataCard
                                v-for="device in devices"
                                :key="device.id"
                            >
                                    <!-- Шапка: Название и последний пинг -->
                                    <div class="flex justify-between items-center">
                                        <div class="inline-flex items-center gap-2 min-w-0">
                                            <span class="font-medium text-base-content truncate">{{ device.name }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <span :class="['badge', 'badge-sm', device.android_id ? 'badge-success' : 'badge-warning']" class="text-nowrap">
                                                {{ device.android_id ? 'Подключено' : 'Не подключено' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="border-b border-base-content/10">

                                    </div>

                                    <!-- Для >= sm -->
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="text-xs text-base-content/70">Токен</div>
                                            <DeviceTokenCopy :token="device.token" truncate-class="w-40" />
                                        </div>
                                        <div>
                                            <DateTime v-if="device.latest_ping_at" class="justify-start" :data="device.latest_ping_at" :plural="true"/>
                                            <span v-else class="opacity-70">нет данных</span>
                                        </div>
                                        <div>
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-xs btn-square"
                                                aria-label="История пингов"
                                                @click.stop="openPingModal(device)"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="size-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Для xs -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="text-xs text-base-content/70">Токен</div>
                                                <DeviceTokenCopy :token="device.token" truncate-class="w-40" />
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-xs btn-square shrink-0"
                                                aria-label="История пингов"
                                                @click.stop="openPingModal(device)"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="size-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="mt-2">
                                            <DateTime v-if="device.latest_ping_at" class="justify-start" :data="device.latest_ping_at" :plural="true"/>
                                            <span v-else class="opacity-70">нет данных</span>
                                        </div>
                                    </div>
                            </DataCard>
                    </DataCardList>
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
