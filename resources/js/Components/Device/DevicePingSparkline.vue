<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    deviceId: {
        type: Number,
        required: true,
    },
    minutes: {
        type: Number,
        default: 10,
    },
    refreshIntervalMs: {
        type: Number,
        default: 30_000,
    },
});

const loading = ref(true);
const pings = ref([]);
let refreshTimer = null;

const cellClass = (ok) => (ok ? 'bg-success' : 'bg-base-content/15');

const fetchPings = async () => {
    if (! props.deviceId) {
        return;
    }

    try {
        const { data } = await window.axios.get(
            route('trader.devices.pings', { device: props.deviceId }),
            { params: { minutes: props.minutes } },
        );
        const items = Array.isArray(data.data?.items) ? data.data.items : [];
        pings.value = items.map((item) => ({ ok: !!item.ok, bucket: item.bucket }));
    } catch {
        pings.value = [];
    } finally {
        loading.value = false;
    }
};

const startRefresh = () => {
    refreshTimer = setInterval(fetchPings, props.refreshIntervalMs);
};

onMounted(() => {
    fetchPings();
    startRefresh();
});

onUnmounted(() => {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
});

watch(
    () => props.deviceId,
    () => {
        loading.value = true;
        fetchPings();
    },
);
</script>

<template>
    <div class="rounded-xl border border-base-content/10 bg-base-200/40 p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-base-content/70">
                Активность за {{ minutes }} мин
            </span>
            <span v-if="loading" class="loading loading-spinner loading-xs text-base-content/50" />
        </div>

        <div class="flex flex-wrap gap-[2px]">
            <template
                v-for="(cell, idx) in pings"
                :key="cell.bucket ?? idx"
            >
                <div
                    :class="['h-2.5 w-1.5 rounded-[1px]', cellClass(cell.ok)]"
                    :title="cell.ok ? 'был пинг' : 'нет пинга'"
                />
            </template>
            <template v-if="!loading && !pings.length">
                <div
                    v-for="idx in minutes * 12"
                    :key="idx"
                    class="h-2.5 w-1.5 rounded-[1px] bg-base-content/10"
                />
            </template>
        </div>
    </div>
</template>
