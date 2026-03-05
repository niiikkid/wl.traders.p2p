<script setup>
import { ref } from "vue";
import {usePage} from "@inertiajs/vue3";

const exchangeRateMarkup = ref(usePage().props.exchangeRateMarkup);

// Функция обновления наценки с проверкой корректности ввода
const updateMarkup = (index, event) => {
    let value = event.target.value.replace(',', '.'); // Заменяем запятую на точку

    // Оставляем только цифры и точку, удаляем любые другие символы
    value = value.replace(/[^0-9.]/g, '');

    exchangeRateMarkup.value[index].markup = value;
    event.target.value = value;
};
</script>

<template>
    <div class="relative overflow-x-auto shadow-md rounded-table">
        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
            <tr>
                <th scope="col" class="px-6 py-3">Валюта</th>
                <th scope="col" class="px-6 py-3">Наценка (%)</th>
            </tr>
            </thead>
            <tbody>
            <tr
                v-for="(item, index) in exchangeRateMarkup"
                :key="item.currency"
                class="bg-white border-b dark:bg-gray-800 dark:border-gray-700"
            >
                <td class="px-6 py-2 font-medium text-gray-900 dark:text-white">
                    {{ item.currency.toUpperCase() }}
                </td>
                <td class="px-6 py-2">
                    <input
                        type="text"
                        inputmode="decimal"
                        class="w-24 p-1 text-gray-900 bg-gray-50 border border-gray-300 rounded-lg dark:bg-gray-700 dark:text-white dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500"
                        :value="item.markup"
                        @input="updateMarkup(index, $event)"
                    />
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</template>
