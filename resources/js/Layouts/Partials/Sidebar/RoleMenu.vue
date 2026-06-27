<script setup>
import { useViewStore } from '@/store/view.js';
import TraderMenu from '@/Layouts/Partials/TraderMenu.vue';
import MerchantMenu from '@/Layouts/Partials/MerchantMenu.vue';
import TeamLeaderMenu from '@/Layouts/Partials/TeamLeaderMenu.vue';
import AdminMenu from '@/Layouts/Partials/AdminMenu.vue';
import SupportMenu from '@/Layouts/Partials/SupportMenu.vue';

const viewStore = useViewStore();
</script>

<template>
    <div class="contents">
        <TraderMenu v-show="viewStore.isTraderViewMode" />
        <MerchantMenu v-show="viewStore.isMerchantViewMode" />
        <TeamLeaderMenu v-show="viewStore.isTeamLeaderViewMode" />
        <AdminMenu v-show="viewStore.isAdminViewMode" />
        <SupportMenu v-show="viewStore.isSupportViewMode" />
    </div>
</template>
