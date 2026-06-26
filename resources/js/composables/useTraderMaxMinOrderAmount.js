import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const resolveLimit = (value) => {
    const parsed = Number(value ?? 0);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
};

const resolveOptionValue = (value) => {
    if (value && typeof value === 'object' && 'value' in value) {
        return value.value;
    }

    if (typeof value === 'function') {
        return value();
    }

    return value;
};

export function useTraderMaxMinOrderAmount(options = {}) {
    const currentUser = usePage().props.auth?.user;

    const traderMaxMinOrderAmount = computed(() => {
        const bypassForAdmin = resolveOptionValue(options.bypassForAdmin);
        const shouldBypass = bypassForAdmin === undefined
            ? (usePage().props.auth?.is_admin === true || usePage().props.auth?.role?.name === 'Super Admin')
            : bypassForAdmin === true;

        if (shouldBypass) {
            return null;
        }

        const ownerLimit = resolveLimit(resolveOptionValue(options.ownerMaxMinOrderAmount));
        if (ownerLimit !== null) {
            return ownerLimit;
        }

        return resolveLimit(currentUser?.max_min_order_amount);
    });

    const minOrderAmountHelperText = computed(() => {
        if (traderMaxMinOrderAmount.value === null) {
            return null;
        }

        return `Максимально допустимое значение: ${traderMaxMinOrderAmount.value}. Чтобы указать больше, обратитесь в поддержку.`;
    });

    const clampMinOrderAmount = (value) => {
        const limit = traderMaxMinOrderAmount.value;
        if (limit === null || value === '' || value === null) {
            return value;
        }

        const numericValue = Number(value);
        if (! Number.isFinite(numericValue)) {
            return value;
        }

        return Math.min(limit, numericValue);
    };

    return {
        traderMaxMinOrderAmount,
        minOrderAmountHelperText,
        clampMinOrderAmount,
    };
}
