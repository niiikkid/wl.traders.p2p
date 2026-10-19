<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputFilter from '@/Components/Filters/Partials/InputFilter.vue';
import FiltersPanel from '@/Components/Filters/FiltersPanel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import MainTableSection from '@/Wrappers/MainTableSection.vue';
import { useModalStore } from '@/store/modal.js';
import { reactive, ref } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

defineProps({
    users: { type: Object, required: true },
    requiresTwoFactor: { type: Boolean, default: false },
});

const modalStore = useModalStore();
const forms = reactive({});
const oneTimePassword = ref('');
const deleteForm = useForm({ one_time_password: '' });

const addressForm = (userId) => {
    if (!forms[userId]) {
        forms[userId] = useForm({ user_id: userId, name: '', address: '', one_time_password: '' });
    }

    return forms[userId];
};

const addAddress = (userId) => {
    const form = addressForm(userId);
    form.one_time_password = oneTimePassword.value;
    form.post(route('admin.withdrawals.address.whitelist.store'), {
        preserveScroll: true,
        errorBag: `address-${userId}`,
        onSuccess: () => {
            form.reset();
            oneTimePassword.value = '';
        },
    });
};

const deleteAddress = (user, address) => {
    modalStore.openConfirmModal({
        title: `Удалить адрес ${address.masked_address} пользователя ${user.email}?`,
        body: 'Адрес исчезнет из списка доступных адресов вывода.',
        confirm_button_name: 'Удалить',
        confirm: () => {
            deleteForm.one_time_password = oneTimePassword.value;
            deleteForm.delete(route('admin.withdrawals.address.whitelist.destroy', address.id), {
                preserveScroll: true,
                onSuccess: () => { oneTimePassword.value = ''; },
            });
        },
    });
};
</script>

<template>
    <div>
        <Head title="Белый список адресов вывода" />
        <MainTableSection title="Белый список адресов вывода" :data="users">
            <template #header>
                <FiltersPanel name="withdrawal-address-whitelist">
                    <InputFilter
                        name="search"
                        placeholder="Логин, имя или полный адрес"
                        class="w-full lg:w-96"
                    />
                </FiltersPanel>
                <div v-if="requiresTwoFactor" class="mt-3 max-w-sm">
                    <label for="whitelist-otp" class="label">Код 2FA администратора</label>
                    <TextInput id="whitelist-otp" v-model="oneTimePassword" autocomplete="one-time-code" inputmode="numeric" class="w-full" />
                </div>
                <InputError :message="deleteForm.errors.address" />
                <InputError :message="deleteForm.errors.one_time_password" />
            </template>
            <template #body>
                <div class="grid min-w-0 grid-cols-1 gap-4">
                    <section v-for="user in users.data" :key="user.id" class="card min-w-0 border border-base-300 bg-base-100 shadow-sm" :data-user-id="user.id">
                        <div class="card-body min-w-0 gap-3 p-4">
                            <h3 class="card-title wrap-anywhere text-base">{{ user.email }}</h3>
                            <ul v-if="user.addresses.length" class="space-y-2">
                                <li v-for="address in user.addresses" :key="address.id" class="flex items-start gap-2 rounded-xl bg-base-200 p-3" :data-address-id="address.id">
                                    <div class="min-w-0 flex-1">
                                        <p v-if="address.name" class="wrap-anywhere text-sm font-medium">{{ address.name }}</p>
                                        <p class="wrap-anywhere font-mono text-xs">{{ address.address }}</p>
                                        <p class="mt-1 text-xs text-base-content/60">USDT · TRC20<span v-if="address.invoices_count"> · Заявок: {{ address.invoices_count }}</span></p>
                                    </div>
                                    <button type="button" class="btn btn-ghost btn-sm btn-square shrink-0 text-error" :aria-label="`Удалить адрес ${address.masked_address}`" :disabled="deleteForm.processing || address.invoices_count > 0" :title="address.invoices_count ? 'Адрес связан с заявками на вывод' : 'Удалить адрес'" @click="deleteAddress(user, address)">
                                        <svg class="size-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m-1.022-.165a48.11 48.11 0 0 1 16.5 0M9 5.25V3.75h6v1.5" />
                                        </svg>
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-base-content/60">Адресов пока нет.</p>
                            <form class="space-y-2" @submit.prevent="addAddress(user.id)">
                                <label :for="`address-name-${user.id}`" class="label">Название адреса</label>
                                <TextInput :id="`address-name-${user.id}`" v-model="addressForm(user.id).name" maxlength="80" placeholder="Необязательно" class="w-full" />
                                <InputError :message="addressForm(user.id).errors.name" />
                                <label :for="`address-${user.id}`" class="label">Адрес USDT TRC20</label>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <TextInput :id="`address-${user.id}`" v-model="addressForm(user.id).address" placeholder="Введите адрес" class="min-w-0 flex-1" />
                                    <button type="submit" class="btn btn-primary" :disabled="addressForm(user.id).processing">Добавить</button>
                                </div>
                                <InputError :message="addressForm(user.id).errors.address" />
                                <InputError :message="addressForm(user.id).errors.user_id" />
                                <InputError :message="addressForm(user.id).errors.one_time_password" />
                            </form>
                        </div>
                    </section>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>
