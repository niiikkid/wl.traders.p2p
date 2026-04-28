<script setup>
import {Head, router, useForm} from '@inertiajs/vue3';
import {computed, ref, watch} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import CascadeSectionNav from '@/Components/Admin/CascadeSectionNav.vue';
import IsActiveStatus from '@/Components/IsActiveStatus.vue';
import TableActionsDropdown from '@/Components/Table/TableActionsDropdown.vue';
import TableAction from '@/Components/Table/TableAction.vue';

const props = defineProps({
    cascadeProviders: Object,
    implementedProviders: Array,
    providerCallbackBaseUrl: String,
    liquidityUsers: Array,
    currencies: Array,
});

/** Список провайдеров: resolve() коллекции даёт массив, без обёртки { data }. */
const cascadeProviderList = computed(() => {
    const cp = props.cascadeProviders;

    if (! cp) {
        return [];
    }

    if (Array.isArray(cp)) {
        return cp;
    }

    if (Array.isArray(cp.data)) {
        return cp.data;
    }

    return [];
});

const isModalOpen = ref(false);
const editingProvider = ref(null);

const draggingRowId = ref(null);

const providerRows = ref([]);

watch(
    cascadeProviderList,
    (d) => {
        if (Array.isArray(d)) {
            providerRows.value = d.map((row) => ({...row}));
        }
    },
    {immediate: true, deep: true},
);

/**
 * Превью перетаскивания — вся строка (без этого браузер рисует только ручку).
 */
const setTableRowDragImage = (event) => {
    const row = event.currentTarget?.closest?.('tr');

    if (! row) {
        return;
    }

    const ghostRow = row.cloneNode(true);

    ghostRow.querySelectorAll('[draggable]').forEach((el) => {
        el.removeAttribute('draggable');
    });

    const rect = row.getBoundingClientRect();
    const offsetX = Math.round(event.clientX - rect.left);
    const offsetY = Math.round(event.clientY - rect.top);

    const host = document.createElement('div');
    const rowBg = typeof window.getComputedStyle === 'function'
        ? window.getComputedStyle(row).backgroundColor
        : '';

    host.style.cssText = [
        'position:fixed',
        'top:0',
        'left:0',
        `width:${rect.width}px`,
        'pointer-events:none',
        'z-index:99999',
        'opacity:0.97',
        'overflow:hidden',
        'border-radius:0.5rem',
        'box-shadow:0 10px 30px rgba(0,0,0,0.18)',
        rowBg && rowBg !== 'rgba(0, 0, 0, 0)' ? `background-color:${rowBg}` : '',
    ].filter(Boolean).join(';');

    const tbl = document.createElement('table');
    tbl.className = row.closest('table')?.className ?? 'table table-sm';
    tbl.style.width = '100%';
    tbl.style.tableLayout = 'fixed';
    const tb = document.createElement('tbody');
    tb.appendChild(ghostRow);
    tbl.appendChild(tb);
    host.appendChild(tbl);

    document.body.appendChild(host);
    event.dataTransfer.setDragImage(host, offsetX, offsetY);

    window.setTimeout(() => {
        host.remove();
    }, 0);
};

const onDragStart = (event, provider) => {
    setTableRowDragImage(event);

    draggingRowId.value = provider.id;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(provider.id));
};

const onDragOver = (event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
};

const onDrop = (event, targetProvider) => {
    event.preventDefault();
    const fromId = Number(event.dataTransfer.getData('text/plain'));

    if (! fromId || fromId === targetProvider.id) {
        return;
    }

    const arr = [...providerRows.value];
    const fromIdx = arr.findIndex((r) => r.id === fromId);
    const toIdx = arr.findIndex((r) => r.id === targetProvider.id);

    if (fromIdx === -1 || toIdx === -1) {
        return;
    }

    const [moved] = arr.splice(fromIdx, 1);

    arr.splice(toIdx, 0, moved);

    const next = arr.map((r, i) => ({...r, priority: i}));

    providerRows.value = next;

    router.patch(route('admin.cascade-providers.reorder'), {
        ids: next.map((r) => r.id),
    }, {
        preserveScroll: true,
    });
};

const onTbodyDrop = (event) => {
    const tr = event.target.closest('tr');

    if (! tr?.dataset?.providerId) {
        return;
    }

    const targetProvider = providerRows.value.find((p) => p.id === Number(tr.dataset.providerId));

    if (! targetProvider) {
        return;
    }

    onDrop(event, targetProvider);
};

