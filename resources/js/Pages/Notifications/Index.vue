<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {computed, onMounted, ref} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from "@/Components/InputError.vue";
import CopyPaymentText from "@/Components/CopyPaymentText.vue";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import {useModalStore} from "@/store/modal.js";

defineOptions({layout: AuthenticatedLayout});

const modalStore = useModalStore();
const rules = ref(usePage().props.rules ?? []);
const filtersVariants = ref(usePage().props.filtersVariants ?? {event: [], currency: []});
const telegramAccount = ref(usePage().props.telegramAccount ?? {});

const ruleForm = useForm({
    event: '',
    currency: '',
    min_amount: '',
    enabled: true,
});
const ruleActionForm = useForm({
    enabled: false,
});
const telegramForm = useForm({});

const eventLabels = computed(() => {
    return Object.fromEntries((filtersVariants.value.event ?? []).map((item) => [item.value, item.name]));
});

const showMinAmountFilter = computed(() => ruleForm.event !== 'withdrawal.requested');
const showCurrencyFilter = computed(() => {
    return ruleForm.event !== 'withdrawal.requested' && ruleForm.event !== 'trust.balance.low';
});
const isTrustBalanceLowEvent = computed(() => ruleForm.event === 'trust.balance.low');

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

const telegramAlertText = computed(() => {
    if (telegramAccount.value?.is_active) {
        return 'Бот привязан к вашему аккаунту. При необходимости вы можете отвязать его здесь.';
    }

    return 'Чтобы получать уведомления в Telegram, привяжите бота через ссылку ниже.';
});

const initRuleDefaults = () => {
    if (!ruleForm.event && (filtersVariants.value.event ?? []).length) {
        ruleForm.event = filtersVariants.value.event[0].value;
    }
};

const createRule = () => {
    ruleForm.post(route('notifications.rules.store'), {
        preserveScroll: true,
        onSuccess: () => {
            if (showMinAmountFilter.value) {
                ruleForm.reset('min_amount');
            } else {
                ruleForm.reset('currency', 'min_amount');
            }
        },
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

onMounted(() => {
    initRuleDefaults();
});

router.on('success', () => {
    rules.value = usePage().props.rules ?? [];
    filtersVariants.value = usePage().props.filtersVariants ?? {event: [], currency: []};
    telegramAccount.value = usePage().props.telegramAccount ?? {};
    initRuleDefaults();
});
</script>

<template>
    <div>
        <Head title="Уведомления" />

        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Уведомления</h2>
            </div>

            <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
      
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
                        <p class="text-sm text-base-content/70">
                            Канал доставки всегда Telegram.
                        </p>
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
                            <div v-if="showCurrencyFilter">
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
                            <div v-if="showMinAmountFilter">
                                <label class="label">
                                    <span class="label-text">{{ isTrustBalanceLowEvent ? 'Порог траст-баланса (USDT)' : 'Мин. сумма (опционально)' }}</span>
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

            <div class="card bg-base-100 shadow">
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
                                    <span class="badge badge-ghost badge-xs">
                                        Telegram
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
        </div>

        <ConfirmModal />
    </div>
</template>
