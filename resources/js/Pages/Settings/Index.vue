<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimeTimeBonus from "@/Pages/Settings/Partials/PrimeTimeBonus.vue";
import SupportLink from "@/Pages/Settings/Partials/SupportLink.vue";
import FundsOnHold from "@/Pages/Settings/Partials/FundsOnHold.vue";
import MaxPendingDisputes from "@/Pages/Settings/Partials/MaxPendingDisputes.vue";
import MaxRejectedDisputes from "@/Pages/Settings/Partials/MaxRejectedDisputes.vue";
import TempVip from "@/Pages/Settings/Partials/TempVip.vue";
import DefaultReserveBalanceLimit from "@/Pages/Settings/Partials/DefaultReserveBalanceLimit.vue";

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <Head title="Настройки системы" />

    <div>
        <div>
            <div class="mx-auto space-y-6">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl sm:text-3xl font-bold text-base-content">Настройки системы</h2>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <PrimeTimeBonus class="max-w-xl"></PrimeTimeBonus>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <TempVip class="max-w-xl"></TempVip>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <DefaultReserveBalanceLimit class="max-w-xl"></DefaultReserveBalanceLimit>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <SupportLink class="max-w-xl"></SupportLink>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <FundsOnHold class="max-w-xl"></FundsOnHold>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <MaxPendingDisputes class="max-w-xl"></MaxPendingDisputes>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <MaxRejectedDisputes class="max-w-xl"></MaxRejectedDisputes>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
