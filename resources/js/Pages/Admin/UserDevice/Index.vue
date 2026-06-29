<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AutomationNav from '@/Components/Admin/AutomationNav.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DeviceConnectSnapshotModal from '@/Modals/DeviceConnectSnapshotModal.vue';
import UserDeviceCard from '@/Components/Device/UserDeviceCard.vue';
import { useDevicesAutoRefresh } from '@/composables/useDevicesAutoRefresh.js';

defineOptions({ layout: AuthenticatedLayout })

const page = usePage();
const devices = computed(() => page.props.devices);

useDevicesAutoRefresh(['devices']);

const snapshotModalOpen = ref(false);
const snapshotDeviceId = ref(null);
const snapshotDeviceName = ref('');

const processingModeTitle = (device) => {
    return device.user?.sms_auto_close_orders_enabled ? 'Автоматический' : 'Полуавтоматический';
};

const processingModeShortTitle = (device) => {
    return device.user?.sms_auto_close_orders_enabled ? 'А' : 'ПА';
};

const processingModeClass = (device) => {
    return device.user?.sms_auto_close_orders_enabled ? 'badge-success' : 'badge-warning';
};

const openSnapshotModal = (device) => {
    snapshotDeviceId.value = device.id;
    snapshotDeviceName.value = device.name ?? '';
    snapshotModalOpen.value = true;
};

const closeSnapshotModal = () => {
    snapshotModalOpen.value = false;
    snapshotDeviceId.value = null;
    snapshotDeviceName.value = '';
};
</script>

<template>
    <div>
        <Head title="Устройства" />

        <MainTableSection title="Устройства" :data="devices">
            <template #header>
                <AutomationNav current="devices" />
            </template>

            <template #body>
                <div
                    v-if="!devices.data?.length"
                    class="rounded-2xl border border-dashed border-base-content/15 bg-base-100 p-8 text-center shadow-sm"
                >
                    <p class="text-sm text-base-content/60">
                        Подключённых устройств пока нет.
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 xl:grid-cols-2"
                >
                    <UserDeviceCard
                        v-for="device in devices.data"
                        :key="device.id"
                        :device="device"
                        show-trader
                        :show-ping-activity="false"
                        :processing-mode-short-title="processingModeShortTitle(device)"
                        :processing-mode-class="processingModeClass(device)"
                        :processing-mode-title="processingModeTitle(device)"
                        @show-snapshot="openSnapshotModal"
                    />
                </div>
            </template>
        </MainTableSection>

        <DeviceConnectSnapshotModal
            :open="snapshotModalOpen"
            :device-id="snapshotDeviceId"
            :device-name="snapshotDeviceName"
            @close="closeSnapshotModal"
        />
    </div>
</template>
