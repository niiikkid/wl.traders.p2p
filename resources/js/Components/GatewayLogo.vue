<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    img_path: {
        type: String,
    },
    name: {
        type: String,
        default: null
    }
});

const uid = computed(() => Math.random().toString(36).substring(2, 9));
</script>

<template>
    <div>
        <div :class="{'tooltip': !!name}" :data-tip="name || ''" class="rounded-lg overflow-hidden">
            <img v-if="img_path"
                 :src="img_path"
                 class=""
            />
            <svg v-else
                 aria-hidden="true"
                 xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
            </svg>
        </div>
    </div>
</template>

<style scoped>

</style>
