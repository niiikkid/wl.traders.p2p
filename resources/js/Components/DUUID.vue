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
    <span>
        <template v-if="! copyable">
            <span class="text-nowrap text-base-content">
                {{uuidShort}}
            </span>
        </template>
        <template v-else>
            <AppTooltip
                :tip="copied ? 'Скопировано!' : 'Скопировать'"
                placement="top"
                :open="copied"
                wrapper-class="inline-block text-nowrap"
            >
                <span
                    class="cursor-pointer text-base-content hover:text-primary/70"
                    role="button"
                    tabindex="0"
                    @click.prevent.stop="copy(props.uuid)"
                    @keydown.enter.prevent.stop="copy(props.uuid)"
                >
                    {{ uuidShort }}
                </span>
            </AppTooltip>
        </template>
    </span>
</template>

<style scoped>

</style>
