<script setup>
import { useClipboard } from '@vueuse/core'
import {computed} from "vue";

const props = defineProps({
    uuid: {
        type: String,
    },
    copyable: {
        type: Boolean,
        default: true
    }
});

const uuidShort = computed(() => {
    var items = props.uuid.split('-');
    if (! items.length) {
        return 'Пусто';
    }
    return items[0];
});

const { copy, copied } = useClipboard()
</script>

<template>
    <span>
        <template v-if="! copyable">
            <span class="text-nowrap text-base-content">
                {{uuidShort}}
            </span>
        </template>
        <template v-else>
            <span
                class="tooltip tooltip-top text-nowrap text-base-content cursor-pointer  hover:text-primary/70"
                :data-tip="copied ? 'Скопировано!' : 'Скопировать'"
                @click.prevent.stop="copy(props.uuid)"
            >
                {{ uuidShort }}
            </span>
        </template>
    </span>
</template>

<style scoped>

</style>
