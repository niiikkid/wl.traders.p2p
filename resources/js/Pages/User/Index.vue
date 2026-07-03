<script setup>
import {Head, router, usePage, useForm} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import PageToolbar from "@/Components/Table/PageToolbar.vue";
import PageToolbarAction from "@/Components/Table/PageToolbarAction.vue";
import InputFilter from "@/Components/Filters/Partials/InputFilter.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import {ref, onUnmounted, computed} from "vue";
import UsersNav from '@/Components/Admin/UsersNav.vue';
import FilterCheckbox from "@/Components/Filters/Partials/FilterCheckbox.vue";
import DateTime from "@/Components/DateTime.vue";
import UserCreateModal from "@/Modals/User/UserCreateModal.vue";
import UserEditModal from "@/Modals/User/UserEditModal.vue";
import UserOnlineActivityModal from "@/Modals/User/UserOnlineActivityModal.vue";
import UserSummaryPopover from "@/Components/User/UserSummaryPopover.vue";
import UserAvatar from '@/Components/User/UserAvatar.vue';
import {useModalStore} from "@/store/modal.js";
import DropdownFilter from "@/Components/Filters/Partials/DropdownFilter.vue";
import TableActionsDropdown from "@/Components/Table/TableActionsDropdown.vue";
import TableActionsHeadCell from "@/Components/Table/TableActionsHeadCell.vue";
import TableAction from "@/Components/Table/TableAction.vue";
import {useTableFiltersStore} from "@/store/tableFilters.js";
import ConfirmModal from "@/Components/Modals/ConfirmModal.vue";
import DataTable from "@/Components/Table/DataTable.vue";
import DataCardList from "@/Components/Table/DataCardList.vue";
import DataCard from "@/Components/Table/DataCard.vue";

const page = usePage();
const users = computed(() => page.props.users);
const modalStore = useModalStore();
const tableFiltersStore = useTableFiltersStore();
const currentTab = computed(() => tableFiltersStore.getTab || 'active');

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

const openUserCreateModal = () => {
    modalStore.openUserCreateModal();
};

const openUserEditModal = (user) => {
    modalStore.openUserEditModal({ user });
};

const openOnlineActivity = (user) => {
    modalStore.openUserOnlineActivityModal({ user });
};

const isTraderRole = (user) => user.role?.name === 'Trader';

/** Баланс и переход в кошелёк — только для ролей с финансовым кошельком в админке. */
const ADMIN_WALLET_ROLES = ['Trader', 'Merchant', 'Team Leader', 'Super Admin'];

const userShowsWalletBalanceAndLink = (user) => {
    const role_name = user?.role?.name;
    return Boolean(role_name && ADMIN_WALLET_ROLES.includes(role_name));
};

const visitUserWallet = (user) => {
    router.visit(route('admin.users.wallet.index', user.id));
};

const confirmArchiveUser = (user) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите архивировать пользователя #' + user.id + '?',
        body: 'Действие можно отменить.',
        confirm_button_name: 'Архивировать',
        confirm: () => {
            router.post(route('admin.users.archive', user.id), {}, {
                preserveScroll: true,
            });
        },
    });
};