const onDragEnd = () => {
    draggingRowId.value = null;
};

const form = useForm({
    code: '',
    name: '',
    provider_type: 'external',
    user_id: null,
    is_active: true,
    min_profit_percent: 0,
    base_url: '',
    access_token: '',
    currency_code: '',
    supported_currency_codes: [],
    timeout: 10,
    verify_ssl: true,
});

/** Все реализации для селекта: при создании — без internal. */
const providerOptions = computed(() => {
    const impl = Array.isArray(props.implementedProviders)
        ? props.implementedProviders
        : [];

    if (editingProvider.value) {
        return impl;
    }

    return impl.filter((provider) => provider.code !== 'internal');
});

const selectedImplementation = computed(() => {
    const impl = Array.isArray(props.implementedProviders) ? props.implementedProviders : [];

    return impl.find((provider) => provider.code === form.code);
});

/** Реализация `internal` — без внешнего API, ликвидности-пользователя и полей интеграции. */
const isInternalCascade = computed(() => {
    const code = editingProvider.value?.code ?? form.code;

    return code === 'internal';
});

const currencyOptions = computed(() => {
    return Array.isArray(props.currencies) ? props.currencies : [];
});

const defaultCurrencyCodes = computed(() => currencyOptions.value.map((currency) => currency.code));

const currencyName = (code) => {
    return currencyOptions.value.find((currency) => currency.code === code)?.name ?? code;
};

const implementationClassBasename = (fully_qualified_class) => {
    if (! fully_qualified_class) {
        return '';
    }
    const parts = fully_qualified_class.split('\\');

    return parts[parts.length - 1] ?? fully_qualified_class;
};

const openCreateModal = () => {
    editingProvider.value = null;
    form.reset();
    form.clearErrors();

    const impl = Array.isArray(props.implementedProviders) ? props.implementedProviders : [];
    const firstAvailable = impl.find((p) => p.code !== 'internal');

    form.defaults({
        code: firstAvailable?.code ?? '',
        name: firstAvailable?.name ?? '',
        provider_type: 'external',
        user_id: null,
        is_active: true,
        min_profit_percent: 0,
        base_url: '',
        access_token: '',
        currency_code: '',
        supported_currency_codes: [...defaultCurrencyCodes.value],
        timeout: 10,
        verify_ssl: true,
    });
    form.reset();
    fillFromImplementation();
    isModalOpen.value = true;
};

const openEditModal = (provider) => {
    editingProvider.value = provider;
    form.clearErrors();
    form.defaults({
        code: provider.code,
        name: provider.name,
        provider_type: provider.provider_type,
        user_id: provider.user_id,
        is_active: provider.is_active,
        min_profit_percent: provider.min_profit_percent ?? 0,
        base_url: provider.base_url ?? '',
        access_token: provider.access_token ?? '',
        currency_code: provider.currency_code ?? '',
        supported_currency_codes: provider.supported_currency_codes?.length
            ? [...provider.supported_currency_codes]
            : (provider.currency_code ? [provider.currency_code] : []),
        timeout: provider.timeout,
        verify_ssl: provider.verify_ssl,
    });
    form.reset();
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingProvider.value = null;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: closeModal,
    };

    if (editingProvider.value) {
        form.patch(route('admin.cascade-providers.update', editingProvider.value.id), options);
        return;
    }

    form.post(route('admin.cascade-providers.store'), options);
};

const fillFromImplementation = () => {
    if (! selectedImplementation.value || editingProvider.value) {
        return;
    }

    form.name = selectedImplementation.value.name;
    if (selectedImplementation.value.code === 'internal') {
        form.provider_type = 'internal';
        form.user_id = null;
        form.min_profit_percent = 0;
        form.base_url = '';
        form.access_token = '';
        form.currency_code = '';
        form.supported_currency_codes = [...defaultCurrencyCodes.value];
        form.verify_ssl = true;
    } else {
        form.provider_type = 'external';
    }
};

const selectedProviderCode = computed(() => {
    return editingProvider.value?.code ?? form.code ?? '';
});

const selectedProviderSupportsCallbackEndpoint = computed(() => {
    if (editingProvider.value) {
        return Boolean(editingProvider.value.supports_callback_endpoint);
    }

    return Boolean(selectedImplementation.value?.supports_callback_endpoint);
});

