<script setup>
import {Head, router, usePage, useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import AddMobileIcon from "@/Components/AddMobileIcon.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import {ref, onUnmounted, computed} from "vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import DateTime from "@/Components/DateTime.vue";
import UserNotesModal from "@/Modals/User/UserNotesModal.vue";
import UserCreateModal from "@/Modals/User/UserCreateModal.vue";
import UserEditModal from "@/Modals/User/UserEditModal.vue";
import UserTempVipHistoryModal from "@/Modals/User/UserTempVipHistoryModal.vue";
import {useModalStore} from "@/store/modal.js";
import DropdownFilter from "@/Components/Filters/Pertials/DropdownFilter.vue";
import TableActionsDropdown from "@/Components/Table/TableActionsDropdown.vue";
import TableAction from "@/Components/Table/TableAction.vue";
import CountdownTimer from "@/Components/CountdownTimer.vue";

const page = usePage();
const users = computed(() => page.props.users);
const modalStore = useModalStore();

const isCooldown = ref(false);
let cooldownTimer = null;
onUnmounted(() => {
    if (cooldownTimer) {
        clearTimeout(cooldownTimer);
        cooldownTimer = null;
    }
});

const onlineForm = useForm({
    is_online: 0
});

const toggleOnline = (order) => {

    onlineForm
        .transform((data) => {
            data.is_online = order.is_online;

            order.is_online = !order.is_online
            data.is_online = order.is_online;

            return data;
        })
        .patch(route('admin.users.toggle-online', order.id), {
            preserveScroll: true,
            onSuccess: () => {},
            onFinish: () => {
                if (cooldownTimer) {
                    clearTimeout(cooldownTimer);
                }
                isCooldown.value = true;
                cooldownTimer = setTimeout(() => {
                    isCooldown.value = false;
                    cooldownTimer = null;
                }, 300);
            },
        });
};

const impersonate = (user) => {
    useForm().post(route('admin.impersonate.start', { user: user.id }));
};

const openUserNotesModal = (user) => {
    modalStore.openUserNotesModal({user});
};

const openUserCreateModal = () => {
    modalStore.openUserCreateModal();
};

const openUserEditModal = (user) => {
    modalStore.openUserEditModal({ user });
};

const openUserTempVipHistoryModal = (user) => {
    modalStore.openUserTempVipHistoryModal({ user });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Пользователи" />

        <UserNotesModal />
        <UserCreateModal />
        <UserEditModal />
        <UserTempVipHistoryModal />

        <MainTableSection
            title="Пользователи"
            :data="users"
        >
            <template v-slot:button>
                <button
                    @click="openUserCreateModal"
                    type="button"
                    class="hidden md:block btn btn-sm btn-primary"
                >
                    Создать пользователя
                </button>
                <AddMobileIcon
                    @click="openUserCreateModal"
                />
            </template>
            <template v-slot:table-filters>
                <FiltersPanel name="users">
                    <InputFilter
                        name="user"
                        placeholder="Поиск (почта или имя)"
                        class="w-64"
                    />
                    <DropdownFilter
                        name="roles"
                        title="Роли"
                    />
                    <FilterCheckbox
                        name="online"
                        title="Работает"
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
                                    <th scope="col" class="px-6 py-3">
                                        ID
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Пользователь
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Баланс
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Заходил
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Создан
                                    </th>
<!--                                    <th scope="col" class="px-6 py-3">
                                        Временный VIP
                                    </th>-->
                                    <th scope="col" class="px-6 py-3">
                                        Работает
                                    </th>
                                    <th scope="col" class="px-6 py-3 flex justify-center">
                                        <span class="sr-only">Действия</span>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="user in users.data" class="hover">
                                    <th scope="row" class="px-6 py-3 font-medium whitespace-nowrap">
                                        {{ user.id }}
                                    </th>
                                    <td class="px-6 py-3 text-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <img :src="'https://api.dicebear.com/9.x/'+user.avatar_style+'/svg?seed='+user.avatar_uuid" class="w-10 h-10 rounded-full" alt="user photo">
                                            <div>
                                                <div class="text-nowrap">
                                                    {{ user.email }}
                                                </div>
                                                <div class="text-nowrap text-xs">
                                                    {{ user.role.name }}
                                                </div>
                                            </div>
                                            <span
                                                v-if="user.banned_at"
                                                title="Пользователь заблокирован"
                                            >
                                                <svg class="w-4 h-4 text-danger" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm7.707-3.707a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586 9.707 8.293Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                            <span
                                                v-if="user.stop_traffic"
                                                title="Трафик остановлен"
                                            >
                                                <svg class="w-4 h-4 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm3-1a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                            <span
                                                v-else-if="user.traffic_enabled_at"
                                                :title="'Трафик включен: ' + user.traffic_enabled_at"
                                            >
                                                <svg class="w-4 h-4 text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2s10 4.477 10 10Zm-11.99 4a1 1 0 0 1-.705-.292l-3.99-3.96a1 1 0 0 1 1.41-1.419l3.285 3.26 6.289-6.254a1 1 0 0 1 1.41 1.418l-6.99 6.955a1 1 0 0 1-.709.292Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                            <span
                                                v-if="user.is_vip"
                                                title="VIP пользователь"
                                            >
                                                <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M10.788 3.103c.495-1.004 1.926-1.004 2.421 0l2.358 4.777 5.273.766c1.107.16 1.55 1.522.748 2.303l-3.816 3.72.9 5.25c.19 1.104-.968 1.945-1.959 1.424l-4.716-2.48-4.715 2.48c-.99.52-2.148-.32-1.96-1.424l.9-5.25-3.815-3.72c-.8-.78-.36-2.142.748-2.303l5.274-.766 2.358-4.777Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-nowrap">
                                        {{ user.balance }} $
                                    </td>
                                    <td class="px-6 py-3 text-nowrap">
                                        <DateTime v-if="user.online_at" :data="user.online_at" :plural="true"/>
                                    </td>
                                    <td class="px-6 py-3 text-nowrap">
                                        <DateTime :data="user.created_at" :plural="true"/>
                                    </td>
<!--                                    <td class="px-6 py-3 text-nowrap">
                                        <div v-if="user.temp_vip_progress?.active" class="flex items-center gap-2">
                                            <CountdownTimer :end="user.temp_vip_progress?.active_until" />
                                            <span class="badge badge-success badge-outline">Активен</span>
                                        </div>
                                        <div v-else class="space-y-1">
                                            <div class="text-sm font-semibold">
                                                {{ user.temp_vip_progress?.count ?? 0 }} / {{ user.temp_vip_progress?.required ?? 0 }}
                                            </div>
                                            <div class="text-xs opacity-70">
                                                Осталось: {{ user.temp_vip_progress?.remaining ?? 0 }}
                                            </div>
                                        </div>
                                    </td>-->
                                    <td class="px-6 py-3 text-nowrap">
                                        <div class="space-y-1">
                                            <div class="flex items-center">
                                                <input type="checkbox" :checked="user.is_online" class="toggle toggle-success" @change="toggleOnline(user)" :disabled="onlineForm.processing || isCooldown">
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-nowrap text-right">
                                        <TableActionsDropdown>
                                            <TableAction v-if="user.can_be_impersonated" @click="impersonate(user)">
                                                Войти как пользователь
                                            </TableAction>
                                            <TableAction @click="router.visit(route('admin.users.wallet.index', user.id))">
                                                Кошелек
                                            </TableAction>
                                            <TableAction @click="openUserNotesModal(user)">
                                                Заметки
                                            </TableAction>
                                            <TableAction @click="openUserEditModal(user)">
                                                Редактировать
                                            </TableAction>
                                            <TableAction @click="openUserTempVipHistoryModal(user)">
                                                История VIP
                                            </TableAction>
                                        </TableActionsDropdown>
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
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-0 pb-2">
                                        <div class="inline-flex gap-3">
                                            <div class="inline-flex items-center">
                                                <span class="text-base-content/70">ID:</span> <span class="font-medium ml-4">{{ user.id }}</span>
                                            </div>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime :data="user.created_at" :plural="true"/>
                                        </div>
                                    </div>

                                    <!-- Для экранов sm и больше -->
                                    <div class="hidden sm:block">
                                        <div class="flex items-center justify-between gap-2 py-2">
                                            <div class="inline-flex items-center justify-between gap-2 flex-1 min-w-0">
                                                <div class="inline-flex items-center gap-2">
                                                    <img :src="'https://api.dicebear.com/9.x/'+user.avatar_style+'/svg?seed='+user.avatar_uuid" class="w-10 h-10 rounded-full flex-shrink-0" alt="user photo">
                                                    <div>
                                                        <div class="text-nowrap truncate">
                                                            {{ user.email }}
                                                        </div>
                                                        <div class="text-nowrap text-xs text-base-content/70">
                                                            {{ user.role.name }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="inline-flex items-center">
                                                    <span class="tex-xs text-base-content/70">Заходил:</span>
                                                    <span class="text-base-content ml-1">
                                                        <DateTime v-if="user.online_at" :data="user.online_at" :plural="true"/>
                                                        <span v-else>-</span>
                                                    </span>
                                                </div>
                                                <div class="inline-flex items-center gap-1 flex-shrink-0 mr-1">
                                                    <span
                                                        v-if="user.banned_at"
                                                        title="Пользователь заблокирован"
                                                    >
                                                        <svg class="w-4 h-4 text-danger" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                            <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm7.707-3.707a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586 9.707 8.293Z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                    <span
                                                        v-if="user.stop_traffic"
                                                        title="Трафик остановлен"
                                                    >
                                                        <svg class="w-4 h-4 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                            <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm3-1a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                    <span
                                                        v-else-if="user.traffic_enabled_at"
                                                        :title="'Трафик включен: ' + user.traffic_enabled_at"
                                                    >
                                                        <svg class="w-4 h-4 text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                            <path fill-rule="evenodd" d="M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2s10 4.477 10 10Zm-11.99 4a1 1 0 0 1-.705-.292l-3.99-3.96a1 1 0 0 1 1.41-1.419l3.285 3.26 6.289-6.254a1 1 0 0 1 1.41 1.418l-6.99 6.955a1 1 0 0 1-.709.292Z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                    <span
                                                        v-if="user.is_vip"
                                                        title="VIP пользователь"
                                                    >
                                                        <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                            <path fill-rule="evenodd" d="M10.788 3.103c.495-1.004 1.926-1.004 2.421 0l2.358 4.777 5.273.766c1.107.16 1.55 1.522.748 2.303l-3.816 3.72.9 5.25c.19 1.104-.968 1.945-1.959 1.424l-4.716-2.48-4.715 2.48c-.99.52-2.148-.32-1.96-1.424l.9-5.25-3.815-3.72c-.8-.78-.36-2.142.748-2.303l5.274-.766 2.358-4.777Z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="text-xs text-base-content/70 grid grid-cols-2 gap-x-4 gap-y-1 flex-1">
                                                <div class="inline-flex items-center">
                                                    <span>Баланс:</span>
                                                    <span class="text-base-content ml-1">{{ user.balance }} $</span>
                                                </div>
                                                <div class="inline-flex items-center">
                                                    <span>Временный VIP:</span>
                                                    <span class="text-base-content ml-1" v-if="user.temp_vip_progress?.active">
                                                        <CountdownTimer :end="user.temp_vip_progress?.active_until" :muted="true" />
                                                    </span>
                                                    <span class="text-base-content ml-1" v-else>
                                                        {{ user.temp_vip_progress?.count ?? 0 }} / {{ user.temp_vip_progress?.required ?? 0 }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs text-base-content/70">Работает: </span>
                                                    <input type="checkbox" :checked="user.is_online" class="toggle toggle-success toggle-sm" @change="toggleOnline(user, 'order')" :disabled="onlineForm.processing || isCooldown">
                                                </div>
                                                <TableActionsDropdown>
                                                    <TableAction v-if="user.can_be_impersonated" @click="impersonate(user)">
                                                        Войти как пользователь
                                                    </TableAction>
                                                    <TableAction @click="router.visit(route('admin.users.wallet.index', user.id))">
                                                        Кошелек
                                                    </TableAction>
                                                    <TableAction @click="openUserNotesModal(user)">
                                                        Заметки
                                                    </TableAction>
                                                    <TableAction @click="openUserEditModal(user)">
                                                        Редактировать
                                                    </TableAction>
                                                    <TableAction @click="openUserTempVipHistoryModal(user)">
                                                        История VIP
                                                    </TableAction>
                                                </TableActionsDropdown>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Для экранов меньше sm -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center gap-2 mb-2">
                                            <img :src="'https://api.dicebear.com/9.x/'+user.avatar_style+'/svg?seed='+user.avatar_uuid" class="w-10 h-10 rounded-full flex-shrink-0" alt="user photo">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-nowrap truncate text-sm">
                                                    {{ user.email }}
                                                </div>
                                                <div class="text-nowrap text-xs text-base-content/70">
                                                    {{ user.role.name }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1 flex-shrink-0 mr-1">
                                                <span
                                                    v-if="user.banned_at"
                                                    title="Пользователь заблокирован"
                                                >
                                                    <svg class="w-4 h-4 text-danger" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                        <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm7.707-3.707a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586 9.707 8.293Z" clip-rule="evenodd"/>
                                                    </svg>
                                                </span>
                                                <span
                                                    v-if="user.stop_traffic"
                                                    title="Трафик остановлен"
                                                >
                                                    <svg class="w-4 h-4 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                        <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm3-1a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Z" clip-rule="evenodd"/>
                                                    </svg>
                                                </span>
                                                <span
                                                    v-else-if="user.traffic_enabled_at"
                                                    :title="'Трафик включен: ' + user.traffic_enabled_at"
                                                >
                                                    <svg class="w-4 h-4 text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                        <path fill-rule="evenodd" d="M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2s10 4.477 10 10Zm-11.99 4a1 1 0 0 1-.705-.292l-3.99-3.96a1 1 0 0 1 1.41-1.419l3.285 3.26 6.289-6.254a1 1 0 0 1 1.41 1.418l-6.99 6.955a1 1 0 0 1-.709.292Z" clip-rule="evenodd"/>
                                                    </svg>
                                                </span>
                                                <span
                                                    v-if="user.is_vip"
                                                    title="VIP пользователь"
                                                >
                                                    <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                        <path fill-rule="evenodd" d="M10.788 3.103c.495-1.004 1.926-1.004 2.421 0l2.358 4.777 5.273.766c1.107.16 1.55 1.522.748 2.303l-3.816 3.72.9 5.25c.19 1.104-.968 1.945-1.959 1.424l-4.716-2.48-4.715 2.48c-.99.52-2.148-.32-1.96-1.424l.9-5.25-3.815-3.72c-.8-.78-.36-2.142.748-2.303l5.274-.766 2.358-4.777Z" clip-rule="evenodd"/>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="text-xs text-base-content/70 grid gap-1 mb-2">
                                            <div>
                                                <span>Баланс:</span>
                                                <span class="text-base-content ml-1">{{ user.balance }} $</span>
                                            </div>
                                            <div>
                                                <span>Временный VIP:</span>
                                                <span class="text-base-content ml-1" v-if="user.temp_vip_progress?.active">
                                                    <CountdownTimer :end="user.temp_vip_progress?.active_until" :muted="true" />
                                                </span>
                                                <span class="text-base-content ml-1" v-else>
                                                    {{ user.temp_vip_progress?.count ?? 0 }} / {{ user.temp_vip_progress?.required ?? 0 }}
                                                </span>
                                            </div>
                                            <div class="flex items-center">
                                                <span class="tex-xs text-base-content/70">Заходил:</span>
                                                <span class="text-base-content ml-1">
                                                    <DateTime v-if="user.online_at" :data="user.online_at" :plural="true"/>
                                                    <span v-else>-</span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="tex-xs text-base-content/70">Работает:</span>
                                                <input type="checkbox" :checked="user.is_online" class="toggle toggle-success toggle-sm" @change="toggleOnline(user)" :disabled="onlineForm.processing || isCooldown">
                                                <input type="checkbox" :checked="user.is_online" class="toggle toggle-success toggle-sm" @change="toggleOnline(user)" :disabled="onlineForm.processing || isCooldown">
                                            </div>
                                            <TableActionsDropdown>
                                                <TableAction v-if="user.can_be_impersonated" @click="impersonate(user)">
                                                    Войти как пользователь
                                                </TableAction>
                                                <TableAction @click="router.visit(route('admin.users.wallet.index', user.id))">
                                                    Кошелек
                                                </TableAction>
                                                <TableAction @click="openUserNotesModal(user)">
                                                    Заметки
                                                </TableAction>
                                                <TableAction @click="openUserEditModal(user)">
                                                    Редактировать
                                                </TableAction>
                                                <TableAction @click="openUserTempVipHistoryModal(user)">
                                                    История VIP
                                                </TableAction>
                                            </TableActionsDropdown>
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
