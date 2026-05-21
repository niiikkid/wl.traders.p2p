<script setup>
import AppTooltip from '@/Components/AppTooltip.vue';
import { useAppClipboard } from '@/composables/useAppClipboard.js';
import { computed } from 'vue';

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

const { copy, copied } = useAppClipboard()
</script>

<template>
    <div>
        <template v-if="! copyable">
            <span class="text-nowrap text-base-content">
                {{uuidShort}}
            </span>
        </template>
        <template v-else>
            <AppTooltip :tip="copied ? 'Скопировано!' : 'Скопировать'" placement="top" :open="copied">
                <button
                    type="button"
                    @click.prevent.stop="copy(uuid)"
                    class="btn btn-ghost font-normal btn-sm text-nowrap text-base-content"
                >
                    {{ uuidShort }}
                </button>
            </AppTooltip>
        </template>
    </div>
</template>

<style scoped>

</style>
