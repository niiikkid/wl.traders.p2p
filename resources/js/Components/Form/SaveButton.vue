<script setup>
import PrimaryButton from "@/Components/PrimaryButton.vue";

const props = defineProps({
    disabled: {
        type: Boolean,
    },
    saved: {
        type: Boolean
    },
});
</script>

<template>
    <div class="flex items-center gap-4">
        <PrimaryButton :disabled="disabled">Сохранить</PrimaryButton>

        <Transition
            enter-active-class="transition ease-in-out"
            enter-from-class="opacity-0"
            leave-active-class="transition ease-in-out"
            leave-to-class="opacity-0"
        >
            <p v-if="saved" class="text-sm text-base-content/70">Сохранено.</p>
        </Transition>
    </div>
</template>

<style scoped>

</style>
