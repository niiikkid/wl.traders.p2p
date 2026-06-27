<script setup>
import { ref, watch } from 'vue';
import DateTime from '@/Components/DateTime.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    device: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const errorMessage = ref('');
const pings = ref([]);

const cellClass = (ok) => ok ? 'bg-success' : 'bg-error';

const resetState = () => {
    loading.value = false;
    errorMessage.value = '';
    pings.value = [];
};

const close = () => {
    emit('close');
};

const fetchPings = async () => {
    if (! props.device?.id) {
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await window.axios.get(route('trader.devices.pings', { device: props.device.id }));
        const items = Array.isArray(data.data?.items) ? data.data.items : [];
        pings.value = items.map((item) => ({ ok: !!item.ok, bucket: item.bucket }));
    } catch {
        errorMessage.value = 'Не удалось загрузить историю пингов.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.open, props.device?.id],
    ([isOpen, deviceId]) => {
        if (! isOpen || ! deviceId) {
            resetState();

            return;
        }

        fetchPings();
    },
);
</script>

<template>
    <dialog :open="open" class="modal">
        <div class="modal-box w-11/12 max-w-4xl p-5">
            <button
                type="button"
                class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
                @click="close"
            >
                ✕
            </button>

            <h3 class="font-bold text-base mb-1">
                История пингов
            </h3>
            <p v-if="device?.name" class="text-xs text-base-content/70 mb-4">
                {{ device.name }}
            </p>

            <div v-if="loading" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md" />
            </div>

            <div v-else-if="errorMessage" class="alert alert-error text-sm">
                {{ errorMessage }}
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div class="text-sm">Пинги за последний час, шаг 5с</div>
                    <div class="text-sm text-base-content/70 text-nowrap">
                        Последний пинг:
                        <DateTime
                            v-if="device?.latest_ping_at"
                            class="inline font-medium"
                            :data="device.latest_ping_at"
                        />
                        <span v-else class="font-medium">—</span>
                    </div>
                </div>

                <div class="flex gap-[2px] flex-wrap">
                    <template
                        v-for="(cell, idx) in (pings.length ? pings : Array.from({ length: 720 }, () => ({ ok: false })))"
                        :key="cell.bucket ?? idx"
                    >
                        <div
                            :class="['w-3 h-3 rounded-[2px]', cellClass(cell.ok)]"
                            :title="cell.ok ? 'был пинг' : 'нет пинга'"
                        />
                    </template>
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-2 mt-4">
                    <div class="text-sm text-base-content/70 text-nowrap flex items-center">
                        <span>Создан:</span>
                        <DateTime
                            v-if="device?.created_at"
                            class="justify-start font-medium ml-2"
                            :data="device.created_at"
                        />
                        <span v-else class="font-medium ml-2">—</span>
                    </div>
                    <div class="text-sm text-base-content/70 text-nowrap flex items-center">
                        <span>Подключен:</span>
                        <DateTime
                            v-if="device?.connected_at"
                            class="font-medium ml-2"
                            :data="device.connected_at"
                        />
                        <span v-else class="font-medium ml-2">нет данных</span>
                    </div>
                </div>
            </template>
        </div>

        <form method="dialog" class="modal-backdrop">
            <button type="button" @click="close">close</button>
        </form>
    </dialog>
</template>
