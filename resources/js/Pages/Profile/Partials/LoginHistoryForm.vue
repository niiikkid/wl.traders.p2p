<script setup>
import { formatDateTime } from '@/utils';
import DateTime from "@/Components/DateTime.vue";

const props = defineProps({
    loginHistory: {
        type: Array,
        required: true,
    },
});

const formatDate = (date) => {
    return formatDateTime(date);
};

const getStatusClass = (isSuccessful) => {
    return isSuccessful ? 'text-success' : 'text-error';
};

const getStatusText = (isSuccessful) => {
    return isSuccessful ? 'Успешно' : 'Неудачно';
};
</script>

<template>
    <section class="text-left">
        <header class="space-y-1">
            <h2 class="text-base font-semibold text-base-content">Сессии входа</h2>

            <p class="text-xs leading-relaxed text-base-content/60">
                Последние попытки входа: устройство, сеть и результат.
            </p>
        </header>

        <div class="mt-5">
            <!-- Desktop/tablet view (table) -->
            <div class="hidden xl:block">
                <div class="overflow-x-auto card">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Устройство</th>
                                <th>IP адрес</th>
                                <th>Браузер</th>
                                <th>ОС</th>
<!--                                <th>Местоположение</th>-->
                                <th>Дата и время</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in loginHistory" :key="index">
                                <td>{{ item.device_type }}</td>
                                <td>{{ item.ip_address }}</td>
                                <td>{{ item.browser }}</td>
                                <td>{{ item.operating_system }}</td>
<!--                                <td>{{ item.location }}</td>-->
                                <td>{{ formatDate(item.created_at) }}</td>
                                <td class="text-sm" :class="getStatusClass(item.is_successful)">
                                    {{ getStatusText(item.is_successful) }}
                                </td>
                            </tr>
                            <tr v-if="loginHistory.length === 0">
                                <td colspan="7" class="text-center text-base-content/60">
                                    Сессий пока нет
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile view (cards list) -->
            <div class="xl:hidden space-y-2">
                <div
                    v-for="(item, index) in loginHistory"
                    :key="index"
                    class="card bg-base-100 border border-base-300 shadow-sm"
                >
                    <div class="card-body px-3 py-2.5">
                        <div class="mb-1.5 flex items-start justify-between gap-2 border-b border-base-content/10 pb-1.5">
                            <div class="min-w-0 text-xs leading-snug">
                                <div class="text-[11px] uppercase tracking-wide text-base-content/60">Устройство</div>
                                <div class="mt-0.5 font-medium text-base-content">{{ item.device_type }}</div>
                            </div>
                            <div class="shrink-0">
                                <div
                                    class="badge badge-xs font-medium"
                                    :class="item.is_successful ? 'badge-success text-success-content' : 'badge-error text-error-content'"
                                >
                                    {{ getStatusText(item.is_successful) }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-1.5 text-xs leading-snug">
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">IP</span>
                                <span class="min-w-0 text-right font-medium text-base-content">{{ item.ip_address }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">Браузер</span>
                                <span class="min-w-0 text-right font-medium text-base-content truncate">{{ item.browser }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">ОС</span>
                                <span class="min-w-0 text-right font-medium text-base-content truncate">{{ item.operating_system }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="shrink-0 text-base-content/60">Время</span>
                                <span class="min-w-0 text-right font-medium text-base-content">
                                    <DateTime :data="item.created_at" :simple="true"/>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="loginHistory.length === 0" class="card bg-base-100 border border-base-300 shadow-sm">
                    <div class="card-body px-3 py-3">
                        <div class="text-center text-xs text-base-content/60">
                            Сессий пока нет
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
