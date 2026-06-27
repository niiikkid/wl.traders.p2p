<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({});

const leave = () => {
    form.post(route('impersonate.leave'));
};
</script>

<template>
    <button
        type="button"
        class="btn btn-sm btn-warning rounded-xl w-full"
        :disabled="form.processing"
        @click="leave"
    >
        Выйти
        <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2"/>
        </svg>
    </button>
</template>
