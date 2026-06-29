<script setup>
import { formatDateTime } from '@/utils';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import DateTime from "@/Components/DateTime.vue";
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modals/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { useModalStore } from '@/store/modal.js';
import { useForm, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import Pagination from '@/Components/Pagination/Pagination.vue';

const props = defineProps({
    loginHistory: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        default: null,
    },
    loginHistoryLoggingEnabled: {
        type: Boolean,
        default: true,
    },
    canManageLoginHistoryLogging: {
        type: Boolean,
        default: false,
    },
});

const loginHistoryItems = computed(() => props.loginHistory?.data ?? []);

const loginHistoryMeta = computed(() => {
    const source = props.loginHistory ?? {};

    if (source.meta) {
        return source.meta;
    }

    return {
        current_page: source.current_page ?? 1,
        per_page: source.per_page ?? 10,
        total: source.total ?? 0,
        last_page: source.last_page ?? 1,
    };
});

const showLoginHistoryPagination = computed(() => (loginHistoryMeta.value.last_page ?? 1) > 1);

const openLoginHistoryPage = (page) => {
    router.visit(route('profile.edit'), {
        data: {
            page,
            per_page: loginHistoryMeta.value.per_page ?? 10,
        },
        preserveScroll: true,
        preserveState: true,
        only: ['loginHistory'],
    });
};

const modalStore = useModalStore();

const confirmingLogout = ref(false);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
});

const formatDate = (date) => {
    return formatDateTime(date);
};

const getStatusClass = (isSuccessful) => {
    return isSuccessful ? 'text-success' : 'text-error';
};

const getStatusText = (isSuccessful) => {
    return isSuccessful ? 'Успешно' : 'Неудачно';
};

const getLocationText = (item) => {
    const locationParts = [item.city, item.region, item.country].filter(Boolean);

    if (locationParts.length > 0) {
        return locationParts.join(', ');
    }

    return item.location || 'Локация не определена';
};

const getCountryCode = (item) => {
    if (!item.country_code) {
        return null;
    }

    return String(item.country_code).toUpperCase();
};

const getCountryFlag = (item) => {
    const code = getCountryCode(item);

    if (!code || code.length !== 2) {
        return '';
    }

    return code
        .split('')
        .map((char) => String.fromCodePoint(127397 + char.charCodeAt(0)))
        .join('');
};

const normalizeDeviceType = (deviceType) => {
    const normalized = String(deviceType || '').trim().toLowerCase();

    if (['компьютер', 'desktop'].includes(normalized)) {
        return 'desktop';
    }

    if (['телефон', 'mobile'].includes(normalized)) {
        return 'mobile';
    }

    if (['планшет', 'tablet'].includes(normalized)) {
        return 'tablet';
    }

    if (['робот', 'bot'].includes(normalized)) {
        return 'bot';
    }

    return normalized || 'unknown';
};

const normalizeOperatingSystem = (operatingSystem) => {
    const normalized = String(operatingSystem || '').trim();
    const lowerValue = normalized.toLowerCase();

    if (lowerValue === '') {
        return null;
    }

    if (lowerValue.includes('mac') || lowerValue.includes('os x')) {
        return 'macOS';
    }

    if (lowerValue.includes('windows')) {
        return 'Windows';
    }

    if (lowerValue.includes('linux')) {
        return 'Linux';
    }

    if (lowerValue.includes('android')) {
        return 'Android';
    }

    if (lowerValue.includes('iphone') || lowerValue.includes('ipad') || lowerValue.includes('ios')) {
        return 'iOS';
    }

    return normalized;
};

const normalizeBrowser = (browser) => {
    const normalized = String(browser || '').trim();

    if (normalized === '') {
        return null;
    }

    return normalized.split(' ')[0] || normalized;
};

const getDeviceSummary = (item) => {
    const browser = normalizeBrowser(item.browser);
    const platform = normalizeOperatingSystem(item.operating_system);
    const deviceType = normalizeDeviceType(item.device_type);

    return [platform, browser, deviceType].filter(Boolean).join(' · ');
};

const openLogoutModal = () => {
    confirmingLogout.value = true;

    nextTick(() => currentPasswordInput.value?.focus());
};

