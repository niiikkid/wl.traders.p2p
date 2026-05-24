import { onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export const PAYMENT_DETAIL_SCHEDULE_TICK_KEY = Symbol('paymentDetailScheduleTick');
export const PAYMENT_DETAIL_SCHEDULE_OFFSET_KEY = Symbol('paymentDetailScheduleOffset');

const TICK_MS = 30_000;

export function usePaymentDetailScheduleTableTick(paymentDetailsRef) {
    const tick = ref(0);
    const serverTimeOffsetMs = ref(0);
    let timer = null;
    let lastServerDateKey = null;

    const syncOffsetFromDetails = () => {
        const rows = paymentDetailsRef.value?.data ?? [];
        const sample = rows.find((row) => row?.schedule?.server_now);

        if (sample?.schedule?.server_now) {
            serverTimeOffsetMs.value = new Date(sample.schedule.server_now).getTime() - Date.now();
        }
    };

    const getServerTimezone = () => {
        const rows = paymentDetailsRef.value?.data ?? [];
        const sample = rows.find((row) => row?.schedule?.server_timezone);

        return sample?.schedule?.server_timezone ?? 'UTC';
    };

    const getServerNow = () => new Date(Date.now() + serverTimeOffsetMs.value);

    const getServerDateKey = () => new Intl.DateTimeFormat('en-CA', {
        timeZone: getServerTimezone(),
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(getServerNow());

    watch(paymentDetailsRef, syncOffsetFromDetails, { immediate: true, deep: true });

    onMounted(() => {
        lastServerDateKey = getServerDateKey();

        timer = setInterval(() => {
            tick.value += 1;

            const dateKey = getServerDateKey();

            if (lastServerDateKey && dateKey !== lastServerDateKey) {
                lastServerDateKey = dateKey;
                router.reload({
                    only: ['paymentDetails'],
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

    return {
        tick,
        serverTimeOffsetMs,
    };
}
