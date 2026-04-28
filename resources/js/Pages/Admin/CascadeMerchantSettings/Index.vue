<script setup>
import {Head, useForm} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import CascadeSectionNav from '@/Components/Admin/CascadeSectionNav.vue';
import IsActiveStatus from '@/Components/IsActiveStatus.vue';

const props = defineProps({
    merchants: Array,
    cascadeProviders: Array,
});

const selectedMerchant = ref(null);
const useProviderWhitelist = ref(false);

const form = useForm({
    cascade_enabled: true,
    allow_internal_providers: true,
    allow_external_providers: true,
    allowed_provider_ids: [],
});

const merchantRows = computed(() => Array.isArray(props.merchants) ? props.merchants : []);
const providerRows = computed(() => Array.isArray(props.cascadeProviders) ? props.cascadeProviders : []);

const internalProviders = computed(() => providerRows.value.filter((provider) => provider.provider_type === 'internal'));
const externalProviders = computed(() => providerRows.value.filter((provider) => provider.provider_type === 'external'));

const providerName = (providerId) => {
    const provider = providerRows.value.find((item) => item.id === providerId);

    return provider?.name ?? provider?.code ?? `#${providerId}`;
};

const providersSummary = (merchant) => {
    const setting = merchant.cascade_setting;

    if (! setting?.cascade_enabled) {
        return 'Каскад выключен';
    }

    const parts = [];

    if (setting.allow_internal_providers) {
        parts.push('internal');
    }

    if (setting.allow_external_providers) {
        parts.push('external');
    }

    if (! parts.length) {
        return 'Нет разрешённых типов';
    }

    if (! setting.allowed_provider_ids?.length) {
        return `${parts.join(' + ')}, все провайдеры`;
    }

    return `${parts.join(' + ')}, ${setting.allowed_provider_ids.length} пров.`;
};

const providerTypeLabel = (type) => ({
    internal: 'Внутренний',
    external: 'Внешний',
}[type] ?? type ?? 'Не указан');

const providerAllowed = (providerId) => form.allowed_provider_ids.includes(providerId);

const toggleProvider = (providerId, checked) => {
    if (checked && ! form.allowed_provider_ids.includes(providerId)) {
        form.allowed_provider_ids = [...form.allowed_provider_ids, providerId];
        return;
    }

    if (! checked) {
        form.allowed_provider_ids = form.allowed_provider_ids.filter((id) => id !== providerId);
    }
};

const selectAllProviders = () => {
    form.allowed_provider_ids = providerRows.value.map((provider) => provider.id);
};

const clearProviders = () => {
    form.allowed_provider_ids = [];
};

const openSettingsModal = (merchant) => {
    const setting = merchant.cascade_setting ?? {};

    selectedMerchant.value = merchant;
    useProviderWhitelist.value = Boolean(setting.allowed_provider_ids?.length);

    form.clearErrors();
    form.defaults({
        cascade_enabled: setting.cascade_enabled ?? true,
        allow_internal_providers: setting.allow_internal_providers ?? true,
        allow_external_providers: setting.allow_external_providers ?? true,
        allowed_provider_ids: [...(setting.allowed_provider_ids ?? [])],
    });
    form.reset();
};

const closeSettingsModal = () => {
    selectedMerchant.value = null;
    form.clearErrors();
};

const submit = () => {
    if (! selectedMerchant.value) {
        return;
    }

    form.allowed_provider_ids = useProviderWhitelist.value
        ? form.allowed_provider_ids
        : [];

    form.patch(route('admin.cascade-merchant-settings.update', selectedMerchant.value.id), {
        preserveScroll: true,
        onSuccess: closeSettingsModal,
    });
};

defineOptions({ layout: AuthenticatedLayout });
</script>

