<script setup>
import AppTooltip from '@/Components/AppTooltip.vue';
import { useAppClipboard } from '@/composables/useAppClipboard.js';
import { computed } from 'vue';

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

const { copy, copied } = useAppClipboard()
</script>

<template>
    <div>
        <template v-if="! copyable">
            <span class="text-nowrap text-base-content">
                {{idShort}}
            </span>
        </template>
        <template v-else>
            <AppTooltip :tip="copied ? 'Скопировано!' : 'Скопировать'" placement="top" :open="copied">
                <button
                    type="button"
                    @click.prevent.stop="copy(id)"
                    class="btn btn-ghost font-normal btn-sm text-nowrap text-base-content"
                >
                    {{ idShort }}
                </button>
            </AppTooltip>
        </template>
    </div>
</template>

<style scoped>

</style>
