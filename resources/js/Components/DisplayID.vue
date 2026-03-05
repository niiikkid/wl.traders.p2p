<script setup>
import { useClipboard } from '@vueuse/core'
import {computed} from "vue";

const props = defineProps({
    id: {
        type: String,
    },
    copyable: {
        type: Boolean,
        default: true
    }
});

const idShort = computed(() => {
    if (!props.id) {
        return 'Пусто';
    }

    if (props.id.length > 8) {
        const last = props.id.substring(props.id.length - 8);
        return `${last}`;
    }

    return props.id;
});

const { copy, copied } = useClipboard()
</script>

<template>
    <div>
        <template v-if="! copyable">
            <span class="text-nowrap text-base-content">
                {{idShort}}
            </span>
        </template>
        <template v-else>
            <div class="tooltip tooltip-top" :data-tip="copied ? 'Скопировано!' : 'Скопировать'">
                <button
                    type="button"
                    @click.prevent.stop="copy(id)"
                    class="btn btn-ghost font-normal btn-sm text-nowrap text-base-content"
                >
                    {{ idShort }}
                </button>
            </div>
        </template>
    </div>
</template>

<style scoped>

</style>
