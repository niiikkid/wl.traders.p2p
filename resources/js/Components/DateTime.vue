<script setup>
import {computed} from "vue";
import {useClipboard} from "@vueuse/core";

const props = defineProps({
    data: {
        type: String,
    },
    plural: {
        type: Boolean,
        default: null,
    },
    simple: {
        type: Boolean,
        default: false,
    },
});

const formatDateRelative = (dateString) => {
    // Поддержка ISO (с Z/offset) и наивного 'YYYY-MM-DD HH:MM[:SS]'
    let correctedDate;
    const isoMatch = (dateString ?? '').match(
        /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?(Z|[+-]\d{2}:\d{2})$/
    );
    if (isoMatch) {
        correctedDate = new Date(dateString);
    } else {
        const naive = (dateString ?? '').match(
            /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?$/
        );
        if (!naive) {
            // Неизвестный формат — показываем "только что", чтобы не ломать UI
            return 'только что';
        }
        const [, y, mo, d, h, mi, s] = naive;
        // Создаём локальную дату без смены таймзоны
        correctedDate = new Date(
            Number(y),
            Number(mo) - 1,
            Number(d),
            Number(h),
            Number(mi),
            s ? Number(s) : 0,
            0
        );
    }
    const now = new Date();
    const diffInSeconds = Math.floor((now - correctedDate) / 1000);

    const intervals = {
        'год': 31536000,
        'месяц': 2592000,
        'неделя': 604800,
        'день': 86400,
        'час': 3600,
        'минута': 60,
        'секунда': 1,
    };

    for (const [unit, seconds] of Object.entries(intervals)) {
        const interval = Math.floor(diffInSeconds / seconds);
        if (interval >= 1) {
            if (unit === 'секунда' && interval < 5) {
                return 'только что';
            }
            return `${interval} ${getPluralForm(interval, unit)} назад`;
        }
    }

    return 'только что';
}

const getPluralForm = (number, unit) => {
    const pluralRules = {
        'год': ['год', 'года', 'лет'],
        'месяц': ['месяц', 'месяца', 'месяцев'],
        'неделя': ['неделя', 'недели', 'недель'],
        'день': ['день', 'дня', 'дней'],
        'час': ['час', 'часа', 'часов'],
        'минута': ['минута', 'минуты', 'минут'],
        'секунда': ['секунда', 'секунды', 'секунд'],
    };

    const cases = [2, 0, 1, 1, 1, 2];
    return pluralRules[unit][
        number % 100 > 4 && number % 100 < 20
            ? 2
            : cases[Math.min(number % 10, 5)]
        ];
}

const formatDateStandard = (dateString) => {
    if (!dateString) {
        return '';
    }

    const iso = dateString;
    const isoMatch = iso.match(
        /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?(Z|[+-]\d{2}:\d{2})$/
    );
    if (isoMatch) {
        const [, y, mo, d, h, mi, s] = isoMatch;
        const yearShort = y.slice(-2); // Последние две цифры года
        return `${yearShort}.${mo}.${d} ${h}:${mi}:${s ?? '00'}`;
    }

    const naiveMatch = iso.match(
        /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?$/
    );
    if (naiveMatch) {
        const [, y, mo, d, h, mi, s] = naiveMatch;
        const yearShort = y.slice(-2); // Последние две цифры года
        return `${yearShort}.${mo}.${d} ${h}:${mi}:${s ?? '00'}`;
    }

    const parsedDate = new Date(dateString);
    if (!Number.isNaN(parsedDate.getTime())) {
        const pad = (n) => String(n).padStart(2, '0');
        const year = parsedDate.getFullYear();
        const yearShort = String(year).slice(-2); // Последние две цифры года
        const month = pad(parsedDate.getMonth() + 1);
        const day = pad(parsedDate.getDate());
        const hours = pad(parsedDate.getHours());
        const minutes = pad(parsedDate.getMinutes());
        const seconds = pad(parsedDate.getSeconds());
        return `${yearShort}.${month}.${day} ${hours}:${minutes}:${seconds}`;
    }

    // fallback — если строка с нестандартным форматом, вернуть как есть
    return dateString;
};

const formatedData = computed(() => {
    if (props.plural) {
        return formatDateRelative(props.data);
    }

    // Полный формат: YY.MM.DD HH:MM:SS (год двумя цифрами, разделитель - точка)
    return formatDateStandard(props.data);
});

const formatDateFull = (dateString) => {
    // Используем тот же формат для полной даты: YY.MM.DD HH:MM:SS (год двумя цифрами, разделитель - точка)
    return formatDateStandard(dateString);
};

const fullDate = computed(() => formatDateFull(props.data));

const { copy, copied } = useClipboard();
</script>

<template>
    <div>
        <template v-if="simple">
            <span class="inline-flex items-center gap-2 text-base-content text-nowrap">
                <svg class="h-4 w-4 text-base-content/70" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z" />
                </svg>
                <span class="inline-block align-middle">{{ fullDate }}</span>
            </span>
        </template>
        <template v-else>
            <div class="tooltip" :data-tip="copied ? 'Скопировано!' : 'Скопировать'">
                <div
                    class="btn btn-ghost btn-xs gap-2 inline-flex items-center text-nowrap"
                    role="button"
                    tabindex="0"
                    @click.prevent="copy(fullDate)"
                >
                    <svg class="h-4 w-4 text-base-content/70" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z" />
                    </svg>
                    <p class="inline-block align-middle text-base-content">{{ formatedData }}</p>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>

</style>
