<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { useAppClipboard } from '@/composables/useAppClipboard.js';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    deviceId: {
        type: Number,
        default: null,
    },
    deviceName: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const errorMessage = ref('');
const raw = ref(null);
const hasSnapshot = ref(false);
const updatedAt = ref(null);

const { copy, copied } = useAppClipboard();

const displayContent = computed(() => {
    if (! raw.value) {
        return { mode: 'empty', text: '' };
    }

    try {
        const parsed = JSON.parse(raw.value);

        return {
            mode: 'json',
            text: JSON.stringify(parsed, null, 2),
        };
    } catch {
        return {
            mode: 'plaintext',
            text: raw.value,
        };
    }
});

const resetState = () => {
    loading.value = false;
    errorMessage.value = '';
    raw.value = null;
    hasSnapshot.value = false;
    updatedAt.value = null;
};

const close = () => {
    emit('close');
};

const fetchSnapshot = async () => {
    if (! props.deviceId) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.get(
            route('admin.devices.connect-snapshot.show', { device: props.deviceId }),
        );
        const data = response?.data?.data ?? {};

        hasSnapshot.value = !! data.has_snapshot;
        raw.value = data.device_connect_snapshot ?? null;
        updatedAt.value = data.updated_at ?? null;
    } catch {
        errorMessage.value = 'Не удалось загрузить снимок устройства.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.open, props.deviceId],
    ([isOpen, deviceId]) => {
        if (! isOpen || ! deviceId) {
            resetState();

            return;
        }

        fetchSnapshot();
    },
);
</script>

<template>
    <dialog :open="open" class="modal">
        <div class="modal-box w-11/12 max-w-5xl p-5">
            <button
                type="button"
                class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                @click="close"
            >
                ✕
            </button>

            <h3 class="font-bold text-base mb-1">
                Снимок устройства
            </h3>
            <p v-if="deviceName" class="text-xs text-base-content/70 mb-3">
                {{ deviceName }} · ID {{ deviceId }}
            </p>

            <div v-if="loading" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md" />
            </div>

            <div v-else-if="errorMessage" class="alert alert-error text-sm">
                {{ errorMessage }}
            </div>

            <div v-else-if="! hasSnapshot" class="text-sm text-base-content/70 py-6">
                Снимок ещё не сохранён.
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <span class="text-xs text-base-content/60">
                        <template v-if="displayContent.mode === 'json'">JSON</template>
                        <template v-else>Текст</template>
                        <template v-if="updatedAt"> · обновлён {{ updatedAt }}</template>
                    </span>
                    <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        :disabled="! raw"
                        @click="copy(raw)"
                    >
                        {{ copied ? 'Скопировано' : 'Скопировать' }}
                    </button>
                </div>

                <pre class="text-xs overflow-auto max-h-[70vh] whitespace-pre-wrap break-words font-mono bg-base-200 rounded-lg p-3">{{ displayContent.text }}</pre>
            </template>
        </div>

        <form method="dialog" class="modal-backdrop">
            <button type="button" @click="close">close</button>
        </form>
    </dialog>
</template>
