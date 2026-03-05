<script setup>
import {Head} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import {ref} from "vue";
import DisplayUUID from "@/Components/DisplayUUID.vue";
import DateTime from "@/Components/DateTime.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";

const props = defineProps({
    logs: Object,
    filters: Object,
});

// Состояние для отслеживания развернутых строк (desktop)
const expandedRows = ref({});
// Состояние для отслеживания развернутых карточек (mobile)
const expandedCards = ref({});

// Функция для переключения состояния развернутой строки (desktop)
const toggleRow = (logId) => {
    expandedRows.value[logId] = !expandedRows.value[logId];
};

// Функция для переключения состояния развернутой карточки (mobile)
const toggleExpand = (logId) => {
    expandedCards.value[logId] = !expandedCards.value[logId];
};

defineOptions({ layout: AuthenticatedLayout })
</script>

<template>
    <div>
        <Head title="Логи колбеков" />

        <MainTableSection
            title="Логи колбеков"
            :data="logs"
        >
            <template v-slot:table-filters>
                <div>
                    <FiltersPanel name="callback-logs">
                        <InputFilter
                            name="uuid"
                            placeholder="UUID сущности"
                        />
                        <InputFilter
                            name="merchant"
                            placeholder="Мерчант (имя или uuid)"
                        />
                    </FiltersPanel>
                </div>
            </template>

            <template v-slot:body>
                <div class="relative">
                    <!-- Desktop/tablet view (table) -->
                    <div class="hidden xl:block rounded-table relative">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th scope="col">
                                            ID
                                        </th>
                                        <th scope="col">
                                            Тип
                                        </th>
                                        <th scope="col">
                                            UUID сущности
                                        </th>
                                        <th scope="col">
                                            URL
                                        </th>
                                        <th scope="col">
                                            HTTP код
                                        </th>
                                        <th scope="col">
                                            Статус
                                        </th>
                                        <th scope="col">
                                            Создан
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="log in logs.data" :key="log.id">
                                        <tr
                                            class="hover cursor-pointer"
                                            @click.stop="toggleRow(log.id)"
                                        >
                                            <th scope="row" class="font-medium whitespace-nowrap">
                                                {{ log.id }}
                                            </th>
                                            <td>
                                                {{ log.type }}
                                            </td>
                                            <td>
                                                <DisplayUUID v-if="log.callbackable" :uuid="log.callbackable.uuid" />
                                                <span v-else>-</span>
                                            </td>
                                            <td class="max-w-64 truncate">
                                                {{ log.url }}
                                            </td>
                                            <td>
                                                <span :class="log.status_code && log.status_code >= 200 && log.status_code < 300 ? 'badge badge-xs badge-success' : 'badge badge-xs badge-error'">
                                                    {{ log.status_code || '-' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span :class="log.is_success ? 'badge badge-xs badge-success' : 'badge badge-xs badge-error'">
                                                    {{ log.is_success ? 'Успешно' : 'Ошибка' }}
                                                </span>
                                            </td>
                                            <td>
                                                <DateTime :data="log.created_at" show-time />
                                            </td>
                                        </tr>

                                        <!-- Развернутая информация -->
                                        <tr v-if="expandedRows[log.id]" class="bg-base-200">
                                            <td colspan="7" class="px-6 py-4">
                                                <h4 class="font-semibold mb-2">Детали</h4>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div v-if="log.request_data" class="mb-4">
                                                        <div class="opacity-70 mb-1">Данные запроса:</div>
                                                        <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ JSON.stringify(log.request_data, null, 2) }}</pre>
                                                    </div>

                                                    <div v-if="log.response_data">
                                                        <div class="opacity-70 mb-1">Данные ответа:</div>
                                                        <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs">{{ JSON.stringify(log.response_data, null, 2) }}</pre>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile view (cards list) -->
                    <div class="xl:hidden space-y-3">
                        <div class="space-y-2">
                            <div
                                v-for="log in logs.data"
                                :key="log.id"
                                class="card bg-base-100 shadow-sm"
                            >
                                <div class="card-body p-4 pt-2 pb-3">
                                    <!-- Компактная шапка: ID и дата -->
                                    <div class="flex justify-between items-center border-b border-base-content/10 mb-1 pb-2">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-base-content/70">ID:</span>
                                            <span class="font-medium text-base-content">{{ log.id }}</span>
                                        </div>
                                        <div class="inline-flex items-center">
                                            <DateTime class="justify-start" :data="log.created_at" show-time />
                                        </div>
                                    </div>

                                    <!-- Для >= sm -->
                                    <div class="hidden sm:flex items-center justify-between gap-2">
                                        <div class="flex-1 min-w-0 inline-flex items-center gap-5">
                                            <div class="w-30">
                                                <div class="text-xs text-base-content/70 mb-1">Тип</div>
                                                <div class="text-sm text-base-content truncate">{{ log.type }}</div>
                                            </div>
                                            <div v-if="log.callbackable">
                                                <div class="text-xs text-base-content/70 mb-1">UUID сущности</div>
                                                <DisplayUUID :uuid="log.callbackable.uuid" />
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 flex items-center gap-2">
                                            <span :class="log.status_code && log.status_code >= 200 && log.status_code < 300 ? 'badge badge-xs badge-success' : 'badge badge-xs badge-error'">
                                                {{ log.status_code || '-' }}
                                            </span>
                                            <span :class="log.is_success ? 'badge badge-xs badge-success' : 'badge badge-xs badge-error'">
                                                {{ log.is_success ? 'Успешно' : 'Ошибка' }}
                                            </span>
                                        </div>
                                        <div>
                                            <button
                                                class="btn btn-primary btn-xs"
                                                @click.stop="toggleExpand(log.id)"
                                                :aria-expanded="!!expandedCards[log.id]"
                                                :aria-label="!!expandedCards[log.id] ? 'Скрыть' : 'Показать детали'"
                                            >
                                                <svg
                                                    :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[log.id]}]"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Для xs -->
                                    <div class="sm:hidden">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs text-base-content/70 mb-1">Тип</div>
                                                <div class="text-sm text-base-content truncate">{{ log.type }}</div>
                                            </div>
                                            <div>
                                                <span :class="log.is_success ? 'badge badge-xs badge-success' : 'badge badge-xs badge-error'">
                                                    {{ log.is_success ? 'Успешно' : 'Ошибка' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="border-b border-base-content/10 my-2"></div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1 min-w-0">
                                                <div v-if="log.callbackable" class="text-xs text-base-content/70 mb-1">UUID сущности</div>
                                                <DisplayUUID v-if="log.callbackable" :uuid="log.callbackable.uuid" />
                                                <div v-else class="text-sm text-base-content/60">-</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span :class="log.status_code && log.status_code >= 200 && log.status_code < 300 ? 'badge badge-xs badge-success' : 'badge badge-xs badge-error'">
                                                    {{ log.status_code || '-' }}
                                                </span>
                                                <button
                                                    class="btn btn-primary btn-xs"
                                                    @click.stop="toggleExpand(log.id)"
                                                    :aria-expanded="!!expandedCards[log.id]"
                                                    :aria-label="!!expandedCards[log.id] ? 'Скрыть' : 'Показать детали'"
                                                >
                                                    <svg
                                                        :class="['w-4 h-4 transition-transform', {'rotate-180': !!expandedCards[log.id]}]"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                        stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Раскрываемая часть -->
                                    <div v-show="!!expandedCards[log.id]" class="mt-3 space-y-2 bg-base-300/50 rounded-box p-2">
                                        <div v-if="log.url" class="flex items-start gap-2 text-sm">
                                            <span class="text-base-content/80 shrink-0">URL:</span>
                                            <span class="text-base-content/60 break-words">{{ log.url }}</span>
                                        </div>
                                        <div v-if="log.request_data" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Данные запроса:</div>
                                            <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs break-words">{{ JSON.stringify(log.request_data, null, 2) }}</pre>
                                        </div>
                                        <div v-if="log.response_data" class="mt-2 pt-2 border-t border-base-300">
                                            <div class="text-xs opacity-70 mb-1">Данные ответа:</div>
                                            <pre class="bg-base-100 p-2 rounded overflow-auto max-h-40 text-xs break-words">{{ JSON.stringify(log.response_data, null, 2) }}</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </MainTableSection>
    </div>
</template>

<style scoped>
.cursor-pointer {
    cursor: pointer;
}
</style>
