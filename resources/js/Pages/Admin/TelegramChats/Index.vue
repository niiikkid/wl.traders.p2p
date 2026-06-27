<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import Modal from '@/Components/Modals/Modal.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
import TableActionsDropdown from '@/Components/Table/TableActionsDropdown.vue';
import TableAction from '@/Components/Table/TableAction.vue';
import DataTable from '@/Components/Table/DataTable.vue';
import DataCardList from '@/Components/Table/DataCardList.vue';
import DataCard from '@/Components/Table/DataCard.vue';
import AppTooltip from '@/Components/AppTooltip.vue';
import { useModalStore } from '@/store/modal.js';

const props = defineProps({
    chats: {
        type: Object,
        required: true,
    },
    botSetting: {
        type: Object,
        required: true,
    },
    selectedChat: {
        type: Object,
        default: null,
    },
    messages: {
        type: Object,
        default: null,
    },
    messagesMeta: {
        type: Object,
        default: null,
    },
    tab: {
        type: String,
        default: 'active',
    },
    chatTypes: {
        type: Array,
        default: () => [],
    },
    chatStatuses: {
        type: Array,
        default: () => [],
    },
});

defineOptions({ layout: AuthenticatedLayout });

const modalStore = useModalStore();

const botSettingState = ref({ ...props.botSetting });
const botSettingsModalOpen = ref(false);
const botSettingsSaving = ref(false);
const webhookSettingUp = ref(false);
const botSettingsError = ref('');
const botSettingsSuccess = ref('');

const botToken = ref('');
const regenerateWebhookSecret = ref(false);
const localWebhookBaseUrl = ref('');

const messageDetail = ref(null);
const messageDetailModalOpen = ref(false);
const attachmentPreview = ref(null);
const attachmentPreviewModalOpen = ref(false);
const addTraderModalOpen = ref(false);

const CHAT_DETAIL_PROPS = ['selectedChat', 'messages', 'messagesMeta'];
const CHAT_LIST_PROPS = ['chats'];

const chatList = computed(() => props.chats?.data ?? []);
const messageList = computed(() => props.messages?.data ?? []);
const chatsMeta = computed(() => props.chats?.meta ?? null);
const chatDetailOpen = computed(() => props.selectedChat !== null);
const chatDetailLoading = ref(false);

const statusLabels = {
    pending_moderation: 'Ожидает модерации',
    active: 'Активен',
    disabled: 'Отключён',
    archived: 'В архиве',
};

const messageStatusLabels = {
    received: 'Получено',
    ignored: 'Проигнорировано',
    matched: 'Совпадение',
    processed: 'Обработано',
    failed: 'Ошибка',
    duplicate: 'Дубликат',
};

const messageTypeLabels = {
    text: 'Текст',
    photo: 'Фото',
    document: 'Документ',
    unknown: 'Неизвестно',
};

const botStatusSummary = computed(() => {
    if (!botSettingState.value.has_bot_token) {
        return { text: 'Токен не задан', class: 'badge-warning' };
    }
    if (botSettingState.value.webhook_last_error) {
        return { text: 'Ошибка webhook', class: 'badge-error' };
    }
    if (botSettingState.value.webhook_set_at) {
        return { text: 'Webhook установлен', class: 'badge-success' };
    }

    return { text: 'Webhook не установлен', class: 'badge-warning' };
});

const buildChatQuery = (extra = {}) => {
    const query = {
        tab: props.tab,
    };

    if (props.chats?.meta?.per_page) {
        query.per_page = props.chats.meta.per_page;
    }

    if ('page' in extra) {
        if (extra.page) {
            query.page = extra.page;
        }
    } else if (props.chats?.meta?.current_page) {
        query.page = props.chats.meta.current_page;
    }

    if ('chat' in extra) {
        if (extra.chat) {
            query.chat = extra.chat;
        }
    } else if (props.selectedChat?.id) {
        query.chat = props.selectedChat.id;
    }

    if ('messages_page' in extra) {
        if (extra.messages_page) {
            query.messages_page = extra.messages_page;
        }
    } else if (props.messagesMeta?.current_page) {
        query.messages_page = props.messagesMeta.current_page;
    }

    return query;
};

const visitChats = (extra = {}, options = {}) => {
    const {
        only = null,
        preserveScroll = true,
        preserveState = true,
    } = options;

    const visitOptions = {
        data: buildChatQuery(extra),
        preserveScroll,
        preserveState,
        onStart: () => {
            if (only?.some((prop) => CHAT_DETAIL_PROPS.includes(prop))) {
                chatDetailLoading.value = true;
            }
        },
        onFinish: () => {
            chatDetailLoading.value = false;
        },
    };

    if (only?.length) {
        visitOptions.only = only;
    }

    router.visit(route('admin.telegram-chats.index'), visitOptions);
};

const switchTab = (tab) => {
    router.visit(route('admin.telegram-chats.index'), {
        data: { tab },
        preserveScroll: false,
        preserveState: false,
    });
};

const selectChat = (chat) => {
    visitChats(
        { chat: chat.id, messages_page: 1 },
        {
            only: CHAT_DETAIL_PROPS,
            preserveScroll: chatDetailOpen.value,
            preserveState: true,
        },
    );
};

const clearSelectedChat = () => {
    visitChats(
        { chat: undefined, messages_page: undefined },
        {
            only: CHAT_DETAIL_PROPS,
            preserveScroll: false,
            preserveState: true,
        },
    );
};

