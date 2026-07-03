<script setup>
import AppTooltip from '@/Components/AppTooltip.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    img_path: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        default: null,
    },
});

const image_failed = ref(false);

watch(
    () => props.img_path,
    () => {
        image_failed.value = false;
    },
);

const show_image = computed(() => {
    const path = props.img_path?.trim();

    return Boolean(path) && !image_failed.value;
});

const on_image_error = () => {
    image_failed.value = true;
};
</script>

<template>
    <div class="shrink-0">
        <AppTooltip v-if="name" :tip="name" wrapper-class="block size-full rounded-lg">
            <div class="size-full overflow-hidden rounded-lg">
                <img
                    v-if="show_image"
                    :src="img_path"
                    class="size-full object-contain"
                    loading="lazy"
                    decoding="async"
                    :alt="name"
                    @error="on_image_error"
                />
                <svg
                    v-else
                    class="size-full"
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
                </svg>
            </div>
        </AppTooltip>
        <div v-else class="size-full overflow-hidden rounded-lg">
            <img
                v-if="show_image"
                :src="img_path"
                class="size-full object-contain"
                loading="lazy"
                decoding="async"
                alt=""
                @error="on_image_error"
            />
            <svg
                v-else
                class="size-full"
                aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
            </svg>
        </div>
    </div>
</template>
