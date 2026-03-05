<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import DateTime from '@/Components/DateTime.vue';
import {ref, reactive} from "vue";
import TableActionsDropdown from '@/Components/Table/TableActionsDropdown.vue';
import TableAction from '@/Components/Table/TableAction.vue';

const devices = ref(usePage().props.devices.data);

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(route('trader.devices.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

router.on('success', (event) => {
    devices.value = usePage().props.devices.data;
})

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        alert('Токен скопирован в буфер обмена');
    });
};

defineOptions({ layout: AuthenticatedLayout })

const expanded = reactive({});
const pings = reactive({});
const loading = reactive({});

const toggleDeviceRow = async (deviceId) => {
    expanded[deviceId] = !expanded[deviceId];
    if (expanded[deviceId] && !pings[deviceId] && !loading[deviceId]) {
        loading[deviceId] = true;
        try {
            const { data } = await window.axios.get(route('trader.devices.pings', { device: deviceId }));
            // Преобразуем ответ к массиву объектов { ok: boolean }
            const items = Array.isArray(data.data?.items) ? data.data.items : [];
            pings[deviceId] = items.map(it => ({ ok: !!it.ok }));
        } finally {
            loading[deviceId] = false;
        }
    }
};

const cellClass = (ok) => ok ? 'bg-success' : 'bg-error';
</script>

