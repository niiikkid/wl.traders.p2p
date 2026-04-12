<script setup>
/**
 * Подтверждение действий только для Manual Control ACQ.
 * Не использует DaisyUI `.modal` / `.modal-open`: в daisyUI 5 они дают
 * `:root:has(.modal){ --page-scroll-bg:... }` и фон всей страницы берётся
 * из темы на корневом html (winter), а не из вложенного data-theme="dim".
 */
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useModalStore } from '@/store/modal.js';

const modal_store = useModalStore();
const { confirmModal } = storeToRefs(modal_store);

const processing = ref(false);

const close = () => {
    modal_store.closeModal('confirm');
};

const confirm = () => {
    processing.value = true;
    confirmModal.value.params.confirm?.();
    processing.value = false;
    modal_store.closeModal('confirm');
};

watch(
    () => confirmModal.value.showed,
    (showed) => {
        if (showed) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = null;
        }
    },
);

const close_on_escape = (e) => {
    if (e.key === 'Escape' && confirmModal.value.showed) {
        close();
    }
};

onMounted(() => document.addEventListener('keydown', close_on_escape));

onUnmounted(() => {
    document.removeEventListener('keydown', close_on_escape);
    document.body.style.overflow = null;
});
</script>

<template>
    <div
        v-if="confirmModal.showed"
        class="fixed inset-0 z-[999] flex items-center justify-center p-4 sm:p-6"
        @keydown.esc.prevent="close"
    >
        <div
            class="absolute inset-0 bg-neutral/50"
            aria-hidden="true"
            @click="close"
        />
        <div
            role="dialog"
            aria-modal="true"
            class="relative z-[1] max-h-[calc(100dvh-3rem)] w-full max-w-md overflow-y-auto rounded-box bg-base-100 p-6 shadow-2xl outline-none sm:max-h-[calc(100dvh-4rem)]"
        >
            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-base-content">
                    {{ confirmModal.params.title }}
                </h2>

                <p class="mt-1 text-sm text-base-content/70">
                    {{ confirmModal.params.body }}
                </p>

                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-error btn-outline"
                        @click="close"
                    >
                        {{ confirmModal.params.cancel_button_name }}
                    </button>
                    <button
                        type="button"
                        :class="{ 'btn-disabled': processing }"
                        :disabled="processing"
                        class="btn btn-sm btn-primary btn-outline"
                        @click="confirm"
                    >
                        {{ confirmModal.params.confirm_button_name }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
