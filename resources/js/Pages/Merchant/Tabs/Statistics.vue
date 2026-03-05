<script setup>
import {computed} from "vue";
import {usePage} from "@inertiajs/vue3";

const props = defineProps({
    statistics: {
        type: Object,
        default: null,
    },
});

const page = usePage();

const statistics = computed(() => props.statistics ?? (page?.props?.statistics ?? {}));
</script>

<template>
    <div>
        <section>
            <div class="mx-auto text-center">
                <div class="xl:stats-horizontal stats-vertical grid xl:grid-cols-4 md:grid-cols-2 sm:grid-cols-1 bg-base-100 shadow rounded-box max-w-full mx-auto">
                    <div class="stat">
                        <div class="stat-value text-3xl md:text-3xl mb-2">
                            {{ statistics.today_profit }}
                            <span class="text-xl text-base-content/60">
                                {{ statistics.currency.toUpperCase() }}
                            </span>
                        </div>
                        <div class="text-base-content/70 mb-1">Доход за сегодня</div>
                        <div class="stat-actions">
                            <div class="badge badge-ghost gap-1 text-xs">
                                <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.032 12 1.984 1.984 4.96-4.96m4.55 5.272.893-.893a1.984 1.984 0 0 0 0-2.806l-.893-.893a1.984 1.984 0 0 1-.581-1.403V7.04a1.984 1.984 0 0 0-1.984-1.984h-1.262a1.983 1.983 0 0 1-1.403-.581l-.893-.893a1.984 1.984 0 0 0-2.806 0l-.893.893a1.984 1.984 0 0 1-1.403.581H7.04A1.984 1.984 0 0 0 5.055 7.04v1.262c0 .527-.209 1.031-.581 1.403l-.893.893a1.984 1.984 0 0 0 0 2.806l.893.893c.372.372.581.876.581 1.403v1.262a1.984 1.984 0 0 0 1.984 1.984h1.262c.527 0 1.031.209 1.403.581l.893.893a1.984 1.984 0 0 0 2.806 0l.893-.893a1.985 1.985 0 0 1 1.403-.581h1.262a1.984 1.984 0 0 0 1.984-1.984V15.7c0-.527.209-1.031.581-1.403Z"/>
                                </svg>
                                Платежей {{ statistics.today_orders_count }}
                            </div>
                        </div>
                    </div>
                    <div class="stat">
                        <div class="stat-value text-3xl md:text-3xl mb-2">
                            {{ statistics.yesterday_profit }}
                            <span class="text-xl text-base-content/60">
                                {{ statistics.currency.toUpperCase() }}
                            </span>
                        </div>
                        <div class="text-base-content/70 mb-1">Доход за вчера</div>
                        <div class="stat-actions">
                            <div class="badge badge-ghost gap-1 text-xs">
                                <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.032 12 1.984 1.984 4.96-4.96m4.55 5.272.893-.893a1.984 1.984 0 0 0 0-2.806l-.893-.893a1.984 1.984 0 0 1-.581-1.403V7.04a1.984 1.984 0 0 0-1.984-1.984h-1.262a1.983 1.983 0 0 1-1.403-.581l-.893-.893a1.984 1.984 0 0 0-2.806 0l-.893.893a1.984 1.984 0 0 1-1.403.581H7.04A1.984 1.984 0 0 0 5.055 7.04v1.262c0 .527-.209 1.031-.581 1.403l-.893.893a1.984 1.984 0 0 0 0 2.806l.893.893c.372.372.581.876.581 1.403v1.262a1.984 1.984 0 0 0 1.984 1.984h1.262c.527 0 1.031.209 1.403.581l.893.893a1.984 1.984 0 0 0 2.806 0l.893-.893a1.985 1.985 0 0 1 1.403-.581h1.262a1.984 1.984 0 0 0 1.984-1.984V15.7c0-.527.209-1.031.581-1.403Z"/>
                                </svg>
                                Платежей {{ statistics.yesterday_orders_count }}
                            </div>
                        </div>
                    </div>
                    <div class="stat">
                        <div class="stat-value text-3xl md:text-3xl mb-2">
                            {{ statistics.month_profit }}
                            <span class="text-xl text-base-content/60">
                                {{ statistics.currency.toUpperCase() }}
                            </span>
                        </div>
                        <div class="text-base-content/70 mb-1">Доход за месяц</div>
                        <div class="stat-actions">
                            <div class="badge badge-ghost gap-1 text-xs">
                                <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.032 12 1.984 1.984 4.96-4.96m4.55 5.272.893-.893a1.984 1.984 0 0 0 0-2.806l-.893-.893a1.984 1.984 0 0 1-.581-1.403V7.04a1.984 1.984 0 0 0-1.984-1.984h-1.262a1.983 1.983 0 0 1-1.403-.581l-.893-.893a1.984 1.984 0 0 0-2.806 0l-.893.893a1.984 1.984 0 0 1-1.403.581H7.04A1.984 1.984 0 0 0 5.055 7.04v1.262c0 .527-.209 1.031-.581 1.403l-.893.893a1.984 1.984 0 0 0 0 2.806l.893.893c.372.372.581.876.581 1.403v1.262a1.984 1.984 0 0 0 1.984 1.984h1.262c.527 0 1.031.209 1.403.581l.893.893a1.984 1.984 0 0 0 2.806 0l.893-.893a1.985 1.985 0 0 1 1.403-.581h1.262a1.984 1.984 0 0 0 1.984-1.984V15.7c0-.527.209-1.031.581-1.403Z"/>
                                </svg>
                                Платежей {{ statistics.month_orders_count }}
                            </div>
                        </div>
                    </div>
                    <div class="stat">
                        <div class="stat-value text-3xl md:text-3xl mb-2">
                            {{ statistics.total_profit }}
                            <span class="text-xl text-base-content/60">
                                {{ statistics.currency.toUpperCase() }}
                            </span>
                        </div>
                        <div class="text-base-content/70 mb-1">Доход за все время</div>
                        <div class="stat-actions">
                            <div class="badge badge-ghost gap-1 text-xs">
                                <svg class="w-4 h-4 text-primary" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.032 12 1.984 1.984 4.96-4.96m4.55 5.272.893-.893a1.984 1.984 0 0 0 0-2.806l-.893-.893a1.984 1.984 0 0 1-.581-1.403V7.04a1.984 1.984 0 0 0-1.984-1.984h-1.262a1.983 1.983 0 0 1-1.403-.581l-.893-.893a1.984 1.984 0 0 0-2.806 0l-.893.893a1.984 1.984 0 0 1-1.403.581H7.04A1.984 1.984 0 0 0 5.055 7.04v1.262c0 .527-.209 1.031-.581 1.403l-.893.893a1.984 1.984 0 0 0 0 2.806l.893.893c.372.372.581.876.581 1.403v1.262a1.984 1.984 0 0 0 1.984 1.984h1.262c.527 0 1.031.209 1.403.581l.893.893a1.984 1.984 0 0 0 2.806 0l.893-.893a1.985 1.985 0 0 1 1.403-.581h1.262a1.984 1.984 0 0 0 1.984-1.984V15.7c0-.527.209-1.031.581-1.403Z"/>
                                </svg>
                                Платежей {{ statistics.total_orders_count }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>

</style>
