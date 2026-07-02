<script setup>
import CryptoDepositModal from '@/Modals/Wallet/CryptoDepositModal.vue';
</script>

<template>
    <CryptoDepositModal
        modal-name="leaderReserveDeposit"
        store-route="leader.deposit.invoices.store"
        title="Пополнение общего страхового резерва"
        description="Средства зачисляются только на резервный баланс Team Leader и не попадают на баланс дохода."
    />
</template>
