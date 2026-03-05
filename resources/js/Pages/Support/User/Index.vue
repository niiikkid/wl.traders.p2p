<script setup>
import {Head, usePage, useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import {ref} from "vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import DateTime from "@/Components/DateTime.vue";

const users = ref(usePage().props.users);

const form = useForm({});
const expandedCards = ref({});

const toggleExpand = (id) => {
    expandedCards.value[id] = !expandedCards.value[id];
};

const toggleTraffic = (user) => {
    form.patch(route('support.users.toggle-traffic', user.id), {
        preserveScroll: true,
        onSuccess: (result) => {
            users.value = result.props.users;
        },
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Пользователи" />

        <MainTableSection
            title="Пользователи"
            :data="users"
        >
            <template v-slot:header>
                <FiltersPanel name="users">
                    <InputFilter
                        name="user"
                        placeholder="Поиск (почта или имя)"
                        class="w-64"
                    />
                    <FilterCheckbox
                        name="online"
                        title="Онлайн"
                    />
                    <FilterCheckbox
                        name="traffic_disabled"
                        title="Трафик выключен"
                    />
                </FiltersPanel>
            </template>
            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                <tr>
                                    <th scope="col">
                                        ID
                                    </th>
                                    <th scope="col">
                                        Пользователь
                                    </th>
                                    <th scope="col">
                                        Баланс
                                    </th>
                                    <th scope="col">
                                        Роль
                                    </th>
                                    <th scope="col">
                                        Пинг
                                    </th>
                                    <th scope="col">
                                        Создан
                                    </th>
                                    <th scope="col">
                                        Онлайн
                                    </th>
                                    <th scope="col">
                                        Трафик
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="user in users.data" class="bg-base-100 border-b last:border-none">
                                    <th scope="row" class=" font-medium whitespace-nowrap">
                                        {{ user.id }}
                                    </th>
                                    <td class=" whitespace-nowrap">
                                        <div class="inline-flex items-center gap-3">
                                            <div class="avatar">
                                                <div class="w-10 rounded-full">
                                                    <img :src="'https://api.dicebear.com/9.x/'+user.avatar_style+'/svg?seed='+user.avatar_uuid" alt="user photo">
                                                </div>
                                            </div>
                                            <div>
                                                <div class="whitespace-nowrap">
                                                    {{ user.email }}
                                                </div>
                                                <div class="whitespace-nowrap text-xs text-base-content/70">
                                                    {{ user.name }}
                                                </div>
                                            </div>
                                            <span v-if="user.banned_at" class="badge badge-error badge-sm" title="Пользователь заблокирован">Ban</span>
                                            <span v-if="user.stop_traffic" class="badge badge-error badge-sm" title="Трафик остановлен">Stop</span>
                                        </div>
                                    </td>
                                    <td class=" whitespace-nowrap">
                                        {{ user.balance }} $
                                    </td>
                                    <td class=" whitespace-nowrap">
                                        {{ user.role.name }}
                                    </td>
                                    <td class=" whitespace-nowrap">
                                        <DateTime v-if="user.apk_latest_ping_at" :data="user.apk_latest_ping_at" :plural="true"/>
                                    </td>
                                    <td class=" whitespace-nowrap">
                                        {{ user.created_at }}
                                    </td>
                                    <td class=" whitespace-nowrap">
                                        <span v-if="user.is_online" class="badge badge-success badge-sm">Онлайн</span>
                                        <span v-else class="badge badge-error badge-sm">Офлайн</span>
                                    </td>
                                    <td class=" whitespace-nowrap">
                                        <label class="inline-flex items-center cursor-pointer gap-2">
                                            <input
                                                type="checkbox"
                                                class="toggle toggle-success toggle-sm"
                                                :checked="!user.stop_traffic"
                                                @change="toggleTraffic(user)"
                                                :disabled="form.processing"
                                            >
                                            <span class="text-xs">
                                                {{ user.stop_traffic ? 'Выкл.' : 'Вкл.' }}
                                            </span>
                                        </label>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="user in users.data"
                                :key="user.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Шапка: ID и дата создания -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                        <div class="inline-flex items-center">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="font-medium text-base-content ml-1">{{ user.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="user.created_at"/>
                                        </div>
                                    </div>

                                    <!-- Основная информация: пользователь -->
                                    <div class="flex items-center justify-between gap-3 mb-1">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <div class="avatar">
                                                    <div class="w-12 rounded-full">
                                                        <img :src="'https://api.dicebear.com/9.x/'+user.avatar_style+'/svg?seed='+user.avatar_uuid" alt="user photo">
                                                    </div>
                                                </div>
                                                <div class="whitespace-nowrap truncate font-medium">
                                                    {{ user.email }}
                                                </div>
                                            </div>
                                            <div class="flex flex-col gap-1">
                                                <span v-if="user.banned_at" class="badge badge-error badge-sm" title="Пользователь заблокирован">Ban</span>
                                                <span v-if="user.stop_traffic" class="badge badge-error badge-sm" title="Трафик остановлен">Stop</span>
                                            </div>
                                        </div>
                                        <div class="items-center">
                                            <span v-if="user.is_online" class="badge badge-success badge-sm">Онлайн</span>
                                            <span v-else class="badge badge-error badge-sm">Офлайн</span>
                                        </div>
                                    </div>

                                    <!-- Информация: баланс, роль, статус -->
                                    <div class="flex flex-col gap-2">
                                        <div class="flex items-center justify-between">
                                            <div class="text-base-content/70 text-sm">Баланс</div>
                                            <div class="text-base-content font-medium">{{ user.balance }} $</div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-base-content/70 text-sm">Роль</div>
                                            <div class="text-base-content">{{ user.role.name }}</div>
                                        </div>
                                        <div v-if="user.apk_latest_ping_at" class="flex items-center justify-between">
                                            <div class="text-base-content/70 text-sm">Пинг</div>
                                            <div class="text-base-content">
                                                <DateTime :data="user.apk_latest_ping_at" :plural="true"/>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between border-t border-base-content/10 pt-2 mt-2">
                                            <div class="text-base-content/70 text-sm">Трафик</div>
                                            <div class="flex items-center gap-2">
                                                <label class="inline-flex items-center cursor-pointer gap-2">
                                                    <input
                                                        type="checkbox"
                                                        class="toggle toggle-success toggle-sm"
                                                        :checked="!user.stop_traffic"
                                                        @change="toggleTraffic(user)"
                                                        :disabled="form.processing"
                                                    >
                                                    <span class="text-xs">
                                                        {{ user.stop_traffic ? 'Выкл.' : 'Вкл.' }}
                                                    </span>
                                                </label>
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
