<script setup>
import {computed} from "vue";

const model = defineModel({
    required: true,
});

const props = defineProps({
    title: {
        type: String,
        default: 'Нажмите, чтобы загрузить файл',
    },
    description: {
        type: String,
        default: null,
    },
});

const fileName = computed(() => {
    if (! model.value?.name) {
        return null;
    }

    var split = model.value.name.split('.');
    var filename = split[0];
    var extension = split[1];

    if (filename.length > 10) {
        filename = filename.substring(0, 5) + '...' + filename.substring(5, 10);
    }

    return filename + '.' + extension;
})
</script>

<template>
    <div>
        <label for="dropzone-file" class="flex flex-col items-center justify-center w-full h-20 border-2 border-dashed rounded-xl cursor-pointer bg-base-200 hover:bg-base-300 border-base-300">
            <div class="flex items-center justify-center px-6 pt-6 pb-6">
                <svg class="w-12 h-12 sm:w-10 sm:h-10 sm:mr-4 text-base-content/60" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                </svg>
                <div>
                    <p v-if="!model" class="text-xs sm:text-sm text-base-content/70 text-center">{{ title }}</p>
                    <p v-if="!model && description" class="text-xs text-base-content/60 text-center">{{ description }}</p>
                    <p v-else class="text-sm text-base-content/70">{{ fileName }}</p>
                </div>
            </div>
            <input id="dropzone-file" type="file" @input="model = $event.target.files[0]" class="hidden" />
        </label>
    </div>
</template>

<style scoped>

</style>
