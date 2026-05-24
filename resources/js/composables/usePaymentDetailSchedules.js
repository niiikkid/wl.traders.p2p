import { ref } from 'vue';

const schedules = ref([]);
const serverTimezone = ref(null);
const serverNow = ref(null);
const loading = ref(false);
const loaded = ref(false);

const buildDefaultWeekdayIntervals = () => {
    const intervals = [];

    for (let day = 1; day <= 7; day += 1) {
        intervals.push({
            day_of_week: day,
            starts_at: '09:00',
            ends_at: '19:00',
        });
    }

    return intervals;
};

export const usePaymentDetailSchedules = () => {
    const fetchSchedules = async (force = false) => {
        if (loaded.value && !force) {
            return schedules.value;
        }

        loading.value = true;

        try {
            const response = await axios.get(route('payment-detail-schedules.index'), {
                headers: { Accept: 'application/json' },
            });
            const data = response.data?.data || {};

            schedules.value = data.schedules || [];
            serverTimezone.value = data.server_timezone || null;
            serverNow.value = data.server_now || null;
            loaded.value = true;

            return schedules.value;
        } finally {
            loading.value = false;
        }
    };

    const scheduleOptions = () => {
        return (schedules.value || []).map((schedule) => ({
            id: schedule.id,
            name: schedule.name,
            status_label: schedule.status_label,
        }));
    };

    const findScheduleById = (scheduleId) => {
        if (!scheduleId) {
            return null;
        }

        return schedules.value.find((schedule) => Number(schedule.id) === Number(scheduleId)) ?? null;
    };

    const invalidateSchedules = () => {
        loaded.value = false;
    };

    return {
        schedules,
        serverTimezone,
        serverNow,
        loading,
        loaded,
        fetchSchedules,
        scheduleOptions,
        findScheduleById,
        invalidateSchedules,
        buildDefaultWeekdayIntervals,
    };
};
