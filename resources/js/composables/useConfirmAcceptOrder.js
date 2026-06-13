import { router, useForm } from '@inertiajs/vue3';
import { useModalStore } from '@/store/modal.js';
import { useViewStore } from '@/store/view.js';

const buildOrderSummary = (order) => ({
    uuid: order.uuid,
    amount: order.amount,
    currency: order.currency,
    payment_detail: order.payment_detail,
    payment_detail_type: order.payment_detail_type,
    payment_detail_name: order.payment_detail_name,
});

const resolveRouteName = (resolver, fallback) => {
    if (typeof resolver === 'function') {
        return resolver();
    }

    if (typeof resolver === 'string') {
        return resolver;
    }

    return fallback();
};

export function useConfirmAcceptOrder(options = {}) {
    const modalStore = useModalStore();
    const viewStore = useViewStore();

    const defaultAcceptRouteName = () => {
        if (viewStore.isAnalystViewMode) {
            return 'analyst.orders.accept';
        }

        if (viewStore.isSupportViewMode) {
            return 'support.orders.accept';
        }

        return 'orders.accept';
    };

    const defaultIndexRouteName = () => {
        if (viewStore.isAnalystViewMode) {
            return 'analyst.orders.index';
        }

        if (viewStore.isSupportViewMode) {
            return 'support.orders.index';
        }

        if (viewStore.isAdminViewMode) {
            return 'admin.orders.index';
        }

        return viewStore.adminPrefix + 'orders.index';
    };

    const confirmAcceptOrder = (order) => {
        modalStore.openConfirmModal({
            title: 'Закрыть сделку как оплаченную?',
            body: 'Проверьте данные сделки перед подтверждением. Действие нельзя отменить.',
            order_summary: buildOrderSummary(order),
            confirm_button_name: 'Платеж поступил',
            confirm: () => {
                useForm({}).patch(
                    route(resolveRouteName(options.acceptRouteName, defaultAcceptRouteName), order.id),
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            modalStore.closeAll();
                            router.visit(route(resolveRouteName(options.indexRouteName, defaultIndexRouteName)), {
                                only: ['orders'],
                            });
                        },
                    },
                );
            },
        });
    };

    return { confirmAcceptOrder };
}
