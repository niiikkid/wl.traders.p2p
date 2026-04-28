<script setup>
import {Head} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SettingsPanel from '@/Pages/Settings/SettingsPanel.vue';
import PrimeTimeBonus from '@/Pages/Settings/Partials/PrimeTimeBonus.vue';
import AppSlogan from '@/Pages/Settings/Partials/AppSlogan.vue';
import SupportLink from '@/Pages/Settings/Partials/SupportLink.vue';
import LandingTelegramLink from '@/Pages/Settings/Partials/LandingTelegramLink.vue';
import FundsOnHold from '@/Pages/Settings/Partials/FundsOnHold.vue';
import MaxPendingDisputes from '@/Pages/Settings/Partials/MaxPendingDisputes.vue';
import MaxRejectedDisputes from '@/Pages/Settings/Partials/MaxRejectedDisputes.vue';
import TempVip from '@/Pages/Settings/Partials/TempVip.vue';
import DefaultReserveBalanceLimit from '@/Pages/Settings/Partials/DefaultReserveBalanceLimit.vue';

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <Head title="Настройки системы" />

    <!-- Без доп. px: отступы задаёт container в AuthenticatedLayout (как у остальных админ-страниц) -->
    <div class="w-full min-w-0">
        <h2 class="mb-4 text-lg font-semibold tracking-tight text-base-content">
            Настройки системы
        </h2>

        <div
            class="grid min-w-0 grid-cols-1 gap-3 lg:grid-cols-2 lg:gap-x-4 lg:gap-y-3"
        >
            <SettingsPanel>
                <AppSlogan />
            </SettingsPanel>
            <SettingsPanel>
                <PrimeTimeBonus />
            </SettingsPanel>
            <SettingsPanel>
                <TempVip />
            </SettingsPanel>
            <SettingsPanel>
                <DefaultReserveBalanceLimit />
            </SettingsPanel>
            <SettingsPanel>
                <SupportLink />
            </SettingsPanel>
            <SettingsPanel>
                <LandingTelegramLink />
            </SettingsPanel>
            <SettingsPanel>
                <FundsOnHold />
            </SettingsPanel>
            <SettingsPanel>
                <MaxPendingDisputes />
            </SettingsPanel>
            <SettingsPanel>
                <MaxRejectedDisputes />
            </SettingsPanel>
        </div>
    </div>
</template>
