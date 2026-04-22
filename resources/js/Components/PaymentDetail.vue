<script setup>
import {computed} from "vue";
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
    showProcessingIndicator: {
        type: Boolean,
        default: false
    },
    usesManualProcessing: {
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

const processingIndicatorTitle = computed(() => {
    return props.usesManualProcessing
        ? 'Ручная обработка'
        : 'Автоматическая обработка';
});

const processingIndicatorIconClass = computed(() => {
    return props.usesManualProcessing ? 'text-accent' : 'text-secondary';
});
</script>

<template>
    <div>
        <template v-if="['nspk', 'e-com'].includes(type)">
            <div class="flex items-center gap-2">
                <a
                    :href="detail"
                    target="_blank"
                    rel="noreferrer"
                    class="text-base-content no-underline hover:text-primary"
                >
                    {{ type === 'nspk' ? 'NSPK ссылка' : 'E-COM ссылка' }}
                </a>
                <div
                    v-if="showProcessingIndicator"
                    class="tooltip tooltip-top"
                    :data-tip="processingIndicatorTitle"
                >
                    <span
                        class="inline-grid size-5 shrink-0 place-items-center"
                        :class="processingIndicatorIconClass"
                        :aria-label="processingIndicatorTitle"
                    >
                        <svg
                            v-if="usesManualProcessing"
                            class="size-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M6.9 11.4444V14.2222M6.9 11.4444V4.77778C6.9 3.8573 7.66112 3.11111 8.6 3.11111C9.53888 3.11111 10.3 3.8573 10.3 4.77778M6.9 11.4444C6.9 10.524 6.13888 9.77778 5.2 9.77778C4.26112 9.77778 3.5 10.524 3.5 11.4444V13.6667C3.5 18.269 7.30558 22 12 22C16.6944 22 20.5 18.269 20.5 13.6667V8.11111C20.5 7.19064 19.7389 6.44444 18.8 6.44444C17.8611 6.44444 17.1 7.19064 17.1 8.11111M10.3 4.77778V10.8889M10.3 4.77778V3.66667C10.3 2.74619 11.0611 2 12 2C12.9389 2 13.7 2.74619 13.7 3.66667V4.77778M13.7 4.77778V10.8889M13.7 4.77778C13.7 3.8573 14.4611 3.11111 15.4 3.11111C16.3389 3.11111 17.1 3.8573 17.1 4.77778V8.11111M17.1 8.11111V10.8889" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <svg
                            v-else
                            class="size-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M14 2C14 2.74028 13.5978 3.38663 13 3.73244V4H20C21.6569 4 23 5.34315 23 7V19C23 20.6569 21.6569 22 20 22H4C2.34315 22 1 20.6569 1 19V7C1 5.34315 2.34315 4 4 4H11V3.73244C10.4022 3.38663 10 2.74028 10 2C10 0.895431 10.8954 0 12 0C13.1046 0 14 0.895431 14 2ZM4 6H11H13H20C20.5523 6 21 6.44772 21 7V19C21 19.5523 20.5523 20 20 20H4C3.44772 20 3 19.5523 3 19V7C3 6.44772 3.44772 6 4 6ZM15 11.5C15 10.6716 15.6716 10 16.5 10C17.3284 10 18 10.6716 18 11.5C18 12.3284 17.3284 13 16.5 13C15.6716 13 15 12.3284 15 11.5ZM16.5 8C14.567 8 13 9.567 13 11.5C13 13.433 14.567 15 16.5 15C18.433 15 20 13.433 20 11.5C20 9.567 18.433 8 16.5 8ZM7.5 10C6.67157 10 6 10.6716 6 11.5C6 12.3284 6.67157 13 7.5 13C8.32843 13 9 12.3284 9 11.5C9 10.6716 8.32843 10 7.5 10ZM4 11.5C4 9.567 5.567 8 7.5 8C9.433 8 11 9.567 11 11.5C11 13.433 9.433 15 7.5 15C5.567 15 4 13.433 4 11.5ZM10.8944 16.5528C10.6474 16.0588 10.0468 15.8586 9.55279 16.1056C9.05881 16.3526 8.85858 16.9532 9.10557 17.4472C9.68052 18.5971 10.9822 19 12 19C13.0178 19 14.3195 18.5971 14.8944 17.4472C15.1414 16.9532 14.9412 16.3526 14.4472 16.1056C13.9532 15.8586 13.3526 16.0588 13.1056 16.5528C13.0139 16.7362 12.6488 17 12 17C11.3512 17 10.9861 16.7362 10.8944 16.5528Z" fill="currentColor"/>
                        </svg>
                    </span>
                </div>
            </div>
            <div v-if="name" class="text-nowrap text-xs text-base-content/70">
                {{ name }}
            </div>
        </template>
        <template v-else-if="copyable">
            <div class="flex items-center gap-2">
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
                        <template v-if="['account_number', 'iban_uah'].includes(type)">
                            <template v-if="short">
                                <template v-if="type === 'account_number'">
                                    ***{{ detail.substring(detail.length - 6) }}
                                </template>
                                <template v-else>
                                    {{ detail.substring(0, 6) }}...{{ detail.substring(detail.length - 4) }}
                                </template>
                            </template>
                            <template v-else>
                                {{ detail }}
                            </template>
                        </template>
                    </a>
                </div>
                <div
                    v-if="showProcessingIndicator"
                    class="tooltip tooltip-top"
                    :data-tip="processingIndicatorTitle"
                >
                    <span
                        class="inline-grid size-5 shrink-0 place-items-center"
                        :class="processingIndicatorIconClass"
                        :aria-label="processingIndicatorTitle"
                    >
                        <svg
                            v-if="usesManualProcessing"
                            class="size-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M6.9 11.4444V14.2222M6.9 11.4444V4.77778C6.9 3.8573 7.66112 3.11111 8.6 3.11111C9.53888 3.11111 10.3 3.8573 10.3 4.77778M6.9 11.4444C6.9 10.524 6.13888 9.77778 5.2 9.77778C4.26112 9.77778 3.5 10.524 3.5 11.4444V13.6667C3.5 18.269 7.30558 22 12 22C16.6944 22 20.5 18.269 20.5 13.6667V8.11111C20.5 7.19064 19.7389 6.44444 18.8 6.44444C17.8611 6.44444 17.1 7.19064 17.1 8.11111M10.3 4.77778V10.8889M10.3 4.77778V3.66667C10.3 2.74619 11.0611 2 12 2C12.9389 2 13.7 2.74619 13.7 3.66667V4.77778M13.7 4.77778V10.8889M13.7 4.77778C13.7 3.8573 14.4611 3.11111 15.4 3.11111C16.3389 3.11111 17.1 3.8573 17.1 4.77778V8.11111M17.1 8.11111V10.8889" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <svg
                            v-else
                            class="size-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M14 2C14 2.74028 13.5978 3.38663 13 3.73244V4H20C21.6569 4 23 5.34315 23 7V19C23 20.6569 21.6569 22 20 22H4C2.34315 22 1 20.6569 1 19V7C1 5.34315 2.34315 4 4 4H11V3.73244C10.4022 3.38663 10 2.74028 10 2C10 0.895431 10.8954 0 12 0C13.1046 0 14 0.895431 14 2ZM4 6H11H13H20C20.5523 6 21 6.44772 21 7V19C21 19.5523 20.5523 20 20 20H4C3.44772 20 3 19.5523 3 19V7C3 6.44772 3.44772 6 4 6ZM15 11.5C15 10.6716 15.6716 10 16.5 10C17.3284 10 18 10.6716 18 11.5C18 12.3284 17.3284 13 16.5 13C15.6716 13 15 12.3284 15 11.5ZM16.5 8C14.567 8 13 9.567 13 11.5C13 13.433 14.567 15 16.5 15C18.433 15 20 13.433 20 11.5C20 9.567 18.433 8 16.5 8ZM7.5 10C6.67157 10 6 10.6716 6 11.5C6 12.3284 6.67157 13 7.5 13C8.32843 13 9 12.3284 9 11.5C9 10.6716 8.32843 10 7.5 10ZM4 11.5C4 9.567 5.567 8 7.5 8C9.433 8 11 9.567 11 11.5C11 13.433 9.433 15 7.5 15C5.567 15 4 13.433 4 11.5ZM10.8944 16.5528C10.6474 16.0588 10.0468 15.8586 9.55279 16.1056C9.05881 16.3526 8.85858 16.9532 9.10557 17.4472C9.68052 18.5971 10.9822 19 12 19C13.0178 19 14.3195 18.5971 14.8944 17.4472C15.1414 16.9532 14.9412 16.3526 14.4472 16.1056C13.9532 15.8586 13.3526 16.0588 13.1056 16.5528C13.0139 16.7362 12.6488 17 12 17C11.3512 17 10.9861 16.7362 10.8944 16.5528Z" fill="currentColor"/>
                        </svg>
                    </span>
                </div>
            </div>
            <div v-if="name" class="w-40 truncate text-nowrap text-xs ml-2 text-base-content/70">
                {{ name }}
            </div>
        </template>
        <template v-else>
            <div class="flex items-center gap-2">
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
                    <template v-if="['account_number', 'iban_uah'].includes(type)">
                        <template v-if="short">
                            <template v-if="type === 'account_number'">
                                ***{{ detail.substring(detail.length - 6) }}
                            </template>
                            <template v-else>
                                {{ detail.substring(0, 6) }}...{{ detail.substring(detail.length - 4) }}
                            </template>
                        </template>
                        <template v-else>
                            {{ detail }}
                        </template>
                    </template>
                </span>
                <div
                    v-if="showProcessingIndicator"
                    class="tooltip tooltip-top"
                    :data-tip="processingIndicatorTitle"
                >
                    <span
                        class="inline-grid size-5 shrink-0 place-items-center"
                        :class="processingIndicatorIconClass"
                        :aria-label="processingIndicatorTitle"
                    >
                        <svg
                            v-if="usesManualProcessing"
                            class="size-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M6.9 11.4444V14.2222M6.9 11.4444V4.77778C6.9 3.8573 7.66112 3.11111 8.6 3.11111C9.53888 3.11111 10.3 3.8573 10.3 4.77778M6.9 11.4444C6.9 10.524 6.13888 9.77778 5.2 9.77778C4.26112 9.77778 3.5 10.524 3.5 11.4444V13.6667C3.5 18.269 7.30558 22 12 22C16.6944 22 20.5 18.269 20.5 13.6667V8.11111C20.5 7.19064 19.7389 6.44444 18.8 6.44444C17.8611 6.44444 17.1 7.19064 17.1 8.11111M10.3 4.77778V10.8889M10.3 4.77778V3.66667C10.3 2.74619 11.0611 2 12 2C12.9389 2 13.7 2.74619 13.7 3.66667V4.77778M13.7 4.77778V10.8889M13.7 4.77778C13.7 3.8573 14.4611 3.11111 15.4 3.11111C16.3389 3.11111 17.1 3.8573 17.1 4.77778V8.11111M17.1 8.11111V10.8889" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <svg
                            v-else
                            class="size-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M14 2C14 2.74028 13.5978 3.38663 13 3.73244V4H20C21.6569 4 23 5.34315 23 7V19C23 20.6569 21.6569 22 20 22H4C2.34315 22 1 20.6569 1 19V7C1 5.34315 2.34315 4 4 4H11V3.73244C10.4022 3.38663 10 2.74028 10 2C10 0.895431 10.8954 0 12 0C13.1046 0 14 0.895431 14 2ZM4 6H11H13H20C20.5523 6 21 6.44772 21 7V19C21 19.5523 20.5523 20 20 20H4C3.44772 20 3 19.5523 3 19V7C3 6.44772 3.44772 6 4 6ZM15 11.5C15 10.6716 15.6716 10 16.5 10C17.3284 10 18 10.6716 18 11.5C18 12.3284 17.3284 13 16.5 13C15.6716 13 15 12.3284 15 11.5ZM16.5 8C14.567 8 13 9.567 13 11.5C13 13.433 14.567 15 16.5 15C18.433 15 20 13.433 20 11.5C20 9.567 18.433 8 16.5 8ZM7.5 10C6.67157 10 6 10.6716 6 11.5C6 12.3284 6.67157 13 7.5 13C8.32843 13 9 12.3284 9 11.5C9 10.6716 8.32843 10 7.5 10ZM4 11.5C4 9.567 5.567 8 7.5 8C9.433 8 11 9.567 11 11.5C11 13.433 9.433 15 7.5 15C5.567 15 4 13.433 4 11.5ZM10.8944 16.5528C10.6474 16.0588 10.0468 15.8586 9.55279 16.1056C9.05881 16.3526 8.85858 16.9532 9.10557 17.4472C9.68052 18.5971 10.9822 19 12 19C13.0178 19 14.3195 18.5971 14.8944 17.4472C15.1414 16.9532 14.9412 16.3526 14.4472 16.1056C13.9532 15.8586 13.3526 16.0588 13.1056 16.5528C13.0139 16.7362 12.6488 17 12 17C11.3512 17 10.9861 16.7362 10.8944 16.5528Z" fill="currentColor"/>
                        </svg>
                    </span>
                </div>
            </div>
            <div v-if="name" class="text-nowrap text-xs text-base-content/70">
                {{ name }}
            </div>
        </template>
    </div>
</template>

<style scoped>

</style>
