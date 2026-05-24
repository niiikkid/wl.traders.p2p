import { onBeforeUnmount, onMounted, provide, ref, unref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export const PAYMENT_DETAIL_SCHEDULE_TICK_KEY = Symbol('paymentDetailScheduleTick');
export const PAYMENT_DETAIL_SCHEDULE_OFFSET_KEY = Symbol('paymentDetailScheduleOffset');
export const PAYMENT_DETAIL_SCHEDULE_TIMEZONE_KEY = Symbol('paymentDetailScheduleTimezone');

const TICK_MS = 30_000;

export function usePaymentDetailScheduleTableTick(paymentDetailsRef, scheduleServerClockRef = null) {
    const tick = ref(0);
    const serverTimeOffsetMs = ref(0);
    const serverTimezone = ref('UTC');
    let timer = null;
    let lastServerDateKey = null;

    const syncOffsetFromDetails = () => {
        const rows = paymentDetailsRef.value?.data ?? [];
        const sample = rows.find((row) => row?.schedule?.server_now);
        const fallback = unref(scheduleServerClockRef);

        if (sample?.schedule?.server_now) {
            serverTimeOffsetMs.value = new Date(sample.schedule.server_now).getTime() - Date.now();
            serverTimezone.value = sample.schedule.server_timezone ?? 'UTC';

            return;
        }

        if (fallback?.server_now) {
            serverTimeOffsetMs.value = new Date(fallback.server_now).getTime() - Date.now();
            serverTimezone.value = fallback.server_timezone ?? 'UTC';
        }
    };

    const getServerNow = () => new Date(Date.now() + serverTimeOffsetMs.value);

    const getServerDateKey = () => new Intl.DateTimeFormat('en-CA', {
        timeZone: serverTimezone.value,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(getServerNow());

    watch(paymentDetailsRef, syncOffsetFromDetails, { immediate: true, deep: true });

    if (scheduleServerClockRef) {
        watch(scheduleServerClockRef, syncOffsetFromDetails, { deep: true });
    }

    onMounted(() => {
        lastServerDateKey = getServerDateKey();

        timer = setInterval(() => {
            tick.value += 1;

            const dateKey = getServerDateKey();

            if (lastServerDateKey && dateKey !== lastServerDateKey) {
                lastServerDateKey = dateKey;

                const reloadProps = scheduleServerClockRef
                    ? ['paymentDetails', 'scheduleServerClock', 'scheduleSummary']
                    : ['paymentDetails', 'scheduleSummary'];

                router.reload({
                    only: reloadProps,
                    preserveScroll: true,
                });

                return;
            }

            lastServerDateKey = dateKey;
        }, TICK_MS);
    });

    onBeforeUnmount(() => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    });

    provide(PAYMENT_DETAIL_SCHEDULE_TICK_KEY, tick);
    provide(PAYMENT_DETAIL_SCHEDULE_OFFSET_KEY, serverTimeOffsetMs);
    provide(PAYMENT_DETAIL_SCHEDULE_TIMEZONE_KEY, serverTimezone);

    return {
        tick,
        serverTimeOffsetMs,
        serverTimezone,
    };
}
