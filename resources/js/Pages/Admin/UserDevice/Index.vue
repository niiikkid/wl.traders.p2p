<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import DeviceConnectSnapshotModal from '@/Modals/DeviceConnectSnapshotModal.vue';

defineOptions({ layout: AuthenticatedLayout })

const page = usePage();
const devices = computed(() => page.props.devices);

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

const shortToken = (token) => {
    if (!token) {
        return '—';
    }

    return `${token.slice(0, 8)}...${token.slice(-6)}`;
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

const copyToClipboard = async (text) => {
    if (!text) {
        return;
    }

    const notifyOk = () => alert('Токен скопирован в буфер обмена');

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            notifyOk();
            return;
        }
    } catch {
        // Fallback below handles restricted clipboard access.
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    const isCopied = document.execCommand('copy');
    document.body.removeChild(textarea);

    if (isCopied) {
        notifyOk();
    }
};
</script>

<template>
    <div>
        <Head title="Устройства" />

        <MainTableSection title="Устройства" :data="devices">
            <template #button>
                <div class="ml-auto flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        class="btn btn-outline btn-sm shrink-0"
                        @click="router.visit(route('admin.app.index'), { preserveScroll: true })"
                    >
                        Приложение
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline btn-sm shrink-0"
                        @click="router.visit(route('admin.sms-logs.index'), { preserveScroll: true })"
                    >
                        Сообщения
                    </button>
                </div>
            </template>

            <template #body>
                <div class="relative">
                    <div class="hidden xl:block shadow-md rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-xs">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th scope="col" class="px-3 py-2">ID</th>
                                    <th scope="col" class="px-3 py-2">Трейдер</th>
                                    <th scope="col" class="px-3 py-2">Название</th>
                                    <th scope="col" class="px-3 py-2">Токен</th>
                                    <th scope="col" class="px-3 py-2">Статус</th>
                                    <th scope="col" class="px-3 py-2">Режим</th>
                                    <th scope="col" class="px-3 py-2">Последний пинг</th>
                                    <th scope="col" class="px-3 py-2">Подключен</th>
                                    <th scope="col" class="px-3 py-2 text-right">Снимок</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="device in devices.data" :key="device.id">
                                    <td class="px-3 py-2 font-medium text-base-content">{{ device.id }}</td>
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-base-content">{{ device.user?.email ?? '—' }}</span>
                                    </td>
                                    <td class="px-3 py-2 font-medium whitespace-nowrap text-base-content">{{ device.name }}</td>
                                    <td class="px-3 py-2">
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs font-mono"
                                            :title="device.token"
                                            @click="copyToClipboard(device.token)"
                                        >
                                            {{ shortToken(device.token) }}
                                        </button>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span :class="['badge', 'badge-sm', device.android_id ? 'badge-success' : 'badge-warning']" class="text-nowrap">
                                            {{ device.android_id ? 'Подключено' : 'Не подключено' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span :class="['badge', 'badge-sm', processingModeClass(device)]" :title="processingModeTitle(device)">
                                            {{ processingModeShortTitle(device) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <DateTime v-if="device.latest_ping_at" :data="device.latest_ping_at" :plural="true" />
                                        <span v-else>нет данных</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <DateTime v-if="device.connected_at" :data="device.connected_at" />
                                        <span v-else>нет данных</span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button
                                            type="button"
                                            class="btn btn-outline btn-xs"
                                            :disabled="! device.has_connect_snapshot"
                                            @click="openSnapshotModal(device)"
                                        >
                                            Просмотр
                                        </button>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="xl:hidden space-y-3">
                        <div
                            v-for="device in devices.data"
                            :key="device.id"
                            class="card bg-base-100 shadow-sm"
                        >
                            <div class="card-body p-3 gap-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-xs text-base-content/60">ID: {{ device.id }}</div>
                                        <div class="font-medium text-base-content truncate">{{ device.name }}</div>
                                        <div class="text-sm text-base-content/70 truncate">{{ device.user?.email ?? '—' }}</div>
                                    </div>
                                    <span :class="['badge', 'badge-sm', device.android_id ? 'badge-success' : 'badge-warning']" class="text-nowrap">
                                        {{ device.android_id ? 'Подключено' : 'Не подключено' }}
                                    </span>
                                </div>

                                <div class="grid gap-2 text-sm">
                                    <div>
                                        <div class="text-xs text-base-content/70">Токен</div>
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs px-0 font-mono"
                                            :title="device.token"
                                            @click="copyToClipboard(device.token)"
                                        >
                                            {{ shortToken(device.token) }}
                                        </button>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Режим</span>
                                        <span :class="['badge', 'badge-sm', processingModeClass(device)]" :title="processingModeTitle(device)">
                                            {{ processingModeShortTitle(device) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base-content/70">Пинг</span>
                                        <DateTime v-if="device.latest_ping_at" :data="device.latest_ping_at" :plural="true" />
                                        <span v-else>нет данных</span>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-1">
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-xs"
                                        :disabled="! device.has_connect_snapshot"
                                        @click="openSnapshotModal(device)"
                                    >
                                        Снимок устройства
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
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
