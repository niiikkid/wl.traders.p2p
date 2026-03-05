<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {computed, onMounted, ref, watch} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import DateTime from "@/Components/DateTime.vue";
import InputError from "@/Components/InputError.vue";
import CopyPaymentText from "@/Components/CopyPaymentText.vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import {useModalStore} from "@/store/modal.js";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";

const tableFiltersStore = useTableFiltersStore();
const modalStore = useModalStore();

const notifications = ref(usePage().props.notifications);
const rules = ref(usePage().props.rules);
const filtersVariants = ref(usePage().props.filtersVariants);
const telegramAccount = ref(usePage().props.telegramAccount);
const currentTab = ref('notifications');

const sectionData = computed(() => {
    if (currentTab.value === 'notifications') {
        return notifications.value;
    }

    return {
        data: [{}],
        meta: {
            current_page: 1,
            per_page: 1,
            total: 1,
        },
    };
});

const ruleForm = useForm({
    event: '',
    channels: ['in_app'],
    currency: '',
    min_amount: '',
    enabled: true,
});

const markAllForm = useForm({});
const ruleActionForm = useForm({
    enabled: false,
});
const telegramForm = useForm({});
const notificationActionForm = useForm({});

const eventLabelFallbacks = {
    'withdrawal.requested': 'Запрос на вывод средств',
    'order.assigned': 'Новая сделка',
    'dispute.opened': 'Открыт спор',
};

const normalizeEventVariants = () => {
    const events = filtersVariants.value.event ?? [];

    if (!events.length) {
        return;
    }

    filtersVariants.value = {
        ...filtersVariants.value,
        event: events.map((item) => {
            const keyName = `notifications.events.${item.value}`;
            const fallbackName = eventLabelFallbacks[item.value];
            const name = item.name === keyName ? (fallbackName ?? item.name) : item.name;

            return {
                ...item,
                name,
            };
        }),
    };
};

const eventLabels = computed(() => {
    return Object.fromEntries((filtersVariants.value.event ?? []).map((item) => [item.value, item.name]));
});

const showAmountFilters = computed(() => ruleForm.event !== 'withdrawal.requested');

const channelLabels = computed(() => {
    return Object.fromEntries((filtersVariants.value.channels ?? []).map((item) => [item.value, item.name]));
});

const deliveryStatusLabels = computed(() => {
    return Object.fromEntries((filtersVariants.value.delivery_status ?? []).map((item) => [item.value, item.name]));
});

const hasRuleAmount = (rule) => {
    return rule?.min_amount !== null && rule?.min_amount !== '' || rule?.currency !== null && rule?.currency !== '';
};

const ruleAmountLabel = (rule) => {
    const parts = [];

    if (rule?.min_amount !== null && rule?.min_amount !== '') {
        parts.push(`от ${rule.min_amount}`);
    }

    if (rule?.currency !== null && rule?.currency !== '') {
        parts.push(rule.currency.toUpperCase());
    }

    return parts.join(' ');
};

const openPage = (tab) => {
    tableFiltersStore.setTab(tab);
    tableFiltersStore.setCurrentPage(1);

    router.visit(route(route().current()), {
        preserveScroll: true,
        data: tableFiltersStore.getQueryData,
    });
};

const initTab = () => {
    if (tableFiltersStore.getTab === '') {
        tableFiltersStore.setTab('notifications');
    }
    currentTab.value = tableFiltersStore.getTab || 'notifications';
};

const initRuleDefaults = () => {
    if (!ruleForm.event && (filtersVariants.value.event ?? []).length) {
        ruleForm.event = filtersVariants.value.event[0].value;
    }
};

const markAllRead = () => {
    markAllForm.post(route('notifications.mark-all-read'), {
        preserveScroll: true,
    });
};

const markRead = (notification) => {
    notificationActionForm.patch(route('notifications.read', notification.id), {
        preserveScroll: true,
    });
};

