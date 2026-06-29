<script setup>
import { computed } from 'vue';
import DateTime from '@/Components/DateTime.vue';
import DeviceOnlineStatus from '@/Components/Device/DeviceOnlineStatus.vue';
import DevicePingSparkline from '@/Components/Device/DevicePingSparkline.vue';
import DeviceTokenCopy from '@/Components/Trader/DeviceTokenCopy.vue';

const props = defineProps({
    device: {
        type: Object,
        required: true,
    },
    showTrader: {
        type: Boolean,
        default: false,
    },
    showPingActivity: {
        type: Boolean,
        default: true,
    },
    processingModeShortTitle: {
        type: String,
        default: null,
    },
    processingModeClass: {
        type: String,
        default: null,
    },
    processingModeTitle: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['show-pings', 'show-snapshot']);

const accentBarClass = computed(() => {
    if (! props.device.is_connected) {
        return 'bg-base-content/20';
    }

    if (props.device.is_online) {
        return 'bg-success';
    }

    return 'bg-warning';
});

const phoneShellClass = computed(() => {
    if (! props.device.is_connected) {
        return 'bg-base-200 text-base-content/40 ring-base-content/10';
    }

    if (props.device.is_online) {
        return 'bg-success/15 text-success ring-success/25';
    }

    return 'bg-warning/15 text-warning ring-warning/25';
});
</script>

<template>
    <article class="card bg-base-100 border border-base-content/10 shadow-md overflow-hidden">
        <div class="h-1 w-full" :class="accentBarClass" />

        <div class="card-body gap-4 p-4 sm:p-5">
            <div class="flex items-start gap-4">
                <div class="indicator shrink-0">
                    <span
                        v-if="device.is_online"
                        class="indicator-item indicator-start badge badge-xs badge-success border-2 border-base-100 px-1.5"
                    >
                        live
                    </span>
                    <div
                        class="flex size-14 items-center justify-center rounded-2xl ring-1"
                        :class="phoneShellClass"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-7"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path d="M17 1H7a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2m0 18H7V5h10zm-1-6h-3V8h-2v5H8l4 4z" />
                        </svg>
                    </div>
                </div>

                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-base text-base-content truncate">
                                {{ device.name }}
                            </h3>
                            <p
                                v-if="device.hardware_title"
                                class="text-sm text-base-content/70 truncate"
                            >
                                {{ device.hardware_title }}
                            </p>
                            <p
                                v-else
                                class="text-sm text-base-content/50"
                            >
                                Приложение ещё не подключено
                            </p>
                            <p
                                v-if="showTrader && device.user?.email"
                                class="text-xs text-base-content/50 truncate mt-0.5"
                            >
                                {{ device.user.email }}
                            </p>
                        </div>

                        <DeviceOnlineStatus
                            :is-connected="!!device.is_connected"
                            :is-online="!!device.is_online"
                            size="sm"
                        />
                    </div>

                    <div
                        v-if="processingModeShortTitle"
                        class="flex items-center gap-2"
                    >
                        <span class="text-xs text-base-content/60">Режим СМС</span>
                        <span
                            :class="['badge badge-xs', processingModeClass]"
                            :title="processingModeTitle"
                        >
                            {{ processingModeShortTitle }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="device.is_connected"
                class="stats stats-vertical sm:stats-horizontal w-full rounded-xl border border-base-content/10 bg-base-200/40 shadow-none"
            >
                <div class="stat place-items-start px-4 py-3">
                    <div class="stat-title text-xs">Платформа</div>
                    <div class="stat-value text-sm font-medium">
                        {{ device.android_label ?? '—' }}
                    </div>
                </div>
                <div class="stat place-items-start px-4 py-3">
                    <div class="stat-title text-xs">Последний пинг</div>
                    <div class="stat-value text-sm font-medium">
                        <DateTime
                            v-if="device.latest_ping_at"
                            class="justify-start"
                            :data="device.latest_ping_at"
                            :plural="true"
                        />
                        <span v-else class="text-base-content/50">нет данных</span>
                    </div>
                </div>
                <div class="stat place-items-start px-4 py-3">
                    <div class="stat-title text-xs">Подключено</div>
                    <div class="stat-value text-sm font-medium">
                        <DateTime
                            v-if="device.connected_at"
                            class="justify-start"
                            :data="device.connected_at"
                        />
                        <span v-else class="text-base-content/50">—</span>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border border-dashed border-base-content/15 bg-base-200/30 p-4 text-sm text-base-content/70"
            >
                Установите APK на телефон и введите токен ниже — после подключения здесь появятся модель, версия Android и статус пингов.
            </div>

            <div>
                <div class="mb-1.5 text-xs font-medium text-base-content/60">
                    Токен устройства
                </div>
                <DeviceTokenCopy :token="device.token" />
            </div>

            <DevicePingSparkline
                v-if="device.is_connected && showPingActivity"
                :device-id="device.id"
                :minutes="10"
            />

            <div class="card-actions justify-end gap-2 pt-1">
                <button
                    v-if="device.has_connect_snapshot"
                    type="button"
                    class="btn btn-ghost btn-sm"
                    @click="emit('show-snapshot', device)"
                >
                    Снимок
                </button>
                <button
                    v-if="device.is_connected && showPingActivity"
                    type="button"
                    class="btn btn-outline btn-sm gap-1.5"
                    @click="emit('show-pings', device)"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="size-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2" />
                    </svg>
                    История пингов
                </button>
            </div>
        </div>
    </article>
</template>
