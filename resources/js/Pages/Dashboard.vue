<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <Head title="Главная"/>

    <div>
        <div class="mx-auto space-y-6">
            <div class="flex justify-between">
                <h2 class="text-xl font-medium text-gray-900 dark:text-white sm:text-2xl">Главная</h2>
            </div>
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl ">
                <div class="p-6 text-gray-900 dark:text-gray-100">Вы вошли</div>
            </div>
        </div>
    </div>
</template>
