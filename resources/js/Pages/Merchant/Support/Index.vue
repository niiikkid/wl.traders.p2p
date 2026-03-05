<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import AddMobileIcon from "@/Components/AddMobileIcon.vue";
import DateTime from "@/Components/DateTime.vue";
import {useModalStore} from "@/store/modal.js";
import SupportCreateModal from "@/Modals/MerchantSupport/SupportCreateModal.vue";
import SupportEditModal from "@/Modals/MerchantSupport/SupportEditModal.vue";
import { ref } from 'vue';

const supports = ref(usePage().props.supports);
const modalStore = useModalStore();

router.on('success', () => {
    supports.value = usePage().props.supports;
});

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Поддержка" />

        <MainTableSection
            title="Разработчики"
            info="Роль «Разработчик» создана для интеграции без полного доступа к аккаунту мерчанта. Разработчик может видеть сделки, документацию по интеграции и получать ключ интеграции."
            :data="supports"
        >
            <template v-slot:button>
                <button
                    @click="modalStore.openSupportCreateModal()"
                    type="button"
                    class="hidden md:block btn btn-primary"
                >
                    Добавить разработчика
                </button>
                <AddMobileIcon
                    @click="modalStore.openSupportCreateModal()"
                />
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
                                        Саппорт
                                    </th>
                                    <th scope="col">
                                        Онлайн
                                    </th>
                                    <th scope="col">
                                        Создан
                                    </th>
                                    <th scope="col" class="flex justify-center">
                                        <span class="sr-only">Действия</span>
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="support in supports.data" class="bg-base-100 border-b last:border-none">
                                    <th scope="row" class="font-medium whitespace-nowrap">
                                        {{ support.id }}
                                    </th>
                                    <td class="text-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                            <img :src="'https://api.dicebear.com/9.x/'+support.avatar_style+'/svg?seed='+support.avatar_uuid" class="w-10 h-10 rounded-full" alt="support photo">
                                            <div>
                                                <div class="text-nowrap text-base-content">
                                                    {{ support.email }}
                                                </div>
                                                <div class="text-nowrap text-xs">
                                                    {{ support.name }}
                                                </div>
                                            </div>
                                            <span
                                                v-if="support.banned_at"
                                                title="Пользователь заблокирован"
                                            >
                                                <svg class="w-4 h-4 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                    <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm7.707-3.707a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586 9.707 8.293Z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <DateTime v-if="support.online_at" :data="support.online_at" :plural="true"/>
                                    </td>
                                    <td class="text-nowrap">
                                        <DateTime :data="support.created_at"/>
                                    </td>
                                    <td class="text-nowrap text-right">
                                        <button
                                            @click="modalStore.openSupportEditModal({ supportId: support.id })"
                                            type="button"
                                            class="btn btn-ghost btn-xs"
                                        >
                                            <svg class="w-[22px] h-[22px] text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.779 17.779 4.36 19.918 6.5 13.5m4.279 4.279 8.364-8.643a3.027 3.027 0 0 0-2.14-5.165 3.03 3.03 0 0 0-2.14.886L6.5 13.5m4.279 4.279L6.499 13.5m2.14 2.14 6.213-6.504M12.75 7.04 17 11.28"/>
                                            </svg>
                                        </button>
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
                                v-for="support in supports.data"
                                :key="support.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Шапка: ID и дата создания -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                        <div class="inline-flex items-center">
                                            <span class="text-base-content/70">ID:</span> <span class="ml-1 font-medium">{{ support.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="support.created_at"/>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <!-- Саппорт -->
                                        <div class="flex items-center justify-between">
                                            <div class="inline-flex items-center gap-2">
                                                <img :src="'https://api.dicebear.com/9.x/'+support.avatar_style+'/svg?seed='+support.avatar_uuid" class="w-10 h-10 rounded-full" alt="support photo">
                                                <div class="text-right">
                                                    <div class="text-nowrap text-base-content">
                                                        {{ support.email }}
                                                    </div>
                                                    <div class="text-nowrap text-xs opacity-70">
                                                        {{ support.name }}
                                                    </div>
                                                </div>
                                                <span
                                                    v-if="support.banned_at"
                                                    title="Пользователь заблокирован"
                                                >
                                                    <svg class="w-4 h-4 text-error" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                                        <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm7.707-3.707a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586 9.707 8.293Z" clip-rule="evenodd"/>
                                                    </svg>
                                                </span>
                                            </div>
                                            <button
                                                @click="modalStore.openSupportEditModal({ supportId: support.id })"
                                                type="button"
                                                class="btn btn-ghost btn-xs"
                                            >
                                                <svg class="w-[22px] h-[22px] text-success" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.779 17.779 4.36 19.918 6.5 13.5m4.279 4.279 8.364-8.643a3.027 3.027 0 0 0-2.14-5.165 3.03 3.03 0 0 0-2.14.886L6.5 13.5m4.279 4.279L6.499 13.5m2.14 2.14 6.213-6.504M12.75 7.04 17 11.28"/>
                                                </svg>
                                            </button>
                                        </div>

                                        <div v-if="support.online_at" class="flex items-center justify-between border-t border-base-content/10 pt-2 mt-2">
                                            <div class="text-base-content/70 text-xs">Онлайн: </div>
                                            <DateTime :data="support.online_at" :plural="true" class="text-xs"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>
        <SupportCreateModal/>
        <SupportEditModal/>
    </div>
</template>
