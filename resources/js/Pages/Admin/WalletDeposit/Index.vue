<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CopyAddress from '@/Components/CopyAddress.vue';
import DateTime from '@/Components/DateTime.vue';
import MoneyValue from '@/Components/MoneyValue.vue';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    addresses: { type: Array, default: () => [] },
    invoices: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    settings: { type: Object, required: true },
});

defineOptions({ layout: AuthenticatedLayout });

const STATUS_LABELS = {
    pending: { label: 'Ожидание', badge: 'badge-info' },
    processing: { label: 'Подтверждается', badge: 'badge-warning' },
    paid: { label: 'Зачислено', badge: 'badge-success' },
    expired: { label: 'Истёк', badge: 'badge-neutral' },
    cancelled: { label: 'Отменён', badge: 'badge-ghost' },
    amount_mismatch: { label: 'Неверная сумма', badge: 'badge-error' },
    failed: { label: 'Ошибка', badge: 'badge-error' },
};

const statusInfo = (status) => STATUS_LABELS[status] ?? { label: status, badge: 'badge-neutral' };
const isFinal = (status) => ['paid', 'expired', 'cancelled', 'amount_mismatch', 'failed'].includes(status);

const addressForm = useForm({ address: '', label: '', is_active: true });

const submitAddress = () => {
    addressForm.post(route('admin.wallet-deposit.addresses.store'), {
        preserveScroll: true,
        onSuccess: () => addressForm.reset(),
    });
};

const toggleActive = (address) => {
    router.patch(
        route('admin.wallet-deposit.addresses.update', address.id),
        { label: address.label, is_active: !address.is_active },
        { preserveScroll: true },
    );
};

const refreshingAddressId = ref(null);

const refreshBalance = (address) => {
    refreshingAddressId.value = address.id;

    router.post(route('admin.wallet-deposit.addresses.refresh-balance', address.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            refreshingAddressId.value = null;
        },
    });
};

// Manual review
const reviewInvoice = ref(null);
const transfers = ref([]);
const loadingTransfers = ref(false);
const attachingTxid = ref(null);
const reviewError = ref('');
const note = ref('');

const openReview = (invoice) => {
    reviewInvoice.value = invoice;
    transfers.value = [];
    reviewError.value = '';
    note.value = '';
    document.getElementById('wallet_deposit_review_modal').showModal();
};

const loadTransfers = async () => {
    if (!reviewInvoice.value) {
        return;
    }

    reviewError.value = '';
    loadingTransfers.value = true;

    try {
        const { data } = await axios.get(route('admin.wallet-deposit.invoices.transfers', reviewInvoice.value.id), {
            headers: { Accept: 'application/json' },
        });
        transfers.value = data.transfers;
    } catch (e) {
        reviewError.value = e.response?.data?.message || 'Не удалось загрузить транзакции.';
    } finally {
        loadingTransfers.value = false;
    }
};

const attach = async (txid) => {
    if (!reviewInvoice.value) {
        return;
    }

    reviewError.value = '';
    attachingTxid.value = txid;

    try {
        await axios.post(
            route('admin.wallet-deposit.invoices.manual-attach', reviewInvoice.value.id),
            { txid, note: note.value || null },
            { headers: { Accept: 'application/json' } },
        );

        document.getElementById('wallet_deposit_review_modal').close();
        router.reload({ only: ['invoices', 'addresses'] });
    } catch (e) {
        reviewError.value = e.response?.data?.message || 'Не удалось привязать транзакцию.';
    } finally {
        attachingTxid.value = null;
    }
};

