<script setup>
import SectionTitle from '@/Components/SectionTitle.vue';
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
    <section>
        <SectionTitle>
            <template #title>История авторизаций</template>
            <template #description>
                Здесь вы можете просмотреть историю входов в ваш аккаунт.
            </template>
        </SectionTitle>

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
                                    История авторизаций пуста
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile view (cards list) -->
            <div class="xl:hidden space-y-3">
                <div
                    v-for="(item, index) in loginHistory"
                    :key="index"
                    class="card bg-base-100 border border-base-300"
                >
                    <div class="card-body p-4 pt-3 pb-3">
                        <div class="flex items-center justify-between border-b border-base-content/10 pb-2 mb-2">
                            <div class="text-sm">
                                <div class="text-base-content/70">Устройство</div>
                                <div class="font-medium text-base-content">{{ item.device_type }}</div>
                            </div>
                            <div class="text-right">
                                <div
                                    class="badge badge-sm"
                                    :class="item.is_successful ? 'badge-success text-success-content' : 'badge-error text-error-content'"
                                >
                                    {{ getStatusText(item.is_successful) }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">IP адрес</span>
                                <span class="text-base-content font-medium">{{ item.ip_address }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Браузер</span>
                                <span class="text-base-content font-medium truncate max-w-[60%]">{{ item.browser }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">ОС</span>
                                <span class="text-base-content font-medium truncate max-w-[60%]">{{ item.operating_system }}</span>
                            </div>
<!--                            <div class="flex items-center justify-between">-->
<!--                                <span class="text-base-content/70">Местоположение</span>-->
<!--                                <span class="text-base-content font-medium truncate max-w-[60%]">{{ item.location }}</span>-->
<!--                            </div>-->
                            <div class="flex items-center justify-between">
                                <span class="text-base-content/70">Время</span>
                                <span class="text-base-content font-medium">
                                    <DateTime :data="item.created_at" :simple="true"/>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="loginHistory.length === 0" class="card bg-base-100 border border-base-300">
                    <div class="card-body p-4">
                        <div class="text-center text-base-content/60">
                            История авторизаций пуста
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
