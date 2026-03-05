<script setup>
import {router, usePage} from "@inertiajs/vue3";
import {ref} from "vue";

const menu = ref(usePage().props.menu);

router.on('success', (event) => {
    menu.value = usePage().props.menu;
})

const openExternal = (link) => {
    window.open('/'+link, '_blank');
}
</script>

<template>
    <ul class="menu menu-md w-full space-y-0.5">
        <li>
            <span
                @click="openExternal('horizon')"
                @keydown.enter.space="openExternal('horizon')"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 32 32">
                    <path d="M27.313 4.688c-6.245-6.25-16.375-6.25-22.625-0.005-3.005 3-4.693 7.073-4.688 11.313-0.010 4.688 2.036 9.135 5.599 12.177h0.016c6.349 5.417 15.797 5.042 21.698-0.859 6.25-6.245 6.25-16.375 0-22.625zM21.333 18.661c-5.333 0-5.333-5.333-10.667-5.333-3.333 0-4.589 2.089-6.354 3.656h-0.005c-0.547-6.458 4.25-12.135 10.708-12.682 6.458-0.542 12.13 4.25 12.677 10.708-1.771 1.568-3.026 3.651-6.359 3.651z"/>
                </svg>
                Horizon
            </span>
        </li>
        <li>
            <span
                @click="openExternal('pulse')"
                @keydown.enter.space="openExternal('pulse')"
                role="link"
                tabindex="0"
            >
                <svg class="size-5 opacity-30" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3 8 4-16 3 8h4"/>
                </svg>
                Pulse
            </span>
        </li>
    </ul>
</template>

<style scoped>

</style>
