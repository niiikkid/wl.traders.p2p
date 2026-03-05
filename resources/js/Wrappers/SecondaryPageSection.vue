<script setup>
import {router} from "@inertiajs/vue3";
import GoBackButton from "@/Components/GoBackButton.vue";

const props = defineProps({
    backLink: {
        type: String,
        default: null,
    },
    title: {
        type: String,
    },
    description: {
        type: String,
        default: null
    }
});
</script>

<template>
    <div class="mx-auto space-y-4">
        <div v-if="backLink" class="mb-3">
            <GoBackButton @click="router.visit(backLink)"/>
        </div>
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body p-5 sm:p-8">
            <section>
                <header>
                    <h2 class="card-title text-lg">
                        {{ title }}
                    </h2>

                    <p v-if="description" class="mt-1 text-sm opacity-70">
                        {{ description }}
                    </p>
                </header>

                <div>
                    <slot></slot>
                </div>
            </section>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
