<script setup>
import {Head, router, useForm, usePage} from '@inertiajs/vue3';
import {onUnmounted, ref} from "vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MainTableSection from "@/Wrappers/MainTableSection.vue";
import FiltersPanel from "@/Components/Filters/FiltersPanel.vue";
import InputFilter from "@/Components/Filters/Pertials/InputFilter.vue";
import FilterCheckbox from "@/Components/Filters/Pertials/FilterCheckbox.vue";
import DateTime from "@/Components/DateTime.vue";

const traders = ref(usePage().props.traders);
const onlineForm = useForm({
    is_online: 0,
});
const isCooldown = ref(false);
let cooldownTimer = null;

onUnmounted(() => {
    if (cooldownTimer) {
        clearTimeout(cooldownTimer);
        cooldownTimer = null;
    }
});

router.on('success', () => {
    traders.value = usePage().props.traders;
});

const toggleOnline = (trader) => {
    onlineForm
        .transform((data) => {
            data.is_online = trader.is_online;

            trader.is_online = !trader.is_online;
            data.is_online = trader.is_online;

            return data;
        })
        .patch(route('leader.traders.toggle-online', trader.id), {
            preserveScroll: true,
            onFinish: () => {
                if (cooldownTimer) {
                    clearTimeout(cooldownTimer);
                }

                isCooldown.value = true;
                cooldownTimer = setTimeout(() => {
                    isCooldown.value = false;
                    cooldownTimer = null;
                }, 300);
            },
        });
};

const openTrader = (trader) => {
    router.visit(route('leader.traders.show', trader.id), {preserveScroll: true});
};

defineOptions({layout: AuthenticatedLayout});
</script>

<template>
    <div>
        <Head title="Трейдеры" />

        <MainTableSection
            title="Трейдеры"
            :data="traders"
            info="Список ваших трейдеров с быстрым переходом в подробную информацию."
        >
            <template #table-filters>
                <FiltersPanel name="leader-traders">
                    <InputFilter
                        name="user"
                        placeholder="Поиск (почта или имя)"
                    />
                    <FilterCheckbox
                        name="online"
                        title="Онлайн"
                    />
                    <FilterCheckbox
                        name="traffic_disabled"
                        title="Трафик выключен"
                    />
                </FiltersPanel>
            </template>

            <template #body>
                <div class="relative">
                    <div class="hidden xl:block">
                        <div class="overflow-x-auto card bg-base-100 shadow">
                            <table class="table table-sm">
                                <thead class="text-xs uppercase bg-base-300">
                                    <tr>
                                        <th>ID</th>
                                        <th>Трейдер</th>
                                        <th>Реквизитов</th>
                                        <th>Статус</th>
                                        <th>Работает</th>
                                        <th>Создан</th>
                                        <th class="text-right">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="trader in traders.data" :key="trader.id" class="hover">
                                        <th class="font-medium whitespace-nowrap">{{ trader.id }}</th>
                                        <td class="whitespace-nowrap">
                                            <div class="inline-flex items-center gap-2">
                                                <img :src="'https://api.dicebear.com/9.x/' + trader.avatar_style + '/svg?seed=' + trader.avatar_uuid" class="w-10 h-10 rounded-full" alt="trader photo">
                                                <div>
                                                    <div>{{ trader.email }}</div>
                                                    <div class="text-xs text-base-content/70">{{ trader.name }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="badge badge-outline">{{ trader.payment_details_count }}</span>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <div class="inline-flex items-center gap-2">
                                                <span class="badge badge-success badge-sm" v-if="trader.is_online">Онлайн</span>
                                                <span class="badge badge-ghost badge-sm" v-else>Оффлайн</span>
                                                <span class="badge badge-error badge-sm" v-if="trader.stop_traffic">Трафик off</span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <input
                                                type="checkbox"
                                                :checked="trader.is_online"
                                                class="toggle toggle-success"
                                                :disabled="onlineForm.processing || isCooldown"
                                                @change="toggleOnline(trader)"
                                            >
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <DateTime :data="trader.created_at" :plural="true" />
                                        </td>
                                        <td class="text-right">
                                            <button class="btn btn-xs btn-primary" @click="openTrader(trader)">
                                                Открыть
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="xl:hidden space-y-2">
                        <div v-for="trader in traders.data" :key="trader.id" class="card bg-base-100 shadow-sm">
                            <div class="card-body p-4 pt-2 pb-3">
                                <div class="flex justify-between items-center border-b border-base-content/10 mb-2 pb-2">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="text-base-content/70">ID:</span>
                                        <span class="font-medium">{{ trader.id }}</span>
                                    </div>
                                    <DateTime :data="trader.created_at" :plural="true" />
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <div class="inline-flex items-center gap-2 min-w-0">
                                        <img :src="'https://api.dicebear.com/9.x/' + trader.avatar_style + '/svg?seed=' + trader.avatar_uuid" class="w-10 h-10 rounded-full" alt="trader photo">
                                        <div class="min-w-0">
                                            <div class="truncate">{{ trader.email }}</div>
                                            <div class="text-xs text-base-content/70 truncate">{{ trader.name }}</div>
                                        </div>
                                    </div>
                                    <span class="badge badge-outline">{{ trader.payment_details_count }}</span>
                                </div>

                                <div class="flex justify-between items-center mt-3">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="badge badge-success badge-sm" v-if="trader.is_online">Онлайн</span>
                                        <span class="badge badge-ghost badge-sm" v-else>Оффлайн</span>
                                        <span class="badge badge-error badge-sm" v-if="trader.stop_traffic">Трафик off</span>
                                    </div>
                                    <div class="inline-flex items-center gap-3">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-xs text-base-content/70">Работает:</span>
                                            <input
                                                type="checkbox"
                                                :checked="trader.is_online"
                                                class="toggle toggle-success toggle-sm"
                                                :disabled="onlineForm.processing || isCooldown"
                                                @change="toggleOnline(trader)"
                                            >
                                        </div>
                                        <button class="btn btn-xs btn-primary" @click="openTrader(trader)">
                                            Открыть
                                        </button>
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