const visitChatListPage = (page) => {
    visitChats(
        { page },
        {
            only: chatDetailOpen.value ? CHAT_LIST_PROPS : null,
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const visitMessagesPage = (page) => {
    visitChats(
        { messages_page: page },
        {
            only: ['messages', 'messagesMeta'],
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const chatUpdateForm = useForm({
    status: '',
    chat_type: '',
});

const teamTraders = computed(() => props.selectedChat?.team_traders ?? []);
const isTraderTeamChatSelected = computed(() => chatUpdateForm.chat_type === 'trader_team');
const canManageTeamTraders = computed(
    () => isTraderTeamChatSelected.value && props.selectedChat?.chat_type === 'trader_team',
);

const traderSearchQuery = ref('');
const traderSearchResults = ref([]);
const traderSearchLoading = ref(false);
const traderSearchDropdownOpen = ref(false);
const selectedTraderToAdd = ref(null);
const traderSearchRootRef = ref(null);
let traderSearchTimeout = null;

const addTraderForm = useForm({
    trader_id: '',
    telegram_username: '',
});

const updatingTraderId = ref(null);
const traderUsernameEdits = ref({});

const chatTypeLabel = (chatType) => {
    const match = props.chatTypes.find((item) => item.value === (chatType ?? ''));

    return match?.label ?? 'Не назначен';
};

const chatTypeVisual = (chatType) => {
    switch (chatType) {
        case 'dispute_processing':
            return {
                icon: 'gavel',
                containerClass: 'bg-warning/15 text-warning ring-warning/30',
            };
        case 'trader_team':
            return {
                icon: 'users',
                containerClass: 'bg-info/15 text-info ring-info/30',
            };
        default:
            return {
                icon: 'help',
                containerClass: 'bg-base-300/70 text-base-content/45 ring-base-content/15',
            };
    }
};

const existingTeamTraderIds = computed(() => new Set(teamTraders.value.map((trader) => trader.id)));

const filteredTraderSearchResults = computed(() =>
    traderSearchResults.value.filter((trader) => !existingTeamTraderIds.value.has(trader.id)),
);

const clearTraderToAdd = () => {
    selectedTraderToAdd.value = null;
    traderSearchQuery.value = '';
    addTraderForm.trader_id = '';
    addTraderForm.telegram_username = '';
    traderSearchResults.value = [];
    traderSearchDropdownOpen.value = false;
};

const openAddTraderModal = () => {
    clearTraderToAdd();
    addTraderModalOpen.value = true;
};

const closeAddTraderModal = () => {
    addTraderModalOpen.value = false;
    clearTraderToAdd();
};

watch(
    () => props.selectedChat,
    (chat, previousChat) => {
        if (!chat) {
            chatUpdateForm.reset();
            traderUsernameEdits.value = {};
            closeAddTraderModal();

            return;
        }

        if (previousChat?.id !== chat.id) {
            clearTraderToAdd();
            closeAddTraderModal();
        }

        chatUpdateForm.status = chat.status;
        chatUpdateForm.chat_type = chat.chat_type ?? '';

        const edits = {};
        (chat.team_traders ?? []).forEach((trader) => {
            edits[trader.id] = trader.telegram_username ?? '';
        });
        traderUsernameEdits.value = edits;
    },
    { immediate: true },
);

watch(traderSearchQuery, () => {
    clearTimeout(traderSearchTimeout);
    traderSearchTimeout = setTimeout(() => {
        searchTraders();
    }, 300);
});

const saveChatSettings = () => {
    if (!props.selectedChat) {
        return;
    }

    chatUpdateForm.patch(route('admin.telegram-chats.update', props.selectedChat.id), {
        preserveScroll: true,
    });
};

const searchTraders = async () => {
    traderSearchLoading.value = true;

    try {
        const response = await axios.get(route('admin.telegram-chats.trader-search'), {
            params: {
                query: traderSearchQuery.value.trim(),
            },
        });

        traderSearchResults.value = response.data.traders ?? [];
        traderSearchDropdownOpen.value = true;
    } catch (error) {
        traderSearchResults.value = [];
        console.error('Ошибка при поиске трейдеров:', error);
    } finally {
        traderSearchLoading.value = false;
    }
};

const selectTraderToAdd = (trader) => {
    selectedTraderToAdd.value = trader;
    traderSearchQuery.value = trader.email;
    addTraderForm.trader_id = trader.id;
    traderSearchDropdownOpen.value = false;
};

const addTeamTrader = () => {
    if (!props.selectedChat || !addTraderForm.trader_id) {
        return;
    }

    addTraderForm.post(route('admin.telegram-chats.traders.store', props.selectedChat.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeAddTraderModal();
        },
    });
};

const updateTeamTraderUsername = (trader) => {
    if (!props.selectedChat) {
        return;
    }

    updatingTraderId.value = trader.id;

    useForm({
        telegram_username: traderUsernameEdits.value[trader.id] ?? '',
    }).patch(route('admin.telegram-chats.traders.update', [props.selectedChat.id, trader.id]), {
        preserveScroll: true,
        onFinish: () => {
            updatingTraderId.value = null;
        },
    });
};

const removeTeamTrader = (trader) => {
    if (!props.selectedChat) {
        return;
    }

    modalStore.openConfirmModal({
        title: 'Удалить трейдера из команды?',
        body: `Трейдер ${trader.email} перестанет получать уведомления в этом чате.`,
        confirm_button_name: 'Удалить',
        cancel_button_name: 'Отмена',
        confirm: () => {
            useForm({}).delete(route('admin.telegram-chats.traders.destroy', [props.selectedChat.id, trader.id]), {
                preserveScroll: true,
            });
        },
    });
};

const onTraderSearchClickOutside = (event) => {
    if (traderSearchRootRef.value && !traderSearchRootRef.value.contains(event.target)) {
        traderSearchDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onTraderSearchClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', onTraderSearchClickOutside);
    clearTimeout(traderSearchTimeout);
});

const archiveChat = (chat) => {
    modalStore.openConfirmModal({
        title: 'Переместить чат в архив?',
        body: 'Обработка сообщений из этого чата будет остановлена.',
        confirm_button_name: 'В архив',
        cancel_button_name: 'Отмена',
        confirm: () => {
            useForm({}).post(route('admin.telegram-chats.archive', chat.id), {
                preserveScroll: true,
            });
        },
    });
};

const restoreChat = (chat) => {
    useForm({}).post(route('admin.telegram-chats.restore', chat.id), {
        preserveScroll: true,
    });
};

const activateChat = (chat) => {
    useForm({ status: 'active' }).patch(route('admin.telegram-chats.update', chat.id), {
        preserveScroll: true,
    });
};

const disableChat = (chat) => {
    useForm({ status: 'disabled' }).patch(route('admin.telegram-chats.update', chat.id), {
        preserveScroll: true,
    });
};

const toggleDebug = (chat, enabled) => {
    if (!enabled) {
        modalStore.openConfirmModal({
            title: 'Выключить режим отладки?',
            body: 'Новые сообщения без успешного открытия спора сохраняться не будут. В фоне будут удалены все накопленные сообщения и файлы, кроме тех, по которым спор уже открыт.',
            confirm_button_name: 'Выключить',
            cancel_button_name: 'Отмена',
            confirm: () => {
                useForm({ debug_enabled: false }).patch(route('admin.telegram-chats.debug.update', chat.id), {
                    preserveScroll: true,
                });
            },
        });

        return;
    }

    useForm({ debug_enabled: true }).patch(route('admin.telegram-chats.debug.update', chat.id), {
        preserveScroll: true,
    });
};

const openBotSettingsModal = async () => {
    botSettingsError.value = '';
    botSettingsSuccess.value = '';
    botToken.value = '';
    regenerateWebhookSecret.value = false;
    localWebhookBaseUrl.value = '';
    botSettingsModalOpen.value = true;

    try {
        const response = await axios.get(route('admin.telegram-bot.settings.show'));
        botSettingState.value = response.data.setting ?? botSettingState.value;
        localWebhookBaseUrl.value = botSettingState.value.local_webhook_base_url ?? '';
    } catch (error) {
        botSettingsError.value = error?.response?.data?.message ?? 'Не удалось загрузить настройки бота.';
    }
};

const saveBotSettings = async () => {
    if (botSettingsSaving.value) {
        return;
    }

    botSettingsSaving.value = true;
    botSettingsError.value = '';
    botSettingsSuccess.value = '';

    try {
        const payload = {
            bot_token: botToken.value || undefined,
            regenerate_webhook_secret: regenerateWebhookSecret.value,
        };

        if (botSettingState.value.is_local) {
            payload.local_webhook_base_url = localWebhookBaseUrl.value.trim() || null;
        }

        const response = await axios.patch(route('admin.telegram-bot.settings.update'), payload);
        botSettingState.value = response.data.setting ?? botSettingState.value;
        botSettingsSuccess.value = response.data.message ?? 'Настройки сохранены.';
        botToken.value = '';
        regenerateWebhookSecret.value = false;
        localWebhookBaseUrl.value = botSettingState.value.local_webhook_base_url ?? '';
    } catch (error) {
        botSettingsError.value = error?.response?.data?.message ?? 'Не удалось сохранить настройки.';
    } finally {
        botSettingsSaving.value = false;
    }
};

const setupWebhook = async () => {
    if (webhookSettingUp.value) {
        return;
    }

    webhookSettingUp.value = true;
    botSettingsError.value = '';
    botSettingsSuccess.value = '';

    try {
        const response = await axios.post(route('admin.telegram-bot.webhook.setup'));
        botSettingState.value = response.data.setting ?? botSettingState.value;
        botSettingsSuccess.value = response.data.message ?? 'Webhook установлен.';
    } catch (error) {
        botSettingsError.value = error?.response?.data?.message ?? 'Не удалось установить webhook.';
        if (error?.response?.data?.setting) {
            botSettingState.value = error.response.data.setting;
        }
    } finally {
        webhookSettingUp.value = false;
    }
};

const openMessageDetail = (message) => {
    messageDetail.value = message;
    messageDetailModalOpen.value = true;
};

const closeMessageDetail = () => {
    messageDetailModalOpen.value = false;
    messageDetail.value = null;
};

const attachmentPreviewType = (attachment) => {
    const mime = attachment?.mime_type?.toLowerCase() ?? '';
    const extension = attachment?.extension?.toLowerCase() ?? '';

    if (mime.startsWith('image/')) {
        return 'image';
    }

    if (mime === 'application/pdf' || extension === 'pdf') {
        return 'pdf';
    }

    return null;
};

const isPreviewableAttachment = (attachment) => attachmentPreviewType(attachment) !== null;

const openAttachmentInNewTab = (attachment) => {
    if (!attachment?.download_url) {
        return;
    }

    window.open(attachment.download_url, '_blank')?.focus();
};

const openAttachmentPreview = (attachment) => {
    if (!attachment?.download_url) {
        return;
    }

    if (!isPreviewableAttachment(attachment)) {
        openAttachmentInNewTab(attachment);

        return;
    }

    attachmentPreview.value = attachment;
    attachmentPreviewModalOpen.value = true;
};

const closeAttachmentPreview = () => {
    attachmentPreviewModalOpen.value = false;
    attachmentPreview.value = null;
};

const openMessageFirstAttachment = (message) => {
    const attachment = message.attachments?.[0];

    if (!attachment) {
        return;
    }

    openAttachmentPreview(attachment);
};

const messagePreviewText = (message) => {
    const text = typeof message.text === 'string' ? message.text.trim() : '';
    const caption = typeof message.caption === 'string' ? message.caption.trim() : '';

    if (text !== '') {
        return text;
    }

    if (caption !== '') {
        return caption;
    }

    return '—';
};

const formatAttachmentSize = (bytes) => {
    const size = Number(bytes);

    if (!Number.isFinite(size) || size <= 0) {
        return '—';
    }

    if (size < 1024) {
        return `${size} б`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} КБ`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} МБ`;
};

const statusBadgeClass = (status) => {
    const map = {
        pending_moderation: 'badge-warning',
        active: 'badge-success',
        disabled: 'badge-ghost',
        archived: 'badge-neutral',
        received: 'badge-ghost',
        ignored: 'badge-ghost',
        matched: 'badge-info',
        processed: 'badge-success',
        failed: 'badge-error',
        duplicate: 'badge-warning',
    };

    return map[status] ?? 'badge-ghost';
};

watch(
    () => props.botSetting,
    (value) => {
        botSettingState.value = { ...value };
    },
    { deep: true },
);
</script>

<template>
    <div>
        <Head title="Telegram-чаты" />

        <ConfirmModal />

        <MainTableSection
            title="Telegram-чаты"
            info="Автоматическое открытие споров по сообщениям мерчантов в Telegram."
            :data="chats"
            :paginate="true"
            :display-pagination="!chatDetailOpen"
            :visit-extra-data="{
                tab,
                chat: selectedChat?.id,
            }"
        >
            <template #button>
                <div
                    class="inline-flex max-w-full flex-wrap items-center justify-end gap-2 rounded-xl border border-base-300 bg-base-300 px-2.5 py-1.5 shadow-sm"
                >
                    <span class="badge badge-sm" :class="botStatusSummary.class">{{ botStatusSummary.text }}</span>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline shrink-0 rounded-lg"
                        @click="openBotSettingsModal"
                    >
                        Настройки бота
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary shrink-0 rounded-lg"
                        :disabled="webhookSettingUp || !botSettingState.has_bot_token"
                        @click="setupWebhook"
                    >
                        {{ webhookSettingUp ? 'Устанавливаем...' : 'Установить webhook' }}
                    </button>
                </div>
            </template>

            <template #header>
                <div role="tablist" class="tabs tabs-boxed w-fit">
                    <button
                        type="button"
                        role="tab"
                        class="tab"
                        :class="{ 'tab-active': tab === 'active' }"
                        @click="switchTab('active')"
                    >
                        Активные
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="tab"
                        :class="{ 'tab-active': tab === 'archived' }"
                        @click="switchTab('archived')"
                    >
                        Архив
                    </button>
                </div>
            </template>

            <template #body>
                <div v-if="!chatDetailOpen">
                    <DataTable>
                        <template #head>
                            <th scope="col">Чат</th>
                            <th scope="col">Функция</th>
                            <th scope="col">Статус</th>
                            <th scope="col">Debug</th>
                            <th scope="col">Сообщений</th>
                            <th scope="col">Последнее</th>
                            <th scope="col"><span class="sr-only">Действия</span></th>
                        </template>
                                    <tr
                                        v-for="chat in chatList"
                                        :key="chat.id"
                                        class="bg-base-100 border-b border-base-200 last:border-none"
                                    >
                                        <th scope="row" class="font-medium text-base-content">
                                            {{ chat.display_title }}
                                        </th>
                                        <td>
                                            <span
                                                class="badge badge-sm badge-outline whitespace-nowrap"
                                                :class="chat.chat_type ? 'badge-primary' : 'badge-ghost'"
                                            >
                                                {{ chatTypeLabel(chat.chat_type) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm" :class="statusBadgeClass(chat.status)">
                                                {{ statusLabels[chat.status] ?? chat.status }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm" :class="chat.debug_enabled ? 'badge-info' : 'badge-ghost'">
                                                {{ chat.debug_enabled ? 'Вкл' : 'Выкл' }}
                                            </span>
                                        </td>
                                        <td>{{ chat.messages_count ?? 0 }}</td>
                                        <td>
                                            <DateTime v-if="chat.last_message_at" :data="chat.last_message_at" simple />
                                            <span v-else class="text-base-content/50">—</span>
                                            <div
                                                v-if="chat.last_message_status"
                                                class="text-xs text-base-content/60"
                                            >
                                                {{ messageStatusLabels[chat.last_message_status] ?? chat.last_message_status }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    class="btn btn-xs btn-primary btn-outline"
                                                    @click="selectChat(chat)"
                                                >
                                                    Открыть
                                                </button>
                                                <TableActionsDropdown button-class="btn btn-ghost btn-circle btn-xs">
                                                    <template v-if="tab === 'archived'">
                                                        <TableAction @click="restoreChat(chat)">
                                                            Восстановить
                                                        </TableAction>
                                                    </template>
                                                    <template v-else>
                                                        <TableAction
                                                            v-if="chat.status !== 'active'"
                                                            @click="activateChat(chat)"
                                                        >
                                                            Активировать
                                                        </TableAction>
                                                        <TableAction
                                                            v-if="chat.status === 'active'"
                                                            @click="disableChat(chat)"
                                                        >
                                                            Отключить
                                                        </TableAction>
                                                        <TableAction @click="archiveChat(chat)">
                                                            Архивировать
                                                        </TableAction>
                                                    </template>
                                                </TableActionsDropdown>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!chatList.length">
                                        <td colspan="7" class="bg-base-100 py-8 text-center text-base-content/60">
                                            Чаты не найдены.
                                        </td>
                                    </tr>
                    </DataTable>

                    <DataCardList>
                        <DataCard
                            v-for="chat in chatList"
                            :key="chat.id"
                        >
                            <div class="flex justify-between items-start gap-2 border-b border-base-content/10 pb-2 mb-1 min-w-0">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-base-content leading-snug break-words">
                                        {{ chat.display_title }}
                                    </div>
                                    <span
                                        class="badge badge-sm badge-outline whitespace-nowrap mt-1"
                                        :class="chat.chat_type ? 'badge-primary' : 'badge-ghost'"
                                    >
                                        {{ chatTypeLabel(chat.chat_type) }}
                                    </span>
                                </div>
                                <div class="shrink-0">
                                    <span class="badge badge-sm" :class="statusBadgeClass(chat.status)">
                                        {{ statusLabels[chat.status] ?? chat.status }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-[11px] leading-tight">
                                <div>
                                    <div class="text-[10px] text-base-content/50 uppercase">Debug</div>
                                    <div class="font-medium text-xs">
                                        <span class="badge badge-xs" :class="chat.debug_enabled ? 'badge-info' : 'badge-ghost'">
                                            {{ chat.debug_enabled ? 'Вкл' : 'Выкл' }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-base-content/50 uppercase">Сообщений</div>
                                    <div class="font-medium text-xs text-base-content">{{ chat.messages_count ?? 0 }}</div>
                                </div>
                                <div class="col-span-2">
                                    <div class="text-[10px] text-base-content/50 uppercase">Последнее</div>
                                    <div class="font-medium text-xs text-base-content inline-flex flex-wrap items-center gap-1">
                                        <DateTime v-if="chat.last_message_at" :data="chat.last_message_at" simple class="justify-start" />
                                        <span v-else class="text-base-content/50">—</span>
                                        <span v-if="chat.last_message_status" class="text-base-content/60">
                                            · {{ messageStatusLabels[chat.last_message_status] ?? chat.last_message_status }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="border-b border-base-content/10 my-2"></div>

                            <div class="flex items-center justify-end gap-1">
                                <button
                                    type="button"
                                    class="btn btn-xs btn-primary btn-outline"
                                    @click="selectChat(chat)"
                                >
                                    Открыть
                                </button>
                                <TableActionsDropdown button-class="btn btn-ghost btn-circle btn-xs">
                                    <template v-if="tab === 'archived'">
                                        <TableAction @click="restoreChat(chat)">
                                            Восстановить
                                        </TableAction>
                                    </template>
                                    <template v-else>
                                        <TableAction
                                            v-if="chat.status !== 'active'"
                                            @click="activateChat(chat)"
                                        >
                                            Активировать
                                        </TableAction>
                                        <TableAction
                                            v-if="chat.status === 'active'"
                                            @click="disableChat(chat)"
                                        >
                                            Отключить
                                        </TableAction>
                                        <TableAction @click="archiveChat(chat)">
                                            Архивировать
                                        </TableAction>
                                    </template>
                                </TableActionsDropdown>
                            </div>
                        </DataCard>
                        <div v-if="!chatList.length" class="py-8 text-center text-base-content/60">
                            Чаты не найдены.
                        </div>
                    </DataCardList>
                </div>

                <div v-else class="grid items-start gap-4 lg:grid-cols-[minmax(12rem,16rem)_1fr]">
                <div class="relative w-full min-w-0 self-start shadow-md rounded-table">
                    <div class="card bg-base-100 shadow">
                    <div class="flex w-full flex-col items-stretch gap-3 bg-base-200/30 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Чаты</p>
                        <ul class="flex w-full flex-col gap-1 p-0">
                            <li
                                v-for="chat in chatList"
                                :key="chat.id"
                                class="w-full rounded-lg cursor-pointer transition-colors hover:bg-base-200/70"
                                :class="{ 'bg-base-200': selectedChat?.id === chat.id }"
                                @click="selectChat(chat)"
                            >
                                <div class="flex w-full items-center justify-between gap-2 p-2">
                                    <div class="flex min-w-0 flex-1 items-center gap-2.5">
                                        <AppTooltip
                                            :tip="chatTypeLabel(chat.chat_type)"
                                            placement="right"
                                            wrapper-class="shrink-0"
                                        >
                                            <span
                                                class="flex size-8 items-center justify-center rounded-lg ring-1 ring-inset"
                                                :class="chatTypeVisual(chat.chat_type).containerClass"
                                            >
                                                <svg
                                                    v-if="chatTypeVisual(chat.chat_type).icon === 'gavel'"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="size-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    aria-hidden="true"
                                                >
                                                    <path d="m13 10l7.383 7.418c.823.82.823 2.148 0 2.967a2.11 2.11 0 0 1-2.976 0L10 13M6 9l4 4m3-3L9 6M3 21h7" />
                                                    <path d="m6.793 15.793l-3.586-3.586a1 1 0 0 1 0-1.414L5.5 8.5L6 9l3-3l-.5-.5l2.293-2.293a1 1 0 0 1 1.414 0l3.586 3.586a1 1 0 0 1 0 1.414L13.5 10.5L13 10l-3 3l.5.5l-2.293 2.293a1 1 0 0 1-1.414 0" />
                                                </svg>
                                                <svg
                                                    v-else-if="chatTypeVisual(chat.chat_type).icon === 'users'"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="size-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0-4 0m-2 8v-1a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v1M15 5a2 2 0 1 0 4 0a2 2 0 0 0-4 0m2 5h2a2 2 0 0 1 2 2v1M5 5a2 2 0 1 0 4 0a2 2 0 0 0-4 0m-2 8v-1a2 2 0 0 1 2-2h2" />
                                                </svg>
                                                <svg
                                                    v-else
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="size-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0-18 0m9 4v.01" />
                                                    <path d="M12 13a2 2 0 0 0 .914-3.782a1.98 1.98 0 0 0-2.414.483" />
                                                </svg>
                                            </span>
                                        </AppTooltip>
                                        <span class="min-w-0 flex-1 truncate font-medium leading-snug text-base-content">
                                            {{ chat.display_title }}
                                        </span>
                                    </div>
                                    <div class="shrink-0" @click.stop>
                                        <TableActionsDropdown button-class="btn btn-ghost btn-circle btn-xs">
                                            <template v-if="tab === 'archived'">
                                                <TableAction @click="restoreChat(chat)">
                                                    Восстановить
                                                </TableAction>
                                            </template>
                                            <template v-else>
                                                <TableAction
                                                    v-if="chat.status !== 'active'"
                                                    @click="activateChat(chat)"
                                                >
                                                    Активировать
                                                </TableAction>
                                                <TableAction
                                                    v-if="chat.status === 'active'"
                                                    @click="disableChat(chat)"
                                                >
                                                    Отключить
                                                </TableAction>
                                                <TableAction @click="archiveChat(chat)">
                                                    Архивировать
                                                </TableAction>
                                            </template>
                                        </TableActionsDropdown>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <p v-if="!chatList.length" class="text-sm text-base-content/60">
                            Чаты не найдены.
                        </p>
                        <div
                            v-if="chatsMeta && chatsMeta.last_page > 1"
                            class="flex items-center justify-between gap-2 border-t border-base-300 pt-2"
                        >
                            <button
                                type="button"
                                class="btn btn-xs btn-outline"
                                :disabled="chatsMeta.current_page <= 1"
                                @click="visitChatListPage(chatsMeta.current_page - 1)"
                            >
                                Назад
                            </button>
                            <span class="text-xs text-base-content/60">
                                {{ chatsMeta.current_page }} / {{ chatsMeta.last_page }}
                            </span>
                            <button
                                type="button"
                                class="btn btn-xs btn-outline"
                                :disabled="chatsMeta.current_page >= chatsMeta.last_page"
                                @click="visitChatListPage(chatsMeta.current_page + 1)"
                            >
                                Вперёд
                            </button>
                        </div>
                    </div>
                    </div>
                </div>

                <div class="relative w-full min-w-0 shadow-md rounded-table">
                    <div
                        v-if="chatDetailLoading"
                        class="absolute inset-0 z-10 flex items-center justify-center rounded-table bg-base-100/70"
                    >
                        <span class="loading loading-spinner loading-md text-primary" />
                    </div>
                    <div class="card bg-base-100 shadow">
                    <div
                        class="flex w-full flex-col gap-4 p-4 sm:p-6"
                        :class="{ 'pointer-events-none opacity-60': chatDetailLoading }"
                    >
                        <div class="flex items-start justify-between gap-2 border-b border-base-200 pb-3">
                            <h3 class="text-lg font-semibold text-base-content">
                                {{ selectedChat.display_title }}
                            </h3>
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm btn-square shrink-0"
                                aria-label="Закрыть"
                                @click="clearSelectedChat"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <template v-if="selectedChat">
                            <div class="flex flex-wrap gap-2">
                                <span class="badge" :class="statusBadgeClass(selectedChat.status)">
                                    {{ statusLabels[selectedChat.status] ?? selectedChat.status }}
                                </span>
                                <span class="badge badge-outline">
                                    {{ chatTypeLabel(selectedChat.chat_type) }}
                                </span>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                                <fieldset class="fieldset min-w-0 flex-1 sm:max-w-xs">
                                    <legend class="fieldset-legend">Статус</legend>
                                    <select
                                        v-model="chatUpdateForm.status"
                                        class="select select-bordered select-sm w-full"
                                    >
                                        <option
                                            v-for="item in chatStatuses"
                                            :key="item.value"
                                            :value="item.value"
                                        >
                                            {{ item.label }}
                                        </option>
                                    </select>
                                </fieldset>
                                <fieldset class="fieldset min-w-0 flex-1 sm:max-w-xs">
                                    <legend class="fieldset-legend">Функция чата</legend>
                                    <select
                                        v-model="chatUpdateForm.chat_type"
                                        class="select select-bordered select-sm w-full"
                                    >
                                        <option
                                            v-for="item in chatTypes"
                                            :key="item.value"
                                            :value="item.value"
                                        >
                                            {{ item.label }}
                                        </option>
                                    </select>
                                </fieldset>
                                <fieldset class="fieldset w-full shrink-0 sm:w-auto">
                                    <legend class="fieldset-legend invisible select-none" aria-hidden="true">&nbsp;</legend>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm w-full sm:w-auto"
                                        :disabled="chatUpdateForm.processing"
                                        @click="saveChatSettings"
                                    >
                                        {{ chatUpdateForm.processing ? 'Сохраняем...' : 'Сохранить' }}
                                    </button>
                                </fieldset>
                            </div>

                            <div v-if="!isTraderTeamChatSelected" class="flex flex-wrap items-center gap-3">
                                <label class="label cursor-pointer gap-2">
                                    <span class="label-text">Режим отладки</span>
                                    <input
                                        type="checkbox"
                                        class="toggle toggle-sm"
                                        :checked="selectedChat.debug_enabled"
                                        @change="toggleDebug(selectedChat, $event.target.checked)"
                                    >
                                </label>
                            </div>

                            <div v-if="isTraderTeamChatSelected" class="space-y-3 rounded-box border border-base-300 bg-base-200/30 p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-semibold text-base-content">Трейдеры</h4>
                                        <p class="text-xs text-base-content/60">
                                            Уведомления о новых спорах для выбранных трейдеров.
                                        </p>
                                    </div>
                                    <AppTooltip
                                        v-if="canManageTeamTraders"
                                        tip="Добавить трейдера"
                                        placement="left"
                                        wrapper-class="shrink-0"
                                    >
                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm btn-square"
                                            aria-label="Добавить трейдера"
                                            @click="openAddTraderModal"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="size-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true"
                                            >
                                                <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0-8 0m8 12h6m-3-3v6M6 21v-2a4 4 0 0 1 4-4h4" />
                                            </svg>
                                        </button>
                                    </AppTooltip>
                                </div>

                                <div
                                    v-if="!canManageTeamTraders"
                                    class="alert alert-warning text-sm"
                                >
                                    Сохраните настройки чата с функцией «Трейдеры», чтобы управлять составом команды.
                                </div>

                                <template v-else>
                                    <div
                                        v-if="teamTraders.length"
                                        class="overflow-hidden rounded-lg border border-base-300 bg-base-100"
                                    >
                                        <table class="table table-xs">
                                            <thead class="bg-base-300/80 text-[10px] uppercase tracking-wide text-base-content/70">
                                                <tr>
                                                    <th scope="col" class="font-medium">Трейдер</th>
                                                    <th scope="col" class="font-medium">Telegram</th>
                                                    <th scope="col" class="w-8">
                                                        <span class="sr-only">Действия</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="trader in teamTraders"
                                                    :key="trader.id"
                                                    class="border-b border-base-200/80 last:border-none"
                                                >
                                                    <td class="max-w-[10rem] truncate align-middle text-xs font-medium">
                                                        {{ trader.email }}
                                                    </td>
                                                    <td class="align-middle">
                                                        <div class="flex items-center gap-1">
                                                            <input
                                                                v-model="traderUsernameEdits[trader.id]"
                                                                type="text"
                                                                class="input input-bordered input-xs w-28 max-w-[7.5rem]"
                                                                placeholder="username"
                                                                autocomplete="off"
                                                            >
                                                            <button
                                                                type="button"
                                                                class="btn btn-xs btn-outline shrink-0 px-2"
                                                                :disabled="updatingTraderId === trader.id"
                                                                @click="updateTeamTraderUsername(trader)"
                                                            >
                                                                {{ updatingTraderId === trader.id ? '...' : 'Сохранить' }}
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle text-right">
                                                        <AppTooltip tip="Удалить" placement="left" wrapper-class="inline-flex">
                                                            <button
                                                                type="button"
                                                                class="btn btn-ghost btn-xs btn-square text-error hover:bg-error/10"
                                                                aria-label="Удалить трейдера"
                                                                @click="removeTeamTrader(trader)"
                                                            >
                                                                <svg
                                                                    xmlns="http://www.w3.org/2000/svg"
                                                                    class="size-4"
                                                                    viewBox="0 0 24 24"
                                                                    fill="none"
                                                                    stroke="currentColor"
                                                                    stroke-width="2"
                                                                    stroke-linecap="round"
                                                                    stroke-linejoin="round"
                                                                    aria-hidden="true"
                                                                >
                                                                    <path d="M4 7h16m-10 4v6m4-6v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3" />
                                                                </svg>
                                                            </button>
                                                        </AppTooltip>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p v-else class="text-sm text-base-content/60">
                                        Участники не добавлены.
                                    </p>
                                </template>
                            </div>

                            <div class="divider my-0 text-base-content/50">Сообщения</div>

                            <div class="overflow-x-auto">
                                <div class="shadow-md rounded-table relative">
                                    <div class="max-h-[32rem] overflow-x-auto overflow-y-auto card bg-base-100 shadow">
                                        <table class="table table-sm">
                                            <thead class="sticky top-0 z-[1] text-xs uppercase bg-base-300">
                                                <tr>
                                                    <th scope="col">ID</th>
                                                    <th scope="col">Тип</th>
                                                    <th scope="col">Статус</th>
                                                    <th scope="col">UUID</th>
                                                    <th scope="col">Дата</th>
                                                    <th scope="col"><span class="sr-only">Действия</span></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template
                                                    v-for="message in messageList"
                                                    :key="message.id"
                                                >
                                                    <tr class="bg-base-100 border-b-0 [&_th]:!border-b-0 [&_td]:!border-b-0 [&_th]:pb-1 [&_td]:pb-1">
                                                        <th scope="row" class="font-medium whitespace-nowrap text-base-content align-top">
                                                            {{ message.telegram_message_id }}
                                                        </th>
                                                        <td class="align-top">{{ messageTypeLabels[message.message_type] ?? message.message_type }}</td>
                                                        <td class="align-top">
                                                            <span class="badge badge-xs" :class="statusBadgeClass(message.status)">
                                                                {{ messageStatusLabels[message.status] ?? message.status }}
                                                            </span>
                                                        </td>
                                                        <td class="align-top">
                                                            <CopyableOrderUid
                                                                v-if="message.detected_uuid"
                                                                :uuid="message.detected_uuid ?? ''"
                                                            />
                                                            <span v-else>—</span>
                                                        </td>
                                                        <td class="align-top">
                                                            <DateTime :data="message.created_at" simple />
                                                        </td>
                                                        <td class="align-top">
                                                            <div class="flex flex-wrap justify-end gap-1">
                                                                <button
                                                                    v-if="message.attachments?.length"
                                                                    type="button"
                                                                    class="btn btn-info btn-outline btn-xs"
                                                                    @click="openMessageFirstAttachment(message)"
                                                                >
                                                                    Файл
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-primary btn-outline btn-xs"
                                                                    @click="openMessageDetail(message)"
                                                                >
                                                                    Подробнее
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr class="bg-base-100 border-b border-base-200 last:border-none [&_td]:!border-t-0">
                                                        <td colspan="6" class="py-2 pt-0 align-middle">
                                                            <div class="flex items-center gap-2 text-xs">
                                                                <span class="shrink-0 text-base-content/60">Сообщение:</span>
                                                                <span class="min-w-0 flex-1 text-base-content/70 whitespace-pre-wrap break-words">
                                                                    {{ messagePreviewText(message) }}
                                                                </span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                                <tr v-if="!messageList.length">
                                                    <td colspan="6" class="bg-base-100 py-8 text-center text-base-content/60">
                                                        Сообщений нет.
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="messagesMeta && messagesMeta.last_page > 1"
                                class="flex justify-center gap-2"
                            >
                                <button
                                    type="button"
                                    class="btn btn-xs btn-outline"
                                    :disabled="messagesMeta.current_page <= 1"
                                    @click="visitMessagesPage(messagesMeta.current_page - 1)"
                                >
                                    Назад
                                </button>
                                <span class="text-sm self-center">
                                    {{ messagesMeta.current_page }} / {{ messagesMeta.last_page }}
                                </span>
                                <button
                                    type="button"
                                    class="btn btn-xs btn-outline"
                                    :disabled="messagesMeta.current_page >= messagesMeta.last_page"
                                    @click="visitMessagesPage(messagesMeta.current_page + 1)"
                                >
                                    Вперёд
                                </button>
                            </div>
                        </template>
                    </div>
                    </div>
                </div>
                </div>
            </template>
        </MainTableSection>

        <Modal :show="addTraderModalOpen" max-width="md" @close="closeAddTraderModal">
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Добавить трейдера</h3>
                <p class="text-sm text-base-content/60">
                    Найдите трейдера по email и при необходимости укажите Telegram username для упоминаний.
                </p>

                <div ref="traderSearchRootRef" class="relative w-full">
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Поиск по email</legend>
                        <div class="relative">
                            <input
                                v-model="traderSearchQuery"
                                type="text"
                                class="input input-bordered input-sm w-full pr-8"
                                placeholder="Email трейдера..."
                                autocomplete="off"
                                @focus="traderSearchDropdownOpen = true"
                                @input="traderSearchDropdownOpen = true"
                            >
                            <button
                                v-if="selectedTraderToAdd"
                                type="button"
                                class="btn btn-ghost btn-xs absolute right-1 top-1/2 -translate-y-1/2"
                                @click="clearTraderToAdd"
                            >
                                ×
                            </button>
                            <span
                                v-if="traderSearchLoading"
                                class="loading loading-spinner loading-sm absolute right-2 top-1/2 -translate-y-1/2"
                            />
                        </div>
                    </fieldset>
                    <div
                        v-if="traderSearchDropdownOpen && filteredTraderSearchResults.length > 0"
                        class="menu menu-sm bg-base-100 rounded-box absolute z-10 mt-1 max-h-48 w-full overflow-y-auto shadow"
                    >
                        <ul>
                            <li
                                v-for="trader in filteredTraderSearchResults"
                                :key="trader.id"
                            >
                                <button
                                    type="button"
                                    class="w-full text-left"
                                    @click="selectTraderToAdd(trader)"
                                >
                                    {{ trader.email }}
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div
                        v-if="traderSearchDropdownOpen && traderSearchQuery && !traderSearchLoading && filteredTraderSearchResults.length === 0"
                        class="alert alert-info mt-1 p-2 text-xs"
                    >
                        <span>Ничего не найдено</span>
                    </div>
                </div>

                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Telegram username</legend>
                    <input
                        v-model="addTraderForm.telegram_username"
                        type="text"
                        class="input input-bordered input-sm w-full"
                        placeholder="Необязательно"
                        autocomplete="off"
                    >
                    <p class="label text-xs text-base-content/60">
                        Telegram username без @ — для упоминания в уведомлениях.
                    </p>
                </fieldset>

                <p v-if="addTraderForm.errors.trader_id" class="text-xs text-error">
                    {{ addTraderForm.errors.trader_id }}
                </p>
                <p v-if="addTraderForm.errors.telegram_username" class="text-xs text-error">
                    {{ addTraderForm.errors.telegram_username }}
                </p>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost btn-sm" @click="closeAddTraderModal">
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="!addTraderForm.trader_id || addTraderForm.processing"
                        @click="addTeamTrader"
                    >
                        {{ addTraderForm.processing ? 'Добавляем...' : 'Добавить' }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="botSettingsModalOpen" max-width="lg" @close="botSettingsModalOpen = false">
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Настройки Telegram-бота</h3>

                <div v-if="botSettingsError" class="alert alert-error text-sm">
                    {{ botSettingsError }}
                </div>
                <div v-if="botSettingsSuccess" class="alert alert-success text-sm">
                    {{ botSettingsSuccess }}
                </div>

                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Токен бота</legend>
                    <input
                        v-model="botToken"
                        type="password"
                        class="input input-bordered w-full"
                        autocomplete="off"
                        placeholder="Введите новый токен"
                    >
                    <p class="label">
                        <span v-if="botSettingState.has_bot_token">Токен сохранён. Оставьте поле пустым, чтобы не менять.</span>
                        <span v-else>Укажите токен отдельного бота для чат-автоматизации.</span>
                    </p>
                </fieldset>

                <label class="label cursor-pointer justify-start gap-3">
                    <input v-model="regenerateWebhookSecret" type="checkbox" class="checkbox checkbox-sm">
                    <span class="label-text">Перегенерировать секрет webhook</span>
                </label>

                <fieldset v-if="botSettingState.is_local" class="fieldset">
                    <legend class="fieldset-legend">Домен webhook (локальная среда)</legend>
                    <input
                        v-model="localWebhookBaseUrl"
                        type="url"
                        class="input input-bordered w-full"
                        autocomplete="off"
                        placeholder="https://example.com"
                    >
                    <p class="label text-wrap">
                        Публичный URL туннеля (Expose, ngrok и т.п.) без пути. Пустое значение — стандартный домен приложения.
                    </p>
                </fieldset>

                <div class="rounded-box border border-base-300 bg-base-200/40 p-3 text-sm space-y-1">
                    <p><span class="text-base-content/60">URL webhook:</span> {{ botSettingState.webhook_url }}</p>
                    <p v-if="botSettingState.webhook_set_at">
                        <span class="text-base-content/60">Установлен:</span> {{ botSettingState.webhook_set_at }}
                    </p>
                    <p v-if="botSettingState.webhook_last_error" class="text-error">
                        {{ botSettingState.webhook_last_error }}
                    </p>
                    <template v-if="botSettingState.webhook_metadata">
                        <p v-if="botSettingState.webhook_metadata.pending_update_count != null">
                            Ожидающих обновлений: {{ botSettingState.webhook_metadata.pending_update_count }}
                        </p>
                        <p v-if="botSettingState.webhook_metadata.last_error_message" class="text-warning">
                            {{ botSettingState.webhook_metadata.last_error_message }}
                        </p>
                    </template>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost btn-sm" @click="botSettingsModalOpen = false">
                        Закрыть
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="botSettingsSaving"
                        @click="saveBotSettings"
                    >
                        {{ botSettingsSaving ? 'Сохраняем...' : 'Сохранить' }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="attachmentPreviewModalOpen" max-width="4xl" :stack-level="1" @close="closeAttachmentPreview">
            <div v-if="attachmentPreview" class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h3 class="text-lg font-semibold min-w-0 truncate">
                        {{ attachmentPreview.original_name || attachmentPreview.stored_name }}
                    </h3>
                    <span class="text-xs text-base-content/60 shrink-0">
                        {{ formatAttachmentSize(attachmentPreview.size) }}
                    </span>
                </div>

                <img
                    v-if="attachmentPreviewType(attachmentPreview) === 'image'"
                    :src="attachmentPreview.download_url"
                    :alt="attachmentPreview.original_name || attachmentPreview.stored_name"
                    class="max-h-[70vh] w-full rounded-box object-contain bg-base-200/40"
                />
                <iframe
                    v-else-if="attachmentPreviewType(attachmentPreview) === 'pdf'"
                    :src="attachmentPreview.download_url"
                    :title="attachmentPreview.original_name || attachmentPreview.stored_name"
                    class="h-[70vh] w-full rounded-box border border-base-300 bg-base-100"
                ></iframe>

                <div class="modal-action">
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        @click="openAttachmentInNewTab(attachmentPreview)"
                    >
                        В новой вкладке
                    </button>
                    <a
                        :href="attachmentPreview.download_url"
                        class="btn btn-outline btn-sm"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Скачать
                    </a>
                    <button type="button" class="btn btn-sm" @click="closeAttachmentPreview">
                        Закрыть
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="messageDetailModalOpen" max-width="2xl" @close="closeMessageDetail">
            <div v-if="messageDetail" class="space-y-4 max-h-[80vh] overflow-y-auto">
                <h3 class="text-lg font-semibold">Сообщение #{{ messageDetail.id }}</h3>

                <div class="grid gap-2 sm:grid-cols-2 text-sm">
                    <div>
                        <span class="text-base-content/60">Статус:</span>
                        {{ messageStatusLabels[messageDetail.status] ?? messageDetail.status }}
                    </div>
                    <div>
                        <span class="text-base-content/60">Тип:</span>
                        {{ messageTypeLabels[messageDetail.message_type] ?? messageDetail.message_type }}
                    </div>
                    <div v-if="messageDetail.detected_uuid">
                        <span class="text-base-content/60">UUID:</span>
                        <CopyableOrderUid :uuid="messageDetail.detected_uuid ?? ''" />
                    </div>
                    <div v-if="messageDetail.order_id">
                        <span class="text-base-content/60">Order ID:</span> {{ messageDetail.order_id }}
                    </div>
                    <div v-if="messageDetail.dispute_id">
                        <span class="text-base-content/60">Dispute ID:</span> {{ messageDetail.dispute_id }}
                    </div>
                    <div v-if="messageDetail.failure_reason" class="sm:col-span-2 text-error">
                        {{ messageDetail.failure_reason }}
                    </div>
                </div>

                <div v-if="messageDetail.text || messageDetail.caption" class="space-y-2 text-sm">
                    <p v-if="messageDetail.text"><span class="text-base-content/60">Сообщение:</span> {{ messageDetail.text }}</p>
                    <p v-if="messageDetail.caption"><span class="text-base-content/60">Сообщение:</span> {{ messageDetail.caption }}</p>
                </div>

                <div v-if="messageDetail.attachments?.length" class="space-y-3">
                    <p class="text-sm font-medium">Вложения</p>
                    <div
                        v-for="attachment in messageDetail.attachments"
                        :key="attachment.id"
                        class="space-y-2 rounded-box border border-base-300 bg-base-200/30 p-3"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2 text-sm">
                            <div class="min-w-0">
                                <div class="font-medium truncate">
                                    {{ attachment.original_name || attachment.stored_name }}
                                </div>
                                <div class="text-xs text-base-content/60">
                                    {{ attachment.mime_type || 'Тип неизвестен' }}
                                    <span class="px-1">·</span>
                                    {{ formatAttachmentSize(attachment.size) }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1 shrink-0">
                                <button
                                    v-if="attachment.download_url"
                                    type="button"
                                    class="btn btn-info btn-outline btn-xs"
                                    @click="openAttachmentPreview(attachment)"
                                >
                                    {{ isPreviewableAttachment(attachment) ? 'Просмотр' : 'Открыть' }}
                                </button>
                                <a
                                    v-if="attachment.download_url"
                                    :href="attachment.download_url"
                                    class="btn btn-outline btn-xs"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Скачать
                                </a>
                            </div>
                        </div>
                        <img
                            v-if="isPreviewableAttachment(attachment) && attachmentPreviewType(attachment) === 'image'"
                            :src="attachment.download_url"
                            :alt="attachment.original_name || attachment.stored_name"
                            class="max-h-64 w-full rounded-box object-contain bg-base-100"
                        />
                        <iframe
                            v-else-if="isPreviewableAttachment(attachment) && attachmentPreviewType(attachment) === 'pdf'"
                            :src="attachment.download_url"
                            :title="attachment.original_name || attachment.stored_name"
                            class="h-64 w-full rounded-box border border-base-300 bg-base-100"
                        ></iframe>
                    </div>
                </div>

                <fieldset class="fieldset">
                    <legend class="fieldset-legend">Raw payload</legend>
                    <pre class="mockup-code max-h-64 overflow-auto text-xs"><code>{{ JSON.stringify(messageDetail.raw_payload, null, 2) }}</code></pre>
                </fieldset>

                <div class="modal-action">
                    <button type="button" class="btn btn-sm" @click="closeMessageDetail">Закрыть</button>
                </div>
            </div>
        </Modal>
    </div>
</template>