const createRule = () => {
    ruleForm.post(route('notifications.rules.store'), {
        preserveScroll: true,
        onSuccess: () => {
            if (showAmountFilters.value) {
                ruleForm.reset('min_amount');
            } else {
                ruleForm.reset('currency', 'min_amount');
            }
        }
    });
};

const toggleRule = (rule) => {
    ruleActionForm.enabled = !rule.enabled;
    ruleActionForm.patch(route('notifications.rules.update', rule.id), {
        preserveScroll: true,
    });
};

const deleteRule = (rule) => {
    ruleActionForm.delete(route('notifications.rules.destroy', rule.id), {
        preserveScroll: true,
    });
};

const refreshTelegramLink = () => {
    telegramForm.post(route('notifications.telegram.link'), {
        preserveScroll: true,
    });
};

const unlinkTelegram = () => {
    modalStore.openConfirmModal({
        title: 'Отвязать Telegram-бота от вашего аккаунта?',
        confirm_button_name: 'Отвязать',
        confirm: () => {
            telegramForm.post(route('notifications.telegram.unlink'), {
                preserveScroll: true,
            });
        },
    });
};

const telegramAlertText = computed(() => {
    if (telegramAccount.value?.is_active) {
        return 'Бот привязан к вашему аккаунту. При необходимости вы можете отвязать его здесь.';
    }

    return 'Чтобы получать уведомления в Telegram, привяжите бота через ссылку ниже.';
});

const statusBadgeClass = (status) => {
    if (status === 'delivered') return 'badge-success';
    if (status === 'failed') return 'badge-error';
    return 'badge-warning';
};

router.on('success', () => {
    notifications.value = usePage().props.notifications;
    rules.value = usePage().props.rules;
    filtersVariants.value = usePage().props.filtersVariants;
    telegramAccount.value = usePage().props.telegramAccount;
    normalizeEventVariants();
    initTab();
    initRuleDefaults();
});

onMounted(() => {
    normalizeEventVariants();
    initTab();
    initRuleDefaults();
});

