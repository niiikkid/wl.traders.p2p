<script setup>
import {computed, ref} from "vue";
import { useClipboard } from '@vueuse/core'

const props = defineProps({
    detail: {
        type: String,
    },
    type: {
        type: String,
    },
    name: {
        type: String,
        default: null
    },
    copyable: {
        type: Boolean,
        default: true
    },
    short: {
        type: Boolean,
        default: false
    },
});
const { text, copy, copied, isSupported } = useClipboard()

const isPhoneType = computed(() => ['phone', 'mobile_commerce'].includes(props.type));

const phone = computed(() => {
    if (!isPhoneType.value) {
        return null;
    }

    let x = props.detail.replace(/\D/g, '').match(/(\d{1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);

    return  !x[2] ? x[1] : '+' + x[1] + ' (' + x[2] + ') ' + x[3] + '-' + x[4] + '-' + x[5];
})
</script>

<template>
    <div>
        <template v-if="type === 'nspk'">
            <div class="flex items-center gap-2">
                <a
                    :href="detail"
                    target="_blank"
                    rel="noreferrer"
                    class="text-base-content no-underline hover:text-primary"
                >
                    NSPK ссылка
                </a>
            </div>
            <div v-if="name" class="text-nowrap text-xs text-base-content/70">
                {{ name }}
            </div>
        </template>
        <template v-else-if="copyable">
            <div class="tooltip tooltip-top" :data-tip="copied ? 'Скопировано!' : 'Скопировать'">
            <a
                href="#"
                @click.prevent="copy(detail)"
                class="btn btn-ghost btn-xs text-nowrap"
                :class="name ? 'text-base-content' : ''"
            >
                <template v-if="type === 'card'">
                    <template v-if="short">
                        {{ detail.substring(0, 4) }}**{{ detail.substring(detail.length - 4) }}
                    </template>
                    <template v-else>
                        {{ detail.match(/.{1,4}/g).join(' ') }}
                    </template>
                </template>
                <template v-if="isPhoneType">
                    <template v-if="short">
                        {{ phone.substring(0,2) }} **** {{ phone.substring(phone.length - 5) }}
                    </template>
                    <template v-else>
                        {{ phone }}
                    </template>
                </template>
                <template v-if="type === 'account_number'">
                    <template v-if="short">
                        ***{{ detail.substring(detail.length - 6) }}
                    </template>
                    <template v-else>
                        {{ detail }}
                    </template>
                </template>
            </a>
            </div>
            <div v-if="name" class="w-40 truncate text-nowrap text-xs ml-2 text-base-content/70">
                {{ name }}
            </div>
        </template>
        <template v-else>
            <span class="text-nowrap" :class="name ? 'text-base-content' : ''">
                <template v-if="type === 'card'">
                    <template v-if="short">
                        {{ detail.substring(0, 4) }}**{{ detail.substring(detail.length - 4) }}
                    </template>
                    <template v-else>
                        {{ detail.match(/.{1,4}/g).join(' ') }}
                    </template>
                </template>
                <template v-if="isPhoneType">
                    <template v-if="short">
                        **** {{ phone.substring(phone.length - 4) }}
                    </template>
                    <template v-else>
                        {{ phone }}
                    </template>
                </template>
                <template v-if="type === 'account_number'">
                    <template v-if="short">
                        ***{{ detail.substring(detail.length - 6) }}
                    </template>
                    <template v-else>
                        {{ detail }}
                    </template>
                </template>
            </span>
            <div v-if="name" class="text-nowrap text-xs text-base-content/70">
                {{ name }}
            </div>
        </template>
    </div>
</template>

<style scoped>

</style>