const closeLogoutModal = () => {
    confirmingLogout.value = false;
    form.reset();
    form.clearErrors();
};

const logoutOtherDevices = () => {
    form.post(route('profile.logout-other-devices'), {
        preserveScroll: true,
        onSuccess: closeLogoutModal,
        onError: () => currentPasswordInput.value?.focus(),
    });
};

const toggleLoginHistoryLoggingForm = useForm({});

const toggleLoginHistoryLogging = () => {
    const enabling = ! props.loginHistoryLoggingEnabled;

    modalStore.openConfirmModal({
        title: enabling ? 'Включить логирование сессий?' : 'Отключить логирование сессий?',
        body: enabling
            ? 'Новые входы снова будут записываться в историю сессий.'
            : 'Логирование сессий входа будет отключено. Существующая история сохранится, новые входы записываться не будут.',
        confirm_button_name: enabling ? 'Включить' : 'Отключить',
        cancel_button_name: 'Отмена',
        confirm: () => {
            toggleLoginHistoryLoggingForm.patch(route('profile.toggle-login-history-logging'), {
                preserveScroll: true,
            });
        },
    });
};
</script>

<template>
    <section class="text-left">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h2 class="text-base font-semibold text-base-content">Сессии входа</h2>

                <p class="text-xs leading-relaxed text-base-content/60">
                    История попыток входа: устройство, сеть и результат.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 sm:ml-auto">
                <button
                    v-if="canManageLoginHistoryLogging"
                    type="button"
                    class="btn btn-sm btn-outline"
                    :class="loginHistoryLoggingEnabled ? 'btn-warning' : 'btn-success'"
                    :disabled="toggleLoginHistoryLoggingForm.processing"
                    @click="toggleLoginHistoryLogging"
                >
                    {{ loginHistoryLoggingEnabled ? 'Отключить логирование' : 'Включить логирование' }}
                </button>

                <button
                    type="button"
                    class="btn btn-sm btn-outline btn-error"
                    @click="openLogoutModal"
                >
                    Выйти из других аккаунтов
                </button>
            </div>
        </header>

        <div v-if="status === 'other-sessions-logged-out'" class="alert alert-success mt-4 py-2 text-sm">
            Другие активные сессии завершены.
        </div>

        <div v-if="status === 'login-history-logging-disabled'" class="alert alert-warning mt-4 py-2 text-sm">
            Логирование сессий отключено. Новые входы не записываются в историю.
        </div>

        <div v-if="status === 'login-history-logging-enabled'" class="alert alert-success mt-4 py-2 text-sm">
            Логирование сессий снова включено.
        </div>

        <div
            v-if="canManageLoginHistoryLogging && !loginHistoryLoggingEnabled"
            class="alert alert-info mt-4 py-2 text-sm"
        >
            Логирование сессий входа отключено. Новые входы не записываются в историю.
        </div>

        <div class="mt-5">
            <!-- Desktop/tablet view (table) -->
            <div class="hidden xl:block">
                <div class="overflow-x-auto card">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Устройство</th>
                                <th>Локация</th>
                                <th>Окружение</th>
                                <th>Дата и время</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in loginHistoryItems" :key="item.id">
                                <td>
                                    <div class="font-medium">{{ getDeviceSummary(item) || 'Не определено' }}</div>
                                    <div class="text-xs text-base-content/60">{{ item.device_type || 'Тип не определён' }}</div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span v-if="getCountryFlag(item)" class="text-lg leading-none">{{ getCountryFlag(item) }}</span>
                                        <span class="font-medium">{{ getLocationText(item) }}</span>
                                    </div>
                                    <div class="text-xs text-base-content/60">
                                        IP: {{ item.ip_address || 'Не определен' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="font-medium">{{ item.browser || 'Не определено' }}</div>
                                    <div class="text-xs text-base-content/60">{{ item.operating_system || 'ОС не определена' }}</div>
                                </td>
                                <td>{{ formatDate(item.created_at) }}</td>
                                <td class="text-sm" :class="getStatusClass(item.is_successful)">
                                    {{ getStatusText(item.is_successful) }}
                                </td>
                            </tr>
                            <tr v-if="loginHistoryItems.length === 0">
                                <td colspan="5" class="text-center text-base-content/60">
                                    Сессий пока нет
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile view (cards list) -->
            <div class="xl:hidden space-y-2">
                <div
                    v-for="item in loginHistoryItems"
                    :key="item.id"
                    class="card bg-base-100 border border-base-300 shadow-sm"
                >
                    <div class="card-body px-3 py-2.5">
                        <div class="mb-1.5 flex items-start justify-between gap-2 border-b border-base-content/10 pb-1.5">
                            <div class="min-w-0 text-xs leading-snug">
                                <div class="text-[11px] uppercase tracking-wide text-base-content/60">Устройство</div>
                                <div class="mt-0.5 font-medium text-base-content">{{ getDeviceSummary(item) || 'Не определено' }}</div>
                                <div class="text-[11px] text-base-content/60">{{ item.device_type || 'Тип не определён' }}</div>
                            </div>
                            <div class="shrink-0">
                                <div
                                    class="badge badge-xs font-medium"
                                    :class="item.is_successful ? 'badge-success text-success-content' : 'badge-error text-error-content'"
                                >
                                    {{ getStatusText(item.is_successful) }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-1.5 text-xs leading-snug">
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">Локация</span>
                                <span class="min-w-0 text-right font-medium text-base-content inline-flex items-center justify-end gap-2">
                                    <span v-if="getCountryFlag(item)" class="text-lg leading-none">{{ getCountryFlag(item) }}</span>
                                    <span class="truncate">{{ getLocationText(item) }}</span>
                                </span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">IP адрес</span>
                                <span class="min-w-0 text-right font-medium text-base-content">{{ item.ip_address || 'Не определен' }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">Окружение</span>
                                <span class="min-w-0 text-right font-medium text-base-content truncate">{{ item.browser || 'Не определено' }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">ОС</span>
                                <span class="min-w-0 text-right font-medium text-base-content truncate">{{ item.operating_system || 'ОС не определена' }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">Устройство</span>
                                <span class="min-w-0 text-right font-medium text-base-content truncate">
                                    {{ getDeviceSummary(item) || 'Не определено' }}
                                </span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">Время</span>
                                <span class="min-w-0 text-right font-medium text-base-content">
                                    <DateTime :data="item.created_at" :simple="true"/>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="loginHistoryItems.length === 0" class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body px-3 py-3">
                        <div class="text-center text-xs text-base-content/60">
                            Сессий пока нет
                        </div>
                    </div>
                </div>
            </div>

            <Pagination
                v-if="showLoginHistoryPagination"
                class="mt-4"
                :model-value="loginHistoryMeta.current_page"
                :total-items="loginHistoryMeta.total"
                :per-page="loginHistoryMeta.per_page"
                previous-label="Назад"
                next-label="Вперёд"
                @page-changed="openLoginHistoryPage"
            />
        </div>

        <Modal :show="confirmingLogout" max-width="md" @close="closeLogoutModal">
            <form class="space-y-4" @submit.prevent="logoutOtherDevices">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">Выйти из других аккаунтов</h2>
                    <p class="mt-1 text-sm text-base-content/70">
                        Все другие активные сессии будут завершены. Текущая сессия останется активной.
                    </p>
                </div>

                <div>
                    <InputLabel
                        for="logout_other_devices_current_password"
                        value="Текущий пароль"
                        :error="!!form.errors.current_password"
                    />

                    <TextInput
                        id="logout_other_devices_current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="current-password"
                        :error="!!form.errors.current_password"
                        @input="form.clearErrors('current_password')"
                    />

                    <InputError :message="form.errors.current_password" class="mt-2" />
                </div>

                <div class="modal-action">
                    <button
                        type="button"
                        class="btn btn-sm btn-ghost"
                        :disabled="form.processing"
                        @click="closeLogoutModal"
                    >
                        Отмена
                    </button>

                    <button
                        type="submit"
                        class="btn btn-sm btn-error"
                        :disabled="form.processing"
                        :class="{ 'btn-disabled': form.processing }"
                    >
                        Выйти из других аккаунтов
                    </button>
                </div>
            </form>
        </Modal>

        <ConfirmModal />
    </section>
</template>
