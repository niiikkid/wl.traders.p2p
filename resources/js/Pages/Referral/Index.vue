<script setup>
import {Head, router, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import DateTime from "@/Components/DateTime.vue";
import {ref} from "vue";

const props = defineProps({
    referrals: Object,
});

const referrals = ref(usePage().props.referrals);

router.on('success', (event) => {
    referrals.value = usePage().props.referrals;
})

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Рефералы" />

        <MainTableSection
            title="Рефералы"
            :data="referrals"
            description="Здесь вы можете увидеть список всех ваших рефералов."
        >
            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col" class="whitespace-nowrap">
                                            ID
                                        </th>
                                        <th scope="col" class="whitespace-nowrap">
                                            Пользователь
                                        </th>
                                        <th scope="col" class="whitespace-nowrap">
                                            Сделок
                                        </th>
                                        <th scope="col" class="whitespace-nowrap">
                                            Доход
                                        </th>
                                        <th scope="col" class="whitespace-nowrap">
                                            Дата регистрации
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="referral in referrals.data" :key="referral.id" class="hover">
                                        <th scope="row" class="font-medium whitespace-nowrap">
                                            {{ referral.id }}
                                        </th>
                                        <td class="whitespace-nowrap">
                                            <div class="inline-flex items-center gap-3">
                                                <div class="avatar">
                                                    <div class="w-10 rounded-full">
                                                        <img :src="'https://api.dicebear.com/9.x/'+referral.avatar_style+'/svg?seed='+referral.avatar_uuid" alt="user photo">
                                                    </div>
                                                </div>
                                                <div class="leading-tight">
                                                    <div class="whitespace-nowrap">
                                                        {{ referral.email }}
                                                    </div>
                                                    <div class="text-xs text-base-content/70 whitespace-nowrap">
                                                        {{ referral.name }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            {{ referral.orders_count }}
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="badge badge-outline">
                                                {{ referral.total_profit || '0.00' }} USDT
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <DateTime :data="referral.created_at"/>
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
                                v-for="referral in referrals.data"
                                :key="referral.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Шапка: ID и дата привлечения -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                        <div class="inline-flex items-center">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="ml-1 font-medium">{{ referral.id }}</span>
                                        </div>
                                        <DateTime class="justify-start" :data="referral.created_at"/>
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <!-- Пользователь -->
                                        <div class="flex items-start justify-between">
                                            <div class="text-base-content/70 text-sm">Пользователь</div>
                                            <div class="inline-flex items-center gap-2">
                                                <div class="avatar">
                                                    <div class="w-8 rounded-full">
                                                        <img :src="'https://api.dicebear.com/9.x/'+referral.avatar_style+'/svg?seed='+referral.avatar_uuid" alt="user photo">
                                                    </div>
                                                </div>
                                                <div class="leading-tight text-right">
                                                    <div class="whitespace-nowrap text-sm">
                                                        {{ referral.email }}
                                                    </div>
                                                    <div class="text-xs text-base-content/70 whitespace-nowrap">
                                                        {{ referral.name }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Сделок и Доход -->
                                        <div class="flex items-center justify-between border-t border-base-content/10 pt-2 mt-1">
                                            <div class="flex items-center gap-2">
                                                <div class="text-base-content/70 text-xs">Сделок</div>
                                                <div class="text-base-content font-medium">{{ referral.orders_count }}</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="text-base-content/70 text-xs">Доход</div>
                                                <span class="badge badge-sm badge-outline">
                                                    {{ referral.total_profit || '0.00' }} USDT
                                                </span>
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
