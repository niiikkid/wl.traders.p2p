<script setup>
/**
 * Single global mount point for app-wide modals.
 *
 * Modals are driven by the `modal` Pinia store (`open('name', params)` / `close('name')`)
 * and stay hidden until opened, so it is safe to mount them once in the authenticated layout.
 * As more domains migrate to the unified modal standard, register their modals here.
 *
 * NOTE: `ConfirmModal` is intentionally not registered here yet — many pages still mount it
 * locally. It will move here once those per-page mounts are removed in the orchestration phase.
 */
import DepositModal from '@/Modals/Wallet/DepositModal.vue';
import WithdrawalModal from '@/Modals/Wallet/WithdrawalModal.vue';
import TraderDepositModal from '@/Modals/Wallet/TraderDepositModal.vue';
import LeaderReserveDepositModal from '@/Modals/Wallet/LeaderReserveDepositModal.vue';
import TraderBalanceTransferModal from '@/Modals/Wallet/TraderBalanceTransferModal.vue';
</script>

<template>
    <DepositModal />
    <WithdrawalModal />
    <TraderDepositModal />
    <LeaderReserveDepositModal />
    <TraderBalanceTransferModal />
</template>
