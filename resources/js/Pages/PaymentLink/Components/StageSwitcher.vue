<script setup>
const props = defineProps({
    stage: {
        type: String,
    },
});

const emit = defineEmits(['setStage']);

const setStage = (type) => {
    emit('setStage', type);
};
</script>

<template>
    <div class="tabs tabs-boxed text-xs">
        <a @click="setStage('select_gateway')" class="tab tab-sm" :class="{'tab-active' : stage === 'select_gateway'}">select_gateway</a>
        <a @click="setStage('payment')" class="tab tab-sm" :class="{'tab-active' : stage === 'payment'}">payment</a>
        <a @click="setStage('success')" class="tab tab-sm" :class="{'tab-active' : stage === 'success'}">success</a>
        <a @click="setStage('fail')" class="tab tab-sm" :class="{'tab-active' : stage === 'fail'}">fail</a>
        <a @click="setStage('dispute_review')" class="tab tab-sm" :class="{'tab-active' : stage === 'dispute_review'}">dispute_review</a>
        <a @click="setStage('dispute_canceled')" class="tab tab-sm" :class="{'tab-active' : stage === 'dispute_canceled'}">dispute_canceled</a>
    </div>
</template>

<style scoped>

</style>
