<script setup>
import { router, usePage } from "@inertiajs/vue3";
import { ref } from "vue";

defineProps({
    title: {
        type: String,
        required: true,
    },
});

const walletStats = ref(usePage().props.walletStats);
const primaryCurrency = walletStats.value.currency.primary.toUpperCase();

router.on("success", () => {
    walletStats.value = usePage().props.walletStats;
});
</script>

<template>
    <div>
        <div class="grow lg:mt-0">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <h3 class="card-title">{{ title }}</h3>

                    <div class="pt-1 inline-block align-middle">
                        <span class="text-xl font-bold">
                            {{ walletStats.totalAvailableBalances.provider.primary }} {{ primaryCurrency }}
                        </span>
                    </div>

                    <div class="mt-0">
                        <div class="inline-flex">
                            <div class="text-sm opacity-70">
                                Вывод
                            </div>
                            <div class="text-sm ml-1.5">
                                {{ walletStats.lockedForWithdrawalBalances.provider.primary }} {{ primaryCurrency }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
