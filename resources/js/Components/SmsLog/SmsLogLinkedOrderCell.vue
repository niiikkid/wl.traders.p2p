<script setup>
import CopyableOrderUid from '@/Components/CopyableOrderUid.vue';
import MoneyValue from '@/Components/MoneyValue.vue';
import OrderDetailsOpenButton from '@/Components/Order/OrderDetailsOpenButton.vue';

defineProps({
    order: {
        type: Object,
        default: null,
    },
    linkable: {
        type: Boolean,
        default: false,
    },
    rejectable: {
        type: Boolean,
        default: false,
    },
    rejected: {
        type: Boolean,
        default: false,
    },
    showOrderDetailsButton: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['open-order', 'link', 'reject']);

const openOrder = (order) => {
    emit('open-order', order);
};

const openLinkModal = () => {
    emit('link');
};

const rejectSmsLog = () => {
    emit('reject');
};
</script>

<template>
    <div v-if="order" class="inline-flex items-center gap-2">
        <div class="flex flex-col gap-0.5">
            <MoneyValue :value="order.amount" :currency="order.currency" compact/>
            <CopyableOrderUid :uuid="order.uuid ?? ''"/>
        </div>
        <OrderDetailsOpenButton
            v-if="showOrderDetailsButton"
            @click="openOrder(order)"
        />
    </div>

    <div v-else-if="rejected" class="text-xs font-medium text-error">
        Отклонено
    </div>

    <div v-else class="flex flex-col items-start gap-1.5">
        <button
            v-if="rejectable"
            type="button"
            class="btn btn-outline btn-error btn-xs"
            @click="rejectSmsLog"
        >
            Отклонить
        </button>
        <button
            v-if="linkable"
            type="button"
            class="btn btn-outline btn-primary btn-xs"
            @click="openLinkModal"
        >
            Привязать
        </button>
    </div>
</template>