const confirmUnarchiveUser = (user) => {
    modalStore.openConfirmModal({
        title: 'Вы уверены что хотите вернуть пользователя из архива #' + user.id + '?',
        body: 'Действие можно отменить.',
        confirm_button_name: 'Вернуть',
        confirm: () => {
            router.delete(route('admin.users.unarchive', user.id), {
                preserveScroll: true,
            });
        },
    });
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Пользователи" />

        <UserCreateModal />
        <UserEditModal />
        <UserOnlineActivityModal />
        <ConfirmModal />

        <MainTableSection
            title="Пользователи"
            :data="users"
        >
            <template v-slot:button>
                <PageToolbar>
                    <PageToolbarAction
                        icon="plus"
                        title="Создать пользователя"
                        label="Создать пользователя"
                        @click="openUserCreateModal"
                    />
                </PageToolbar>
            </template>
            <template #header>
                <UsersNav :current="currentTab" />
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
                    <DataTable>
                        <template #head>
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
                                        Работает
                                    </th>
                                    <TableActionsHeadCell cell-class="px-6 py-3" />
                        </template>
                                <tr v-for="user in users.data" class="hover">
                                    <th scope="row" class="px-6 py-3 font-medium whitespace-nowrap">
                                        {{ user.id }}
                                    </th>
                                    <td class="px-6 py-3">
                                        <UserSummaryPopover :user="user">
                                            <div class="inline-flex max-w-[16rem] min-w-0 items-center gap-2 text-left hover:opacity-80 transition">
                                            <UserAvatar :user="user" class="shrink-0" />
                                            <div class="min-w-0 overflow-hidden">
                                                <div class="truncate" :title="user.email">
                                                    {{ user.email }}
                                                </div>
                                                <div class="truncate text-xs text-base-content/70">
                                                    <span :title="user.role.name">{{ user.role.name }}</span>
                                                </div>
                                            </div>
                                            <span
                                                v-if="user.banned_at"
                                                class="shrink-0"
                                                title="Пользователь заблокирован"
                                            >
                                                <svg class="w-4 h-4 text-danger" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm7.707-3.707a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586 9.707 8.293Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                            <span
                                                v-if="user.stop_traffic"
                                                class="shrink-0"
                                                title="Трафик остановлен"
                                            >
                                                <svg class="w-4 h-4 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm3-1a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                            <span
                                                v-else-if="user.traffic_enabled_at"
                                                class="shrink-0"
                                                :title="'Трафик включен: ' + user.traffic_enabled_at"
                                            >
                                                <svg class="w-4 h-4 text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2s10 4.477 10 10Zm-11.99 4a1 1 0 0 1-.705-.292l-3.99-3.96a1 1 0 0 1 1.41-1.419l3.285 3.26 6.289-6.254a1 1 0 0 1 1.41 1.418l-6.99 6.955a1 1 0 0 1-.709.292Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                            </div>
                                        </UserSummaryPopover>
                                    </td>
                                    <td class="px-6 py-3 text-nowrap">
                                        <template v-if="userShowsWalletBalanceAndLink(user)">{{ user.balance }} $</template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="px-6 py-3 text-nowrap">
                                        <button v-if="user.online_at" type="button" class="btn btn-ghost btn-xs gap-2 px-1" title="История онлайна" @click="openOnlineActivity(user)"><DateTime :data="user.online_at" :plural="true" :copyable="false"/></button>
                                    </td>
                                    <td class="px-6 py-3 text-nowrap">
                                        <template v-if="isTraderRole(user)">
                                            <input type="checkbox" :checked="user.is_online" class="toggle toggle-success" @change="toggleOnline(user)" :disabled="onlineForm.processing || isCooldown || currentTab === 'archived'">
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="px-6 py-3 text-nowrap text-right">
                                        <TableActionsDropdown>
                                            <template v-if="currentTab === 'active'">
                                                <TableAction v-if="user.can_be_impersonated" @click="impersonate(user)">
                                                    Войти как пользователь
                                                </TableAction>
                                                <TableAction
                                                    v-if="userShowsWalletBalanceAndLink(user)"
                                                    @click="visitUserWallet(user)"
                                                >
                                                    Финансы
                                                </TableAction>
                                                <TableAction @click="openUserEditModal(user)">
                                                    Редактировать
                                                </TableAction>
                                                <TableAction v-if="isTraderRole(user)" @click="confirmArchiveUser(user)">
                                                    Архивировать
                                                </TableAction>
                                            </template>
                                            <template v-else-if="isTraderRole(user)">
                                                <TableAction @click="confirmUnarchiveUser(user)">
                                                    Вернуть из архива
                                                </TableAction>
                                            </template>
                                        </TableActionsDropdown>
                                    </td>
                                </tr>
                    </DataTable>

                    <!-- Mobile view (cards list) -->
                    <DataCardList>
                            <DataCard
                                v-for="user in users.data"
                                :key="user.id"
                            >
                                    <!-- Шапка: ID -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-0 pb-2">
                                        <div class="inline-flex gap-3">
                                            <div class="inline-flex items-center">
                                                <span class="text-base-content/70">ID:</span> <span class="font-medium ml-4">{{ user.id }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Для экранов sm и больше -->
                                    <div class="hidden sm:block">
                                        <div class="flex items-center justify-between gap-2 py-2">
                                            <div class="inline-flex items-center justify-between gap-2 flex-1 min-w-0">
                                                <div class="min-w-0 flex-1 overflow-hidden">
                                                <UserSummaryPopover :user="user">
                                                    <div class="inline-flex min-w-0 max-w-full items-center gap-2 text-left hover:opacity-80 transition">
                                                    <UserAvatar :user="user" class="shrink-0" />
                                                    <div class="min-w-0 overflow-hidden">
                                                        <div class="truncate" :title="user.email">
                                                            {{ user.email }}
                                                        </div>
                                                        <div class="truncate text-xs text-base-content/70">
                                                            <span :title="user.role.name">{{ user.role.name }}</span>
                                                        </div>
                                                    </div>
                                                    </div>
                                                </UserSummaryPopover>
                                                </div>
                                                <div class="inline-flex shrink-0 items-center">
                                                    <span class="tex-xs text-base-content/70">Заходил:</span>
                                                    <span class="text-base-content ml-1">
                                                        <button v-if="user.online_at" type="button" class="btn btn-ghost btn-xs gap-2 px-1" title="История онлайна" @click="openOnlineActivity(user)"><DateTime :data="user.online_at" :plural="true" :copyable="false"/></button>
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
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="text-xs text-base-content/70 grid grid-cols-2 gap-x-4 gap-y-1 flex-1">
                                                <div class="inline-flex items-center">
                                                    <span>Баланс:</span>
                                                    <span class="text-base-content ml-1">
                                                        <template v-if="userShowsWalletBalanceAndLink(user)">{{ user.balance }} $</template>
                                                        <template v-else>—</template>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs text-base-content/70">Работает: </span>
                                                    <template v-if="isTraderRole(user)">
                                                        <input type="checkbox" :checked="user.is_online" class="toggle toggle-success toggle-sm" @change="toggleOnline(user)" :disabled="onlineForm.processing || isCooldown || currentTab === 'archived'">
                                                    </template>
                                                    <template v-else>—</template>
                                                </div>
                                                <TableActionsDropdown>
                                                    <template v-if="currentTab === 'active'">
                                                        <TableAction v-if="user.can_be_impersonated" @click="impersonate(user)">
                                                            Войти как пользователь
                                                        </TableAction>
                                                        <TableAction
                                                            v-if="userShowsWalletBalanceAndLink(user)"
                                                            @click="visitUserWallet(user)"
                                                        >
                                                            Финансы
                                                        </TableAction>
                                                        <TableAction @click="openUserEditModal(user)">
                                                            Редактировать
                                                        </TableAction>
                                                        <TableAction v-if="isTraderRole(user)" @click="confirmArchiveUser(user)">
                                                            Архивировать
                                                        </TableAction>
                                                    </template>
                                                    <template v-else-if="isTraderRole(user)">
                                                        <TableAction @click="confirmUnarchiveUser(user)">
                                                            Вернуть из архива
                                                        </TableAction>
                                                    </template>
                                                </TableActionsDropdown>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Для экранов меньше sm -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center gap-2 mb-2 min-w-0">
                                            <UserAvatar :user="user" class="shrink-0" />
                                            <div class="min-w-0 flex-1 overflow-hidden">
                                            <UserSummaryPopover :user="user">
                                                <div class="inline-flex min-w-0 flex-1 items-center gap-2 text-left hover:opacity-80 transition">
                                                    <div class="min-w-0 overflow-hidden">
                                                        <div class="truncate text-sm" :title="user.email">
                                                            {{ user.email }}
                                                        </div>
                                                        <div class="truncate text-xs text-base-content/70">
                                                            <span :title="user.role.name">{{ user.role.name }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </UserSummaryPopover>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1 mr-1">
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
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="text-xs text-base-content/70 grid gap-1 mb-2">
                                            <div>
                                                <span>Баланс:</span>
                                                <span class="text-base-content ml-1">
                                                    <template v-if="userShowsWalletBalanceAndLink(user)">{{ user.balance }} $</template>
                                                    <template v-else>—</template>
                                                </span>
                                            </div>
                                            <div class="flex items-center">
                                                <span class="tex-xs text-base-content/70">Заходил:</span>
                                                <span class="text-base-content ml-1">
                                                    <button v-if="user.online_at" type="button" class="btn btn-ghost btn-xs gap-2 px-1" title="История онлайна" @click="openOnlineActivity(user)"><DateTime :data="user.online_at" :plural="true" :copyable="false"/></button>
                                                    <span v-else>-</span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="tex-xs text-base-content/70">Работает:</span>
                                                <template v-if="isTraderRole(user)">
                                                    <input type="checkbox" :checked="user.is_online" class="toggle toggle-success toggle-sm" @change="toggleOnline(user)" :disabled="onlineForm.processing || isCooldown || currentTab === 'archived'">
                                                </template>
                                                <template v-else>—</template>
                                            </div>
                                            <TableActionsDropdown>
                                                <template v-if="currentTab === 'active'">
                                                    <TableAction v-if="user.can_be_impersonated" @click="impersonate(user)">
                                                        Войти как пользователь
                                                    </TableAction>
                                                    <TableAction
                                                        v-if="userShowsWalletBalanceAndLink(user)"
                                                        @click="visitUserWallet(user)"
                                                    >
                                                        Финансы
                                                    </TableAction>
                                                    <TableAction @click="openUserEditModal(user)">
                                                        Редактировать
                                                    </TableAction>
                                                    <TableAction v-if="isTraderRole(user)" @click="confirmArchiveUser(user)">
                                                        Архивировать
                                                    </TableAction>
                                                </template>
                                                <template v-else-if="isTraderRole(user)">
                                                    <TableAction @click="confirmUnarchiveUser(user)">
                                                        Вернуть из архива
                                                    </TableAction>
                                                </template>
                                            </TableActionsDropdown>
                                        </div>
                                    </div>
                            </DataCard>
                    </DataCardList>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
