<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import IsActiveStatus from '@/Components/IsActiveStatus.vue';

const props = defineProps({
    cascadeProviders: Object,
    implementedProviders: Array,
    providerCallbackBaseUrl: String,
    providerTypes: Array,
    merchants: Array,
});

const isModalOpen = ref(false);
const editingProvider = ref(null);

const form = useForm({
    code: '',
    name: '',
    provider_type: 'external',
    is_active: true,
    weight: null,
    priority: null,
    base_url: '',
    access_token: '',
    merchant_id: '',
    target_merchant_id: null,
    currency_code: '',
    timeout: 10,
    verify_ssl: true,
    description: '',
});

const providerOptions = computed(() => props.implementedProviders);

const canCreateProvider = computed(() => providerOptions.value.length > 0);

const selectedImplementation = computed(() => {
    return props.implementedProviders.find((provider) => provider.code === form.code);
});

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
    form.defaults({
        code: providerOptions.value[0]?.code ?? '',
        name: providerOptions.value[0]?.name ?? '',
        provider_type: providerOptions.value[0]?.code === 'internal' ? 'internal' : 'external',
        is_active: true,
        weight: null,
        priority: null,
        base_url: '',
        access_token: '',
        merchant_id: '',
        target_merchant_id: null,
        currency_code: '',
        timeout: 10,
        verify_ssl: true,
        description: '',
    });
    form.reset();
    isModalOpen.value = true;
};

const openEditModal = (provider) => {
    editingProvider.value = provider;
    form.clearErrors();
    form.defaults({
        code: provider.code,
        name: provider.name,
        provider_type: provider.provider_type,
        is_active: provider.is_active,
        weight: provider.weight,
        priority: provider.priority,
        base_url: provider.base_url ?? '',
        access_token: provider.access_token ?? '',
        merchant_id: provider.merchant_id ?? '',
        target_merchant_id: provider.target_merchant_id,
        currency_code: provider.currency_code ?? '',
        timeout: provider.timeout,
        verify_ssl: provider.verify_ssl,
        description: provider.description ?? '',
    });
    form.reset();
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingProvider.value = null;
};

