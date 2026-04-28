<script setup>
const props = defineProps({
    /** DaisyUI color: primary | accent | secondary */
    variant: {
        type: String,
        default: 'primary',
    },
});

const variantClass = {
    primary: 'btn-primary',
    accent: 'btn-accent',
    secondary: 'btn-secondary',
}[props.variant] || 'btn-primary';
</script>

<template>
    <button
        type="button"
        class="md:hidden btn btn-square btn-sm"
        :class="variantClass"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span class="sr-only">Создать пользователя</span>
    </button>
</template>

<style scoped>

</style>
