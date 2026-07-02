<script setup>
import CryptoDepositModal from '@/Modals/Wallet/CryptoDepositModal.vue';
</script>

<template>
    <CryptoDepositModal
        modal-name="traderDeposit"
        store-route="trader.deposit.invoices.store"
        title="Пополнение траст баланса"
    />
</template>