<template>
    <div>
        <Head title="Устройства" />

        <MainTableSection title="Устройства" :data="devices" :paginate="false">
            <template v-slot:header>
                <div class="card bg-base-100 shadow-md mb-6">
                    <div class="card-body p-4 sm:p-6">
                        <h3 class="card-title mb-1.5">Скачайте и установите APK</h3>
                        <p class="text-base-content/70">
                            Для получения СМС нужно приложение, которое доступно только для Android —
                            <a :href="route('app.download')" class="link link-primary">Скачать</a>
                        </p>
                    </div>
                </div>
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body p-4 sm:p-6">
                    <section>
                        <header>
                            <h2 class="card-title">
                                Создать новый токен для устройства
                            </h2>

                            <p class="mt-1 text-sm text-base-content/70">
                                Создайте новый токен для подключения устройства. Один токен может быть использован только для одного устройства.
                            </p>
                        </header>

                        <form @submit.prevent="submit" class="mt-6 space-y-6">
                            <div class="form-control">
                                <InputLabel for="name" value="Название устройства" class="label">
                                    <span class="label-text">Название устройства</span>
                                </InputLabel>

                                <TextInput
                                    id="name"
                                    type="text"
                                    class="input input-bordered w-full"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    placeholder="Например: Samsung Galaxy S21"
                                />

                                <InputError class="mt-2 text-error" :message="form.errors.name" />
                            </div>

                            <div class="sm:flex items-center gap-4 space-y-2 sm:space-y-0">
                                <PrimaryButton class="btn btn-primary" :disabled="form.processing">
                                    Создать токен
                                </PrimaryButton>

                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <div v-if="form.recentlySuccessful" class="alert alert-success py-2 px-3 text-sm">
                                        <span>Токен создан.</span>
                                    </div>
                                </Transition>
                            </div>
                        </form>
                    </section>
                    </div>
                </div>
            </template>

            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block shadow-md rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th scope="col" class="px-6 py-3">
                                        Название
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Токен
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Статус
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Последний пинг
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right">

                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <template v-for="device in devices" :key="device.id">
                                <tr>
                                    <th scope="row" class="px-6 py-3 font-medium whitespace-nowrap text-base-content">
                                        {{ device.name }}
                                    </th>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center">
                                            <span class="truncate max-w-36 text-base-content">{{ device.token }}</span>
                                            <button
                                                @click="copyToClipboard(device.token)"
                                                class="ml-2 btn btn-ghost btn-xs"
                                                title="Копировать токен"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span :class="['badge', device.android_id ? 'badge-success' : 'badge-warning']" class="badge-sm text-nowrap">
                                            {{ device.android_id ? 'Подключено' : 'Не подключено' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <DateTime v-if="device.latest_ping_at" :data="device.latest_ping_at" :plural="true" />
                                        <span v-else>нет данных</span>
                                    </td>
                                    <td class="px-6 py-3 text-right relative">
                                        <TableActionsDropdown>
                                            <TableAction @click="toggleDeviceRow(device.id)">
                                                Показать историю пингов
                                            </TableAction>
                                        </TableActionsDropdown>
                                    </td>
                                </tr>
                                <tr v-if="expanded[device.id]" :key="`expand-${device.id}`" class="bg-base-200">
                                    <td :colspan="7" class="px-6 py-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="text-sm">Пинги за последний час, шаг 5с</div>
                                            <div class="text-sm text-base-content/70 text-nowrap">Последний пинг: <span class="font-medium">{{ device.latest_ping_at ?? '—' }}</span></div>
                                        </div>
                                        <div v-if="loading[device.id]" class="text-sm opacity-70">Загрузка…</div>
                                        <div v-else class="flex gap-[2px] flex-wrap">
                                            <template v-for="(cell, idx) in (pings[device.id] || Array.from({length: 720}, () => ({ ok: false })))" :key="cell.bucket ?? idx">
                                                <div :class="['w-3 h-3 rounded-[2px]', cellClass(cell.ok)]" :title="cell.ok ? 'был пинг' : 'нет пинга'"></div>
                                            </template>
                                        </div>
                                        <div class="flex gap-6 mt-2">
                                            <div class="text-sm text-base-content/70 text-nowrap flex">Создан:
                                                <DateTime class="justify-start font-medium ml-2" :data="device.created_at"/>
                                            </div>
                                            <div class="text-sm text-base-content/70 text-nowrap flex">Подключен:
                                                <DateTime class="font-medium ml-2" v-if="device.connected_at" :data="device.connected_at" /><span v-else class="font-medium">нет данных</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="device in devices"
                                :key="device.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Шапка: Название и последний пинг -->
                                    <div class="flex justify-between items-center">
                                        <div class="inline-flex items-center gap-2 min-w-0">
                                            <span class="font-medium text-base-content truncate">{{ device.name }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <span :class="['badge', 'badge-sm', device.android_id ? 'badge-success' : 'badge-warning']" class="text-nowrap">
                                                {{ device.android_id ? 'Подключено' : 'Не подключено' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="border-b border-base-content/10">

                                    </div>

                                    <!-- Для >= sm -->
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="text-xs text-base-content/70">Токен</div>
                                            <div class="flex items-center gap-2">
                                                <span class="truncate max-w-40 text-base-content">{{ device.token }}</span>
                                                <button
                                                    @click="copyToClipboard(device.token)"
                                                    class="btn btn-ghost btn-xs"
                                                    title="Копировать токен"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <DateTime v-if="device.latest_ping_at" class="justify-start" :data="device.latest_ping_at" :plural="true"/>
                                            <span v-else class="opacity-70">нет данных</span>
                                        </div>
                                        <div>
                                            <button
                                                class="btn btn-primary btn-xs"
                                                @click.stop="toggleDeviceRow(device.id)"
                                                :aria-expanded="!!expanded[device.id]"
                                                :aria-label="!!expanded[device.id] ? 'Скрыть' : 'Показать детали'"
                                                :disabled="!!loading[device.id]"
                                            >
                                                <svg
                                                    :class="['w-4 h-4 transition-transform', {'rotate-180': !!expanded[device.id]}]"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Для xs -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between">
                                            <div class="min-w-0">
                                                <div class="text-xs text-base-content/70">Токен</div>
                                                <div class="flex items-center gap-2">
                                                    <span class="truncate max-w-40 text-base-content">{{ device.token }}</span>
                                                    <button
                                                        @click="copyToClipboard(device.token)"
                                                        class="btn btn-ghost btn-xs"
                                                        title="Копировать токен"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <button
                                                    class="btn btn-primary btn-xs"
                                                    @click.stop="toggleDeviceRow(device.id)"
                                                    :aria-expanded="!!expanded[device.id]"
                                                    :aria-label="!!expanded[device.id] ? 'Скрыть' : 'Показать детали'"
                                                    :disabled="!!loading[device.id]"
                                                >
                                                    <svg
                                                        :class="['w-4 h-4 transition-transform', {'rotate-180': !!expanded[device.id]}]"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Раскрываемая часть -->
                                    <div v-show="!!expanded[device.id]" class="mt-3 grid gap-2 bg-base-300/50 rounded-box p-2">
                                        <div class="sm:hidden">
                                            <DateTime v-if="device.latest_ping_at" class="justify-start" :data="device.latest_ping_at" :plural="true"/>
                                            <span v-else class="opacity-70">нет данных</span>
                                        </div>
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="text-sm">Пинги за последний час, шаг 5с</div>
                                            <div class="text-sm text-base-content/70 text-nowrap">
                                                Последний пинг:
                                                <span class="font-medium">{{ device.latest_ping_at ?? '—' }}</span>
                                            </div>
                                        </div>
                                        <div v-if="loading[device.id]" class="text-sm opacity-70">Загрузка…</div>
                                        <div v-else class="flex gap-[2px] flex-wrap">
                                            <template v-for="(cell, idx) in (pings[device.id] || Array.from({length: 720}, () => ({ ok: false })))" :key="cell.bucket ?? idx">
                                                <div :class="['w-3 h-3 rounded-[2px]', cellClass(cell.ok)]" :title="cell.ok ? 'был пинг' : 'нет пинга'"></div>
                                            </template>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                                            <div class="text-sm text-base-content/70 text-nowrap flex items-center">
                                                <span>Создан:</span>
                                                <DateTime class="justify-start font-medium ml-2" :data="device.created_at"/>
                                            </div>
                                            <div class="text-sm text-base-content/70 text-nowrap flex items-center">
                                                <span>Подключен:</span>
                                                <DateTime class="font-medium ml-2" v-if="device.connected_at" :data="device.connected_at" />
                                                <span v-else class="font-medium ml-2">нет данных</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
