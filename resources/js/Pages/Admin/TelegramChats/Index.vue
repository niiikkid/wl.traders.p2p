<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import DisplayUUID from '@/Components/DisplayUUID.vue';
import Modal from '@/Components/Modals/Modal.vue';
import ConfirmModal from '@/Components/Modals/ConfirmModal.vue';
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
    parserTypes: {
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

const messageDetail = ref(null);
const messageDetailModalOpen = ref(false);

const chatList = computed(() => props.chats?.data ?? []);
const messageList = computed(() => props.messages?.data ?? []);

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

const visitChats = (extra = {}) => {
    router.visit(route('admin.telegram-chats.index'), {
        data: {
            tab: props.tab,
            chat: props.selectedChat?.id ?? undefined,
            per_page: props.chats?.meta?.per_page,
            messages_page: props.messagesMeta?.current_page,
            ...extra,
        },
        preserveScroll: true,
        preserveState: true,
    });
};

const switchTab = (tab) => {
    router.visit(route('admin.telegram-chats.index'), {
        data: { tab },
        preserveScroll: false,
    });
};

const selectChat = (chat) => {
    visitChats({ chat: chat.id, messages_page: 1 });
};

const clearSelectedChat = () => {
    visitChats({ chat: undefined, messages_page: undefined });
};

const chatUpdateForm = useForm({
    status: '',
    parser_type: '',
});

watch(
    () => props.selectedChat,
    (chat) => {
        if (!chat) {
            return;
        }
        chatUpdateForm.status = chat.status;
        chatUpdateForm.parser_type = chat.parser_type;
    },
    { immediate: true },
);

const saveChatSettings = () => {
    if (!props.selectedChat) {
        return;
    }

    chatUpdateForm.patch(route('admin.telegram-chats.update', props.selectedChat.id), {
        preserveScroll: true,
    });
};

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
            body: 'Несвязанные со спорами сообщения больше не будут сохраняться. Накопленные debug-сообщения можно будет удалить на следующем этапе (очистка).',
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
    botSettingsModalOpen.value = true;

    try {
        const response = await axios.get(route('admin.telegram-bot.settings.show'));
        botSettingState.value = response.data.setting ?? botSettingState.value;
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
        const response = await axios.patch(route('admin.telegram-bot.settings.update'), {
            bot_token: botToken.value || undefined,
            regenerate_webhook_secret: regenerateWebhookSecret.value,
        });
        botSettingState.value = response.data.setting ?? botSettingState.value;
        botSettingsSuccess.value = response.data.message ?? 'Настройки сохранены.';
        botToken.value = '';
        regenerateWebhookSecret.value = false;
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

        <div class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-base-content sm:text-3xl">Telegram-чаты</h2>
                    <p class="text-sm text-base-content/60">
                        Автоматическое открытие споров по сообщениям мерчантов в Telegram.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge" :class="botStatusSummary.class">{{ botStatusSummary.text }}</span>
                    <button type="button" class="btn btn-outline btn-sm" @click="openBotSettingsModal">
                        Настройки бота
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="webhookSettingUp || !botSettingState.has_bot_token"
                        @click="setupWebhook"
                    >
                        {{ webhookSettingUp ? 'Устанавливаем...' : 'Установить webhook' }}
                    </button>
                </div>
            </div>

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

            <div class="grid gap-4 xl:grid-cols-2">
                <MainTableSection
                    title="Чаты"
                    :data="chats"
                    :paginate="true"
                    :visit-extra-data="{
                        tab,
                        chat: selectedChat?.id,
                    }"
                >
                    <template #body>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Чат</th>
                                        <th>Статус</th>
                                        <th>Debug</th>
                                        <th>Сообщений</th>
                                        <th>Последнее</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="chat in chatList"
                                        :key="chat.id"
                                        class="cursor-pointer hover:bg-base-200"
                                        :class="{ 'bg-base-200': selectedChat?.id === chat.id }"
                                        @click="selectChat(chat)"
                                    >
                                        <td>
                                            <div class="font-medium">{{ chat.display_title }}</div>
                                            <div class="text-xs text-base-content/60">{{ chat.telegram_chat_id }}</div>
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
                                        <td @click.stop>
                                            <div class="flex flex-wrap gap-1">
                                                <button
                                                    v-if="tab === 'archived'"
                                                    type="button"
                                                    class="btn btn-xs btn-outline"
                                                    @click="restoreChat(chat)"
                                                >
                                                    Восстановить
                                                </button>
                                                <template v-else>
                                                    <button
                                                        v-if="chat.status !== 'active'"
                                                        type="button"
                                                        class="btn btn-xs btn-success btn-outline"
                                                        @click="activateChat(chat)"
                                                    >
                                                        Активировать
                                                    </button>
                                                    <button
                                                        v-if="chat.status === 'active'"
                                                        type="button"
                                                        class="btn btn-xs btn-warning btn-outline"
                                                        @click="disableChat(chat)"
                                                    >
                                                        Отключить
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-xs btn-outline"
                                                        @click="archiveChat(chat)"
                                                    >
                                                        Архив
                                                    </button>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!chatList.length">
                                        <td colspan="6" class="text-center text-base-content/60">
                                            Чаты не найдены.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </MainTableSection>

                <div class="card bg-base-100 shadow">
                    <div class="card-body space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-lg font-semibold">
                                {{ selectedChat ? selectedChat.display_title : 'Детали чата' }}
                            </h3>
                            <button
                                v-if="selectedChat"
                                type="button"
                                class="btn btn-ghost btn-xs"
                                @click="clearSelectedChat"
                            >
                                Закрыть
                            </button>
                        </div>

                        <template v-if="selectedChat">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <span class="text-xs text-base-content/60">Telegram ID</span>
                                    <p class="font-mono text-sm">{{ selectedChat.telegram_chat_id }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-base-content/60">Username</span>
                                    <p class="text-sm">{{ selectedChat.username ? '@' + selectedChat.username : '—' }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span class="badge" :class="statusBadgeClass(selectedChat.status)">
                                    {{ statusLabels[selectedChat.status] ?? selectedChat.status }}
                                </span>
                                <span class="badge badge-outline">
                                    {{ selectedChat.parser_type }}
                                </span>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <fieldset class="fieldset">
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
                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">Парсер</legend>
                                    <select
                                        v-model="chatUpdateForm.parser_type"
                                        class="select select-bordered select-sm w-full"
                                    >
                                        <option
                                            v-for="item in parserTypes"
                                            :key="item.value"
                                            :value="item.value"
                                        >
                                            {{ item.label }}
                                        </option>
                                    </select>
                                </fieldset>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <label class="label cursor-pointer gap-2">
                                    <span class="label-text">Режим отладки</span>
                                    <input
                                        type="checkbox"
                                        class="toggle toggle-sm"
                                        :checked="selectedChat.debug_enabled"
                                        @change="toggleDebug(selectedChat, $event.target.checked)"
                                    >
                                </label>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm"
                                    :disabled="chatUpdateForm.processing"
                                    @click="saveChatSettings"
                                >
                                    Сохранить
                                </button>
                            </div>

                            <div class="divider my-0">Сообщения</div>

                            <div class="overflow-x-auto max-h-[32rem]">
                                <table class="table table-xs">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Тип</th>
                                            <th>Статус</th>
                                            <th>UUID</th>
                                            <th>Дата</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="message in messageList" :key="message.id">
                                            <td>{{ message.telegram_message_id }}</td>
                                            <td>{{ messageTypeLabels[message.message_type] ?? message.message_type }}</td>
                                            <td>
                                                <span class="badge badge-xs" :class="statusBadgeClass(message.status)">
                                                    {{ messageStatusLabels[message.status] ?? message.status }}
                                                </span>
                                            </td>
                                            <td>
                                                <DisplayUUID
                                                    v-if="message.detected_uuid"
                                                    :uuid="message.detected_uuid"
                                                />
                                                <span v-else>—</span>
                                            </td>
                                            <td>
                                                <DateTime :data="message.created_at" simple />
                                            </td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn btn-ghost btn-xs"
                                                    @click="openMessageDetail(message)"
                                                >
                                                    Подробнее
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="!messageList.length">
                                            <td colspan="6" class="text-center text-base-content/60">
                                                Сообщений нет.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div
                                v-if="messagesMeta && messagesMeta.last_page > 1"
                                class="flex justify-center gap-2"
                            >
                                <button
                                    type="button"
                                    class="btn btn-xs btn-outline"
                                    :disabled="messagesMeta.current_page <= 1"
                                    @click="visitChats({ messages_page: messagesMeta.current_page - 1 })"
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
                                    @click="visitChats({ messages_page: messagesMeta.current_page + 1 })"
                                >
                                    Вперёд
                                </button>
                            </div>
                        </template>

                        <p v-else class="text-sm text-base-content/60">
                            Выберите чат в таблице слева, чтобы просмотреть настройки и сообщения.
                        </p>
                    </div>
                </div>
            </div>
        </div>

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
                        <DisplayUUID :uuid="messageDetail.detected_uuid" />
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
                    <p v-if="messageDetail.text"><span class="text-base-content/60">Текст:</span> {{ messageDetail.text }}</p>
                    <p v-if="messageDetail.caption"><span class="text-base-content/60">Подпись:</span> {{ messageDetail.caption }}</p>
                </div>

                <div v-if="messageDetail.attachments?.length" class="space-y-2">
                    <p class="text-sm font-medium">Вложения</p>
                    <div
                        v-for="attachment in messageDetail.attachments"
                        :key="attachment.id"
                        class="flex flex-wrap items-center gap-2 text-sm"
                    >
                        <span>{{ attachment.original_name || attachment.stored_name }}</span>
                        <span class="text-base-content/60">({{ attachment.mime_type }}, {{ attachment.size }} б)</span>
                        <a
                            v-if="attachment.download_url"
                            :href="attachment.download_url"
                            class="link link-primary"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Скачать
                        </a>
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