<template>
    <div>
        <Head title="Мерчанты каскада" />

        <MainTableSection
            title="Мерчанты каскада"
            :data="merchantRows"
            :paginate="false"
            :display-pagination="false"
        >
            <template #button>
                <CascadeSectionNav active="merchants" />
            </template>

            <template #body>
                <div class="hidden xl:block overflow-x-auto card bg-base-100 shadow">
                    <table class="table table-sm">
                        <thead class="text-xs uppercase bg-base-300">
                            <tr>
                                <th>ID</th>
                                <th>Мерчант</th>
                                <th>Владелец</th>
                                <th>Статус</th>
                                <th>Каскад</th>
                                <th>Провайдеры</th>
                                <th><span class="sr-only">Действия</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="merchant in merchantRows"
                                :key="merchant.id"
                                class="bg-base-100 border-b last:border-none border-base-200"
                            >
                                <td class="font-medium">#{{ merchant.id }}</td>
                                <td>
                                    <div class="font-medium text-base-content">{{ merchant.name }}</div>
                                    <div class="text-xs opacity-70">{{ merchant.domain || merchant.uuid }}</div>
                                </td>
                                <td class="text-nowrap">{{ merchant.owner?.email ?? 'Пусто' }}</td>
                                <td><IsActiveStatus :is_active="merchant.active" /></td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            :class="[
                                                'badge badge-sm',
                                                merchant.cascade_setting.cascade_enabled ? 'badge-success' : 'badge-error',
                                            ]"
                                        >
                                            {{ merchant.cascade_setting.cascade_enabled ? 'Включен' : 'Выключен' }}
                                        </span>
                                        <span v-if="merchant.cascade_setting.is_default" class="badge badge-sm badge-ghost">
                                            По умолчанию
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-sm">{{ providersSummary(merchant) }}</div>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline"
                                        @click="openSettingsModal(merchant)"
                                    >
                                        Настройки
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:hidden">
                    <div
                        v-for="merchant in merchantRows"
                        :key="merchant.id"
                        class="card bg-base-100 shadow"
                    >
                        <div class="card-body gap-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-lg font-semibold">{{ merchant.name }}</div>
                                    <div class="text-xs opacity-70">#{{ merchant.id }} · {{ merchant.owner?.email ?? 'Пусто' }}</div>
                                </div>
                                <span
                                    :class="[
                                        'badge',
                                        merchant.cascade_setting.cascade_enabled ? 'badge-success' : 'badge-error',
                                    ]"
                                >
                                    {{ merchant.cascade_setting.cascade_enabled ? 'Включен' : 'Выключен' }}
                                </span>
                            </div>

                            <div class="text-sm opacity-80">{{ providersSummary(merchant) }}</div>

                            <div class="card-actions justify-end">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline"
                                    @click="openSettingsModal(merchant)"
                                >
                                    Настройки
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>

        <dialog v-if="selectedMerchant" open class="modal modal-open">
            <div class="modal-box max-w-4xl">
                <form method="dialog">
                    <button
                        type="button"
                        class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                        @click="closeSettingsModal"
                    >
                        ✕
                    </button>
                </form>

                <h3 class="text-lg font-bold">
                    Настройки каскада: {{ selectedMerchant?.name }}
                </h3>
                <p class="mt-1 text-sm opacity-70">
                    Если whitelist провайдеров выключен, для мерчанта доступны все глобально активные провайдеры выбранных типов.
                </p>

                <form class="mt-6 space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <label class="flex items-center justify-between gap-3 rounded-box border border-base-300 p-4">
                            <span>
                                <span class="block font-medium">Каскадирование</span>
                                <span class="block text-xs opacity-70">Включить трафик мерчанта в каскад</span>
                            </span>
                            <input v-model="form.cascade_enabled" type="checkbox" class="toggle toggle-primary" />
                        </label>

                        <label class="flex items-center justify-between gap-3 rounded-box border border-base-300 p-4">
                            <span>
                                <span class="block font-medium">Внутренний провайдер</span>
                                <span class="block text-xs opacity-70">Разрешить нашу ликвидность</span>
                            </span>
                            <input v-model="form.allow_internal_providers" type="checkbox" class="toggle toggle-primary" />
                        </label>

                        <label class="flex items-center justify-between gap-3 rounded-box border border-base-300 p-4">
                            <span>
                                <span class="block font-medium">Внешние провайдеры</span>
                                <span class="block text-xs opacity-70">Разрешить внешние интеграции</span>
                            </span>
                            <input v-model="form.allow_external_providers" type="checkbox" class="toggle toggle-primary" />
                        </label>
                    </div>

                    <div class="rounded-box border border-base-300">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 p-4">
                            <label class="flex items-center gap-3">
                                <input v-model="useProviderWhitelist" type="checkbox" class="toggle toggle-primary" />
                                <span>
                                    <span class="block font-medium">Ограничить список провайдеров</span>
                                    <span class="block text-xs opacity-70">Выберите конкретных провайдеров для сделок этого мерчанта</span>
                                </span>
                            </label>

                            <div v-if="useProviderWhitelist" class="flex gap-2">
                                <button type="button" class="btn btn-xs btn-outline" @click="selectAllProviders">
                                    Выбрать всех
                                </button>
                                <button type="button" class="btn btn-xs btn-ghost" @click="clearProviders">
                                    Очистить
                                </button>
                            </div>
                        </div>

                        <div v-if="useProviderWhitelist" class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-2">
                            <div>
                                <h4 class="mb-2 font-semibold">Внутренние</h4>
                                <div v-if="internalProviders.length" class="space-y-2">
                                    <label
                                        v-for="provider in internalProviders"
                                        :key="provider.id"
                                        class="flex items-center justify-between gap-3 rounded-box bg-base-200/60 p-3"
                                    >
                                        <span>
                                            <span class="block font-medium">{{ provider.name }}</span>
                                            <span class="block text-xs opacity-70">
                                                {{ provider.code }} · {{ providerTypeLabel(provider.provider_type) }}
                                            </span>
                                        </span>
                                        <input
                                            type="checkbox"
                                            class="checkbox checkbox-primary"
                                            :checked="providerAllowed(provider.id)"
                                            @change="toggleProvider(provider.id, $event.target.checked)"
                                        />
                                    </label>
                                </div>
                                <div v-else class="text-sm opacity-70">Нет внутренних провайдеров.</div>
                            </div>

                            <div>
                                <h4 class="mb-2 font-semibold">Внешние</h4>
                                <div v-if="externalProviders.length" class="space-y-2">
                                    <label
                                        v-for="provider in externalProviders"
                                        :key="provider.id"
                                        class="flex items-center justify-between gap-3 rounded-box bg-base-200/60 p-3"
                                    >
                                        <span>
                                            <span class="block font-medium">
                                                {{ provider.name }}
                                                <span v-if="! provider.is_active" class="badge badge-xs badge-warning ms-1">выкл.</span>
                                            </span>
                                            <span class="block text-xs opacity-70">
                                                {{ provider.code }} · {{ providerTypeLabel(provider.provider_type) }}
                                            </span>
                                        </span>
                                        <input
                                            type="checkbox"
                                            class="checkbox checkbox-primary"
                                            :checked="providerAllowed(provider.id)"
                                            @change="toggleProvider(provider.id, $event.target.checked)"
                                        />
                                    </label>
                                </div>
                                <div v-else class="text-sm opacity-70">Нет внешних провайдеров.</div>
                            </div>
                        </div>

                        <div v-else class="p-4 text-sm opacity-70">
                            Whitelist не используется. Мерчанту будут доступны все провайдеры, подходящие под тип, активность и валюту сделки.
                        </div>
                    </div>

                    <div v-if="form.errors.allowed_provider_ids" class="alert alert-error">
                        {{ form.errors.allowed_provider_ids }}
                    </div>

                    <div v-if="useProviderWhitelist && form.allowed_provider_ids.length" class="rounded-box bg-base-200 p-4 text-sm">
                        <div class="mb-2 font-medium">Выбрано:</div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="providerId in form.allowed_provider_ids"
                                :key="providerId"
                                class="badge badge-outline"
                            >
                                {{ providerName(providerId) }}
                            </span>
                        </div>
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost" @click="closeSettingsModal">
                            Отмена
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="button" @click="closeSettingsModal">Закрыть</button>
            </form>
        </dialog>
    </div>
</template>
