<script setup>
defineProps({
    cellClass: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <th scope="col" class="w-16 text-right" :class="cellClass">
        <span class="sr-only">Действия</span>
    </th>
</template>