const selectedProviderCallbackEndpointUrl = computed(() => {
    if (editingProvider.value?.callback_endpoint_url) {
        return editingProvider.value.callback_endpoint_url;
    }

    if (! selectedProviderCode.value || ! props.providerCallbackBaseUrl) {
        return '';
    }

    return `${props.providerCallbackBaseUrl}/${selectedProviderCode.value}/callback`;
});

const copyCallbackEndpoint = async () => {
    if (! selectedProviderCallbackEndpointUrl.value || typeof navigator?.clipboard?.writeText !== 'function') {
        return;
    }

    try {
        await navigator.clipboard.writeText(selectedProviderCallbackEndpointUrl.value);
    } catch (error) {
        console.error('Failed to copy callback URL', error);
    }
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Провайдеры каскада" />

        <MainTableSection
            title="Провайдеры каскада"
            :data="cascadeProviderList"
            :paginate="false"
            :display-pagination="false"
        >
            <template #button>
                <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                    <button
                        type="button"
                        class="btn btn-sm btn-accent btn-outline btn-square"
                        aria-label="Добавить провайдера"
                        @click="openCreateModal"
                    >
                        <svg
                            class="w-5 h-5 shrink-0"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M3.75 4.5L4.5 3.75H10.5L11.25 4.5V10.5L10.5 11.25H4.5L3.75 10.5V4.5ZM5.25 5.25V9.75H9.75V5.25H5.25ZM13.5 3.75L12.75 4.5V10.5L13.5 11.25H19.5L20.25 10.5V4.5L19.5 3.75H13.5ZM14.25 9.75V5.25H18.75V9.75H14.25ZM17.25 20.25H15.75V17.25H12.75V15.75H15.75V12.75H17.25V15.75H20.25V17.25H17.25V20.25ZM4.5 12.75L3.75 13.5V19.5L4.5 20.25H10.5L11.25 19.5V13.5L10.5 12.75H4.5ZM5.25 18.75V14.25H9.75V18.75H5.25Z"
                                fill="currentColor"
                            />
                        </svg>
                    </button>
                    <CascadeSectionNav active="integrations" />
                </div>
            </template>

            <template v-slot:body>
                <div class="hidden xl:block overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th class="w-10 pe-0"><span class="sr-only">Порядок</span></th>
                                <th>ID</th>
                                <th>Провайдер</th>
                                <th class="text-end" aria-label="Приоритет" title="Приоритет">P.</th>
                                <th>Настройки</th>
                                <th>API</th>
                                <th>Статус</th>
                                <th><span class="sr-only">Действия</span></th>
                            </tr>
                        </thead>
                        <tbody
                            @dragover="onDragOver"
                            @drop="onTbodyDrop"
                        >
                            <tr
                                v-for="provider in providerRows"
                                :key="provider.id"
                                class="bg-base-100 border-b last:border-none border-base-200"
                                :class="{ 'opacity-50': draggingRowId === provider.id }"
                                :data-provider-id="provider.id"
                            >
                                <td class="w-10 pe-0 align-middle">
                                    <span
                                        role="button"
                                        tabindex="0"
                                        draggable="true"
                                        class="inline-flex cursor-grab active:cursor-grabbing select-none rounded px-1 py-1 text-base-content/50 hover:text-base-content hover:bg-base-200/80"
                                        data-drag-handle
                                        aria-label="Перетащить для изменения приоритета"
                                        @dragstart="onDragStart($event, provider)"
                                        @dragend="onDragEnd"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            class="w-5 h-5 shrink-0"
                                            aria-hidden="true"
                                        >
                                            <circle cx="9.5" cy="6" r="0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="9.5" cy="10" r="0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="9.5" cy="14" r="0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="9.5" cy="18" r="0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="14.5" cy="6" r="0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="14.5" cy="10" r="0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="14.5" cy="14" r="0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                            <circle cx="14.5" cy="18" r="0.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </td>
                                <th class="font-medium">{{ provider.id }}</th>
                                <td>
                                    <div class="font-medium text-nowrap">{{ provider.name }}</div>
                                    <div class="text-xs opacity-70 text-nowrap">{{ provider.code }}</div>
                                </td>
                                <td class="text-end tabular-nums whitespace-nowrap">
                                    {{ provider.priority ?? '—' }}
                                </td>
                                <td>
                                    <div class="text-nowrap font-medium">Мин. прибыль: {{ provider.min_profit_percent ?? 0 }}%</div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="currency in provider.supported_currency_codes"
                                            :key="`${provider.id}-${currency}`"
                                            class="badge badge-xs badge-outline"
                                            :title="currencyName(currency)"
                                        >
                                            {{ currency }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="max-w-64 truncate" :title="provider.base_url ?? ''">
                                        {{ provider.base_url || 'Пусто' }}
                                    </div>
                                    <div class="text-xs opacity-70 text-nowrap">
                                        Timeout: {{ provider.timeout ?? 'Пусто' }} сек.
                                    </div>
                                </td>
                                <td><IsActiveStatus :is_active="provider.is_active" /></td>
                                <td class="text-right">
                                    <TableActionsDropdown>
                                        <TableAction @click="openEditModal(provider)">
                                            Редактировать
                                        </TableAction>
                                    </TableActionsDropdown>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="xl:hidden space-y-3">
                    <div
                        v-for="provider in providerRows"
                        :key="provider.id"
                        class="card bg-base-100 shadow-sm"
                    >
                        <div class="card-body p-4 gap-3">
                            <div class="flex items-start justify-between gap-3 border-b border-base-content/10 pb-2">
                                <div>
                                    <div class="font-medium">{{ provider.name }}</div>
                                    <div class="text-xs opacity-70">{{ provider.code }}</div>
                                </div>
                                <IsActiveStatus :is_active="provider.is_active" />
                            </div>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div class="text-base-content/60" title="Приоритет">P.</div>
                                    <div class="font-medium">{{ provider.priority ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Timeout</div>
                                    <div class="font-medium">{{ provider.timeout ?? 'Пусто' }}</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Мин. прибыль</div>
                                    <div class="font-medium">{{ provider.min_profit_percent ?? 0 }}%</div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="currency in provider.supported_currency_codes"
                                            :key="`${provider.id}-mobile-${currency}`"
                                            class="badge badge-xs badge-outline"
                                            :title="currencyName(currency)"
                                        >
                                            {{ currency }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <div class="truncate text-xs opacity-70">{{ provider.base_url || 'Base URL не задан' }}</div>
                                <TableActionsDropdown>
                                    <TableAction @click="openEditModal(provider)">
                                        Редактировать
                                    </TableAction>
                                </TableActionsDropdown>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <dialog :open="isModalOpen" class="modal">
            <div class="modal-box w-11/12 max-w-3xl p-5">
                <button
                    type="button"
                    class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                    @click="closeModal"
                >
                    ✕
                </button>

                <h3 class="font-bold text-base mb-1">
                    {{ editingProvider ? 'Редактирование провайдера' : 'Новый провайдер каскада' }}
                </h3>
                <p class="text-xs opacity-70 mb-3">
                    Провайдера можно создать только для класса, найденного в папке реализаций.
                </p>

                <form class="space-y-3" @submit.prevent="submit">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Реализация</legend>
                            <select
                                v-model="form.code"
                                class="select select-bordered select-sm w-full"
                                :disabled="editingProvider !== null"
                                @change="fillFromImplementation"
                            >
                                <option value="" disabled>Выберите класс</option>
                                <option
                                    v-for="provider in providerOptions"
                                    :key="provider.code"
                                    :value="provider.code"
                                >
                                    {{ implementationClassBasename(provider.class) }}
                                </option>
                            </select>
                            <p v-if="form.errors.code" class="label text-error text-xs">{{ form.errors.code }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Название</legend>
                            <input v-model="form.name" type="text" class="input input-bordered input-sm w-full" />
                            <p v-if="form.errors.name" class="label text-error text-xs">{{ form.errors.name }}</p>
                        </fieldset>

                        <fieldset v-if="! isInternalCascade" class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Пользователь Provider Liquidity</legend>
                            <select v-model="form.user_id" class="select select-bordered select-sm w-full">
                                <option :value="null">Не привязан</option>
                                <option
                                    v-for="user in liquidityUsers"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.email }}
                                </option>
                            </select>
                            <p v-if="form.errors.user_id" class="label text-error text-xs">{{ form.errors.user_id }}</p>
                        </fieldset>

                        <fieldset v-if="! isInternalCascade" class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Минимальная прибыль, %</legend>
                            <input
                                v-model="form.min_profit_percent"
                                type="number"
                                step="0.0001"
                                min="0"
                                max="100"
                                class="input input-bordered input-sm w-full"
                            />
                            <p v-if="form.errors.min_profit_percent" class="label text-error text-xs">{{ form.errors.min_profit_percent }}</p>
                        </fieldset>

                        <fieldset v-if="! isInternalCascade" class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Base URL</legend>
                            <input v-model="form.base_url" type="url" class="input input-bordered input-sm w-full" placeholder="https://example.com" />
                            <p v-if="form.errors.base_url" class="label text-error text-xs">{{ form.errors.base_url }}</p>
                        </fieldset>

                        <fieldset v-if="! isInternalCascade" class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Token</legend>
                            <input v-model="form.access_token" type="text" class="input input-bordered input-sm w-full" />
                            <p v-if="form.errors.access_token" class="label text-error text-xs">{{ form.errors.access_token }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1 sm:col-span-2">
                            <legend class="fieldset-legend text-xs">Поддерживаемые валюты</legend>
                            <div class="rounded-box border border-base-300 p-3">
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                    <label
                                        v-for="currency in currencyOptions"
                                        :key="currency.code"
                                        class="label cursor-pointer justify-start gap-2 rounded-box border border-base-200 px-2 py-1.5"
                                        :title="currency.name"
                                    >
                                        <input
                                            v-model="form.supported_currency_codes"
                                            type="checkbox"
                                            class="checkbox checkbox-primary checkbox-xs"
                                            :value="currency.code"
                                        >
                                        <span class="text-sm">{{ currency.code }}</span>
                                    </label>
                                </div>
                            </div>
                            <p v-if="form.errors.supported_currency_codes" class="label text-error text-xs">{{ form.errors.supported_currency_codes }}</p>
                            <p v-if="form.errors['supported_currency_codes.0']" class="label text-error text-xs">{{ form.errors['supported_currency_codes.0'] }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Timeout</legend>
                            <input v-model="form.timeout" type="number" min="1" max="10" class="input input-bordered input-sm w-full" />
                            <p v-if="form.errors.timeout" class="label text-error text-xs">{{ form.errors.timeout }}</p>
                        </fieldset>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="label cursor-pointer justify-start gap-2 py-0">
                            <input v-model="form.is_active" type="checkbox" class="toggle toggle-primary" />
                            <span class="text-sm">Активен</span>
                        </label>

                        <label v-if="! isInternalCascade" class="label cursor-pointer justify-start gap-2 py-0">
                            <input v-model="form.verify_ssl" type="checkbox" class="toggle toggle-primary" />
                            <span class="text-sm">SSL</span>
                        </label>
                    </div>

                    <fieldset v-if="! isInternalCascade" class="fieldset gap-1">
                        <legend class="fieldset-legend text-xs">Callback endpoint</legend>
                        <div class="rounded-box border border-base-300 p-3 bg-base-200/40 space-y-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span
                                    class="badge"
                                    :class="selectedProviderSupportsCallbackEndpoint ? 'badge-success' : 'badge-ghost'"
                                >
                                    {{ selectedProviderSupportsCallbackEndpoint ? 'Поддерживается в исходящем запросе' : 'В исходящем запросе не используется' }}
                                </span>
                            </div>
                            <div class="join w-full">
                                <input
                                    :value="selectedProviderCallbackEndpointUrl"
                                    type="text"
                                    class="input input-bordered input-sm join-item w-full"
                                    readonly
                                >
                                <button
                                    type="button"
                                    class="btn btn-sm join-item"
                                    :disabled="! selectedProviderCallbackEndpointUrl"
                                    @click="copyCallbackEndpoint"
                                >
                                    Копировать
                                </button>
                            </div>
                            <p class="text-xs opacity-70">
                                Эту ссылку передайте внешнему сервису для webhook callback.
                            </p>
                        </div>
                    </fieldset>

                    <div class="modal-action mt-1">
                        <button type="button" class="btn btn-sm btn-ghost" @click="closeModal">Отмена</button>
                        <button type="submit" class="btn btn-sm btn-primary" :disabled="form.processing || ! form.code">
                            {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="button" @click="closeModal">close</button>
            </form>
        </dialog>
    </div>
</template>