watch(() => ruleForm.event, (value) => {
    if (value === 'withdrawal.requested') {
        ruleForm.currency = '';
        ruleForm.min_amount = '';
    }
});

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Уведомления" />

        <MainTableSection
            title="Уведомления"
            :data="sectionData"
            :display-pagination="currentTab === 'notifications'"
        >
            <template v-slot:header>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click.prevent="openPage('notifications')"
                        :class="currentTab === 'notifications' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'"
                    >
                        Список
                    </button>
                    <button
                        type="button"
                        @click.prevent="openPage('settings')"
                        :class="currentTab === 'settings' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline'"
                    >
                        Настройки
                    </button>
                </div>
            </template>

            <template v-slot:table-filters>
                <FiltersPanel v-if="currentTab === 'notifications'" name="notifications">
                    <DropdownFilter name="event" title="События" />
                    <DropdownFilter name="delivery_status" title="Статус доставки" />
                    <FilterCheckbox name="only_unread" title="Только непрочитанные" />
                </FiltersPanel>
            </template>

            <template v-slot:button>
                <button
                    v-if="currentTab === 'notifications'"
                    type="button"
                    class="btn btn-sm btn-outline"
                    :disabled="markAllForm.processing"
                    @click.prevent="markAllRead"
                >
                    Отметить все прочитанными
                </button>
            </template>

            <template v-slot:body>
                <template v-if="currentTab === 'notifications'">
                    <div class="hidden xl:block rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th>Заголовок</th>
                                    <th>Статус</th>
                                    <th>Создано</th>
                                    <th class="text-right">
                                        <span class="sr-only">Действия</span>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="notification in notifications.data" :key="notification.id" class="bg-base-100 border-b last:border-none border-base-200">
                                    <td>
                                        <div class="font-medium text-base-content">{{ notification.title }}</div>
                                        <div class="text-xs text-base-content/70">{{ notification.body }}</div>
                                        <div class="mt-1">
                                            <span v-if="notification.read_at" class="badge badge-outline badge-xs">Прочитано</span>
                                            <span v-else class="badge badge-info badge-xs">Непрочитано</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm" :class="statusBadgeClass(notification.status)">
                                            {{ deliveryStatusLabels[notification.status] ?? notification.status }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <DateTime :data="notification.created_at" />
                                    </td>
                                    <td class="text-right">
                                        <button
                                            v-if="!notification.read_at"
                                            type="button"
                                            class="btn btn-xs btn-outline"
                                            :disabled="notificationActionForm.processing"
                                            @click.prevent="markRead(notification)"
                                            title="Отметить прочитанным"
                                            aria-label="Переключить статус прочтения"
                                        >
                                            <svg
                                                class="w-4 h-4"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path d="M16.707 5.293a1 1 0 0 1 0 1.414l-7.5 7.5a1 1 0 0 1-1.414 0l-3.5-3.5a1 1 0 1 1 1.414-1.414l2.793 2.793 6.793-6.793a1 1 0 0 1 1.414 0Z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="xl:hidden space-y-3">
                        <div
                            v-for="notification in notifications.data"
                            :key="notification.id"
                            class="card bg-base-100 shadow-sm"
                        >
                            <div class="card-body p-4 space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="font-medium text-base-content">{{ notification.title }}</div>
                                        <div class="text-xs text-base-content/70">{{ notification.body }}</div>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <span class="badge badge-xs" :class="statusBadgeClass(notification.status)">
                                            {{ deliveryStatusLabels[notification.status] ?? notification.status }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span v-if="notification.read_at" class="badge badge-outline badge-xs">Прочитано</span>
                                        <span v-else class="badge badge-info badge-xs">Непрочитано</span>
                                    </div>
                                    <DateTime :data="notification.created_at" />
                                </div>
                                <button
                                    v-if="!notification.read_at"
                                    type="button"
                                    class="btn btn-xs btn-outline"
                                    :disabled="notificationActionForm.processing"
                                    @click.prevent="markRead(notification)"
                                >
                                    Отметить прочитанным
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="card bg-base-100 shadow">
                            <div class="card-body space-y-4">
                                <div
                                    class="alert text-sm"
                                    :class="telegramAccount.is_active ? 'alert-success' : 'alert-info'"
                                >
                                    {{ telegramAlertText }}
                                </div>
                                <h3 class="text-lg font-semibold">Telegram</h3>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="badge" :class="telegramAccount.is_active ? 'badge-success' : 'badge-warning'">
                                            {{ telegramAccount.is_active ? 'Привязан' : 'Не привязан' }}
                                        </span>
                                        <span v-if="telegramAccount.bot_username" class="text-sm text-base-content/70">
                                            @{{ telegramAccount.bot_username }}
                                        </span>
                                    </div>
                                    <div v-if="!telegramAccount.is_active && telegramAccount.start_link" class="flex flex-wrap items-center gap-3">
                                        <a
                                            class="btn btn-sm btn-outline"
                                            :href="telegramAccount.start_link"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Открыть Telegram
                                        </a>
                                        <CopyPaymentText text="Скопировать ссылку" :copy_text="telegramAccount.start_link" />
                                    </div>
                                    <div v-else-if="!telegramAccount.is_active" class="text-sm text-base-content/70">
                                        Укажите `TELEGRAM_BOT_NAME`, чтобы сформировать ссылку привязки.
                                    </div>
                                </div>
                                <button
                                    v-if="telegramAccount.is_active"
                                    type="button"
                                    class="btn btn-sm btn-outline btn-error"
                                    :disabled="telegramForm.processing"
                                    @click.prevent="unlinkTelegram"
                                >
                                    Отвязать бота
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="btn btn-sm btn-primary"
                                    :disabled="telegramForm.processing"
                                    @click.prevent="refreshTelegramLink"
                                >
                                    Обновить ссылку
                                </button>
                            </div>
                        </div>

                        <div class="card bg-base-100 shadow">
                            <div class="card-body space-y-4">
                                <h3 class="text-lg font-semibold">Новое правило</h3>
                                <div class="grid gap-3">
                                    <div>
                                        <label class="label">
                                            <span class="label-text">Событие</span>
                                        </label>
                                        <select v-model="ruleForm.event" class="select select-bordered w-full">
                                            <option disabled value="">Выберите событие</option>
                                            <option v-for="event in filtersVariants.event" :key="event.value" :value="event.value">
                                                {{ event.name }}
                                            </option>
                                        </select>
                                        <InputError :message="ruleForm.errors.event" />
                                    </div>
                                    <div>
                                        <label class="label">
                                            <span class="label-text">Каналы</span>
                                        </label>
                                        <div class="flex flex-wrap gap-4">
                                            <label class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    value="in_app"
                                                    class="checkbox checkbox-sm"
                                                    v-model="ruleForm.channels"
                                                />
                                                <span class="text-sm">В панели</span>
                                            </label>
                                            <label class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    value="telegram"
                                                    class="checkbox checkbox-sm"
                                                    v-model="ruleForm.channels"
                                                />
                                                <span class="text-sm">Telegram</span>
                                            </label>
                                        </div>
                                        <InputError :message="ruleForm.errors.channels" />
                                    </div>
                                    <div v-if="showAmountFilters">
                                        <label class="label">
                                            <span class="label-text">Валюта (опционально)</span>
                                        </label>
                                        <select v-model="ruleForm.currency" class="select select-bordered w-full">
                                            <option value="">Любая</option>
                                            <option v-for="currency in filtersVariants.currency" :key="currency.value" :value="currency.value">
                                                {{ currency.name }}
                                            </option>
                                        </select>
                                        <InputError :message="ruleForm.errors.currency" />
                                    </div>
                                    <div v-if="showAmountFilters">
                                        <label class="label">
                                            <span class="label-text">Мин. сумма (опционально)</span>
                                        </label>
                                        <input
                                            v-model="ruleForm.min_amount"
                                            type="text"
                                            class="input input-bordered w-full"
                                            placeholder="Например, 100"
                                        />
                                        <InputError :message="ruleForm.errors.min_amount" />
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    :disabled="ruleForm.processing"
                                    @click.prevent="createRule"
                                >
                                    Создать правило
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow mt-6">
                        <div class="card-body">
                            <h3 class="text-lg font-semibold mb-4">Правила</h3>
                            <div v-if="!rules.length" class="text-sm text-base-content/70">
                                Пока что правил нет.
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="rule in rules"
                                    :key="rule.id"
                                    class="flex flex-wrap items-center justify-between gap-3 border border-base-300 rounded-box p-3"
                                >
                                    <div class="space-y-1">
                                        <div class="font-medium">{{ eventLabels[rule.event] ?? rule.event }}</div>
                                        <div class="flex flex-wrap gap-2 text-xs text-base-content/70">
                                            <span class="badge badge-ghost badge-xs" v-for="channel in rule.channels" :key="channel">
                                                {{ channelLabels[channel] ?? channel }}
                                            </span>
                                            <span v-if="hasRuleAmount(rule)" class="badge badge-outline badge-xs">
                                                {{ ruleAmountLabel(rule) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <label class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                class="toggle toggle-sm"
                                                :checked="rule.enabled"
                                                :disabled="ruleActionForm.processing"
                                                @change="toggleRule(rule)"
                                            />
                                            <span class="text-sm">{{ rule.enabled ? 'Включено' : 'Выключено' }}</span>
                                        </label>
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-outline btn-error"
                                            :disabled="ruleActionForm.processing"
                                            @click.prevent="deleteRule(rule)"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </template>
        </MainTableSection>

        <ConfirmModal />
    </div>
</template>
