<script setup>
import {Head, usePage} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import {ref} from "vue";
import TextInput from "@/Components/TextInput.vue";

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Вайтлист адресов" />

        <div class="mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-xl sm:text-4xl">Вайтлист адресов</h2>
                <slot name="button"></slot>
            </div>
            <dib>
                <FiltersPanel name="sms-logs">
                    <InputFilter
                        name="search"
                        placeholder="Почта, имя или адресс"
                        class="lg:w-96 w-72"
                    />
                </FiltersPanel>
            </dib>
            <div class="grid grid-cols-1 gap-6">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title text-xl mb-3">user1@example.com</h3>
                        <ul class="space-y-2 mb-3">
                            <li class="flex items-center justify-between bg-base-200 p-3 rounded-xl address-item">
                                <span class="text-sm font-mono">0x123...abc</span>
                                <button class="btn btn-ghost btn-sm text-error">
                                    <svg class="w-[20px] h-[20px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M8.586 2.586A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4a2 2 0 0 1 .586-1.414ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </li>
                            <li class="flex items-center justify-between bg-base-200 p-3 rounded-xl address-item">
                                <span class="text-sm font-mono">0x456...def</span>
                                <button class="btn btn-ghost btn-sm text-error">
                                    <svg class="w-[20px] h-[20px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M8.586 2.586A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4a2 2 0 0 1 .586-1.414ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </li>
                        </ul>
                        <div class="mt-3 flex gap-2">
                            <text-input
                                class="w-full"
                                model-value=""
                                placeholder="Введите адрес"
                            ></text-input>
                            <button class="btn btn-primary">Добавить</button>
                        </div>
                    </div>
                </div>
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title text-xl mb-3">user2@example.com</h3>
                        <ul class="space-y-2">
                            <li class="flex items-center justify-between bg-base-200 p-3 rounded-xl address-item">
                                <span class="text-sm font-mono">0x789...ghi</span>
                                <button class="btn btn-ghost btn-sm text-error">
                                    <svg class="w-[20px] h-[20px]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M8.586 2.586A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4a2 2 0 0 1 .586-1.414ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </li>
                        </ul>
                        <div class="mt-3 flex gap-2">
                            <text-input
                                class="w-full"
                                model-value=""
                                placeholder="Введите адрес"
                            ></text-input>
                            <button class="btn btn-primary">Добавить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