const submit = () => {
    if (form.provider_type === 'internal') {
        form.target_merchant_id = null;
    }

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
    form.provider_type = selectedImplementation.value.code === 'internal' ? 'internal' : 'external';
    if (form.provider_type === 'internal') {
        form.target_merchant_id = null;
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

const selectedTargetMerchantLabel = computed(() => {
    if (! form.target_merchant_id) {
        return '';
    }

    const selected = props.merchants.find((merchant) => merchant.id === Number(form.target_merchant_id));
    if (! selected) {
        return '';
    }

    return `${selected.name} (${selected.uuid})`;
});

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Провайдеры каскада" />

        <MainTableSection
            title="Провайдеры каскада"
            :data="cascadeProviders"
        >
            <template v-slot:button>
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    :disabled="! canCreateProvider"
                    @click="openCreateModal"
                >
                    Добавить провайдера
                </button>
            </template>

            <template v-slot:body>
                <div class="hidden xl:block overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th>ID</th>
                                <th>Провайдер</th>
                                <th>Тип</th>
                                <th>Распределение</th>
                                <th>API</th>
                                <th>Статус</th>
                                <th><span class="sr-only">Действия</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="provider in cascadeProviders.data"
                                :key="provider.id"
                                class="bg-base-100 border-b last:border-none border-base-200"
                            >
                                <th class="font-medium">{{ provider.id }}</th>
                                <td>
                                    <div class="font-medium text-nowrap">{{ provider.name }}</div>
                                    <div class="text-xs opacity-70 text-nowrap">{{ provider.code }}</div>
                                </td>
                                <td class="text-nowrap">{{ provider.provider_type_name }}</td>
                                <td>
                                    <div class="text-nowrap">Вес: {{ provider.weight ?? 'Пусто' }}</div>
                                    <div class="text-xs opacity-70 text-nowrap">Приоритет: {{ provider.priority ?? 'Пусто' }}</div>
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
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs"
                                        @click="openEditModal(provider)"
                                    >
                                        Редактировать
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="xl:hidden space-y-3">
                    <div
                        v-for="provider in cascadeProviders.data"
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
                                    <div class="text-base-content/60">Тип</div>
                                    <div class="font-medium">{{ provider.provider_type_name }}</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Приоритет</div>
                                    <div class="font-medium">{{ provider.priority ?? 'Пусто' }}</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Вес</div>
                                    <div class="font-medium">{{ provider.weight ?? 'Пусто' }}</div>
                                </div>
                                <div>
                                    <div class="text-base-content/60">Timeout</div>
                                    <div class="font-medium">{{ provider.timeout ?? 'Пусто' }}</div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <div class="truncate text-xs opacity-70">{{ provider.base_url || 'Base URL не задан' }}</div>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-outline btn-xs"
                                    @click="openEditModal(provider)"
                                >
                                    Изменить
                                </button>
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

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Тип</legend>
                            <select v-model="form.provider_type" class="select select-bordered select-sm w-full">
                                <option
                                    v-for="type in providerTypes"
                                    :key="type.code"
                                    :value="type.code"
                                >
                                    {{ type.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.provider_type" class="label text-error text-xs">{{ form.errors.provider_type }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Распределение</legend>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="form.weight" type="number" step="0.01" min="0" max="100" class="input input-bordered input-sm w-full" placeholder="Вес" />
                                <input v-model="form.priority" type="number" min="0" class="input input-bordered input-sm w-full" placeholder="Приоритет" />
                            </div>
                            <p v-if="form.errors.weight" class="label text-error text-xs">{{ form.errors.weight }}</p>
                            <p v-if="form.errors.priority" class="label text-error text-xs">{{ form.errors.priority }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Base URL</legend>
                            <input v-model="form.base_url" type="url" class="input input-bordered input-sm w-full" placeholder="https://example.com" />
                            <p v-if="form.errors.base_url" class="label text-error text-xs">{{ form.errors.base_url }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Token</legend>
                            <input v-model="form.access_token" type="text" class="input input-bordered input-sm w-full" />
                            <p v-if="form.errors.access_token" class="label text-error text-xs">{{ form.errors.access_token }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Merchant ID</legend>
                            <input v-model="form.merchant_id" type="text" class="input input-bordered input-sm w-full" />
                            <p v-if="form.errors.merchant_id" class="label text-error text-xs">{{ form.errors.merchant_id }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Мерчант (модель)</legend>
                            <select
                                v-model="form.target_merchant_id"
                                class="select select-bordered select-sm w-full"
                                :disabled="form.provider_type === 'internal'"
                            >
                                <option :value="null">Не выбран</option>
                                <option
                                    v-for="merchant in merchants"
                                    :key="merchant.id"
                                    :value="merchant.id"
                                >
                                    {{ merchant.name }} ({{ merchant.uuid }})
                                </option>
                            </select>
                            <p v-if="form.errors.target_merchant_id" class="label text-error text-xs">{{ form.errors.target_merchant_id }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Валюта</legend>
                            <input v-model="form.currency_code" type="text" maxlength="10" class="input input-bordered input-sm w-full" placeholder="RUB" />
                            <p v-if="form.errors.currency_code" class="label text-error text-xs">{{ form.errors.currency_code }}</p>
                        </fieldset>

                        <fieldset class="fieldset gap-1">
                            <legend class="fieldset-legend text-xs">Timeout</legend>
                            <input v-model="form.timeout" type="number" min="1" max="300" class="input input-bordered input-sm w-full" />
                            <p v-if="form.errors.timeout" class="label text-error text-xs">{{ form.errors.timeout }}</p>
                        </fieldset>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="label cursor-pointer justify-start gap-2 py-0">
                            <input v-model="form.is_active" type="checkbox" class="toggle toggle-primary" />
                            <span class="text-sm">Активен</span>
                        </label>

                        <label class="label cursor-pointer justify-start gap-2 py-0">
                            <input v-model="form.verify_ssl" type="checkbox" class="toggle toggle-primary" />
                            <span class="text-sm">SSL</span>
                        </label>
                    </div>

                    <fieldset class="fieldset gap-1">
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
                            <p v-if="selectedTargetMerchantLabel" class="text-xs opacity-70">
                                Назначено мерчанту: {{ selectedTargetMerchantLabel }}
                            </p>
                        </div>
                    </fieldset>

                    <fieldset class="fieldset gap-1">
                        <legend class="fieldset-legend text-xs">Описание</legend>
                        <textarea v-model="form.description" class="textarea textarea-bordered textarea-sm w-full min-h-16"></textarea>
                        <p v-if="form.errors.description" class="label text-error text-xs">{{ form.errors.description }}</p>
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