const filterByStatus = (event) => {
    router.get(route('admin.wallet-deposit.index'), { status: event.target.value || undefined }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <div class="space-y-6 p-4">
        <Head title="CryptoProcessing" />

        <div>
            <h1 class="text-xl font-semibold">CryptoProcessing</h1>
            <p class="text-sm text-base-content/60">Внутренний процессинг пополнений: пул адресов и ручной разбор.</p>
        </div>

        <!-- Settings (read-only) -->
        <div class="stats stats-vertical sm:stats-horizontal bg-base-100 shadow w-full">
            <div class="stat">
                <div class="stat-title">Время жизни инвойса</div>
                <div class="stat-value text-2xl">{{ settings.invoice_expires_in_minutes }} мин</div>
            </div>
            <div class="stat">
                <div class="stat-title">Подтверждений</div>
                <div class="stat-value text-2xl">{{ settings.min_confirmations }}</div>
            </div>
            <div class="stat">
                <div class="stat-title">Окно коллизий</div>
                <div class="stat-value text-2xl">{{ settings.amount_collision_percent }}%</div>
            </div>
        </div>

        <!-- Address pool -->
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h2 class="card-title text-base">Пул адресов</h2>

                <form class="flex flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="submitAddress">
                    <div class="flex-1">
                        <label class="label"><span class="label-text">Адрес TRON (TRC20)</span></label>
                        <input v-model="addressForm.address" type="text" class="input input-bordered w-full" placeholder="T..." />
                        <p v-if="addressForm.errors.address" class="mt-1 text-xs text-error">{{ addressForm.errors.address }}</p>
                    </div>
                    <div class="sm:w-48">
                        <label class="label"><span class="label-text">Метка</span></label>
                        <input v-model="addressForm.label" type="text" class="input input-bordered w-full" placeholder="Необязательно" />
                    </div>
                    <button type="submit" class="btn btn-primary" :disabled="addressForm.processing">
                        <span v-if="addressForm.processing" class="loading loading-spinner loading-sm"></span>
                        Добавить
                    </button>
                </form>

                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Адрес</th>
                                <th>Метка</th>
                                <th>Активен</th>
                                <th>Открытых</th>
                                <th>Баланс</th>
                                <th>Проверен</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="address in addresses" :key="address.id">
                                <td><CopyAddress :text="address.address" /></td>
                                <td>{{ address.label || '—' }}</td>
                                <td>
                                    <input type="checkbox" class="toggle toggle-sm toggle-success" :checked="address.is_active" @change="toggleActive(address)" />
                                </td>
                                <td>{{ address.open_invoices_count ?? 0 }}</td>
                                <td class="whitespace-nowrap">
                                    <MoneyValue v-if="address.balance !== null" :value="address.balance" currency="usdt" compact />
                                    <span v-else class="text-base-content/50">—</span>
                                </td>
                                <td>
                                    <DateTime v-if="address.last_checked_at" :data="address.last_checked_at" />
                                    <span v-else class="text-base-content/50">—</span>
                                </td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs btn-square"
                                        title="Обновить баланс"
                                        aria-label="Обновить баланс"
                                        :disabled="refreshingAddressId === address.id"
                                        @click="refreshBalance(address)"
                                    >
                                        <span v-if="refreshingAddressId === address.id" class="loading loading-spinner loading-xs" />
                                        <svg
                                            v-else
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.75"
                                            stroke="currentColor"
                                            class="size-4 shrink-0"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                                            />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="addresses.length === 0">
                                <td colspan="7" class="text-center text-base-content/50">Адресов пока нет</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Invoices -->
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <h2 class="card-title text-base">Инвойсы</h2>
                    <select class="select select-bordered select-sm" @change="filterByStatus">
                        <option value="">Все статусы</option>
                        <option v-for="s in statuses" :key="s" :value="s">{{ statusInfo(s).label }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Статус</th>
                                <th>Пользователь</th>
                                <th>Мерчант</th>
                                <th>Сумма</th>
                                <th>Адрес</th>
                                <th>Подтв.</th>
                                <th>Создан</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in invoices.data" :key="invoice.id">
                                <td><span class="badge" :class="statusInfo(invoice.status).badge">{{ statusInfo(invoice.status).label }}</span></td>
                                <td class="text-xs">{{ invoice.user.email }}</td>
                                <td class="text-xs">
                                    <template v-if="invoice.merchant">
                                        <div class="font-medium">{{ invoice.merchant.name }}</div>
                                        <div class="font-mono text-base-content/60">{{ invoice.merchant.uuid }}</div>
                                    </template>
                                    <span v-else class="text-base-content/50">—</span>
                                </td>
                                <td class="whitespace-nowrap">
                                    <MoneyValue :value="invoice.amount" currency="usdt" compact />
                                </td>
                                <td><CopyAddress :text="invoice.address" /></td>
                                <td>{{ invoice.confirmations }}/{{ invoice.required_confirmations }}</td>
                                <td><DateTime :data="invoice.created_at" /></td>
                                <td class="text-right">
                                    <button v-if="!isFinal(invoice.status)" class="btn btn-outline btn-xs" @click="openReview(invoice)">Проверить</button>
                                    <a v-else-if="invoice.tx_explorer_url" :href="invoice.tx_explorer_url" target="_blank" class="btn btn-ghost btn-xs">TX</a>
                                </td>
                            </tr>
                            <tr v-if="invoices.data.length === 0">
                                <td colspan="8" class="text-center text-base-content/50">Инвойсов пока нет</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="invoices.links" class="join mt-2 justify-center">
                    <button
                        v-for="link in invoices.meta?.links ?? []"
                        :key="link.label"
                        class="join-item btn btn-sm"
                        :class="{ 'btn-active': link.active, 'btn-disabled': !link.url }"
                        @click="link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Manual review modal -->
        <dialog id="wallet_deposit_review_modal" class="modal">
            <div class="modal-box max-w-3xl">
                <h3 class="text-lg font-bold">Ручной разбор инвойса</h3>

                <div v-if="reviewInvoice" class="mt-2 text-sm space-y-1">
                    <div><span class="text-base-content/60">Пользователь:</span> {{ reviewInvoice.user.email }}</div>
                    <div class="flex flex-wrap items-center gap-1">
                        <span class="text-base-content/60">Сумма инвойса:</span>
                        <MoneyValue :value="reviewInvoice.amount" currency="usdt" />
                    </div>
                    <div class="flex flex-wrap items-center gap-1">
                        <span class="text-base-content/60">Адрес:</span>
                        <CopyAddress :text="reviewInvoice.address" />
                    </div>
                </div>

                <div class="my-3 flex items-center gap-2">
                    <button class="btn btn-sm btn-primary" :disabled="loadingTransfers" @click="loadTransfers">
                        <span v-if="loadingTransfers" class="loading loading-spinner loading-sm"></span>
                        Загрузить транзакции адреса
                    </button>
                </div>

                <div class="alert alert-warning alert-soft text-xs">
                    При ручной привязке на баланс будет зачислена <b>фактическая</b> сумма транзакции.
                </div>

                <input v-model="note" type="text" class="input input-bordered input-sm mt-3 w-full" placeholder="Комментарий (необязательно)" />

                <p v-if="reviewError" class="mt-2 text-sm text-error">{{ reviewError }}</p>

                <div class="mt-3 overflow-x-auto max-h-80">
                    <table class="table table-xs">
                        <thead>
                            <tr>
                                <th>TXID</th>
                                <th>Сумма</th>
                                <th>Время</th>
                                <th>Флаги</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in transfers" :key="t.txid">
                                <td>
                                    <a :href="t.explorer_url" target="_blank" class="link link-primary">{{ t.txid.slice(0, 8) }}…</a>
                                </td>
                                <td class="whitespace-nowrap">
                                    <MoneyValue :value="t.amount" currency="usdt" compact />
                                    <span v-if="t.matches_invoice_amount" class="badge badge-success badge-xs ml-1">точная</span>
                                </td>
                                <td class="whitespace-nowrap"><DateTime :data="t.timestamp" /></td>
                                <td class="text-xs space-x-1">
                                    <span v-if="t.inside_invoice_window" class="badge badge-info badge-xs">в окне</span>
                                    <span v-if="t.already_attached" class="badge badge-error badge-xs">занята</span>
                                </td>
                                <td>
                                    <button
                                        class="btn btn-primary btn-xs"
                                        :disabled="t.already_attached || attachingTxid === t.txid"
                                        @click="attach(t.txid)"
                                    >
                                        <span v-if="attachingTxid === t.txid" class="loading loading-spinner loading-xs"></span>
                                        Привязать
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="transfers.length === 0 && !loadingTransfers">
                                <td colspan="5" class="text-center text-base-content/50">Загрузите транзакции для этого адреса</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn">Закрыть</button>
                    </form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
    </div>
</template>
