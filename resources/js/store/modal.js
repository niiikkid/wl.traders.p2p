import {defineStore} from 'pinia'

export const useModalStore = defineStore('modal', {
    state: () => {
        return {
            modals: {
                confirm: {
                    showed: false,
                    params: {},
                },
                dispute: {
                    showed: false,
                    params: {},
                },
                disputeCancel: {
                    showed: false,
                    params: {},
                },
                deposit: {
                    showed: false,
                    params: {},
                },
                traderDeposit: {
                    showed: false,
                    params: {},
                },
                withdrawal: {
                    showed: false,
                    params: {},
                },
                order: {
                    showed: false,
                    params: {},
                },
                editOrderAmount: {
                    showed: false,
                    params: {},
                },
                userNotes: {
                    showed: false,
                    params: {},
                },
                userCreate: {
                    showed: false,
                    params: {},
                },
                userEdit: {
                    showed: false,
                    params: {},
                },
                userTempVipHistory: {
                    showed: false,
                    params: {},
                },
                paymentDetailCreate: {
                    showed: false,
                    params: {},
                },
                paymentDetailEdit: {
                    showed: false,
                    params: {},
                },
                paymentDetailBulkEdit: {
                    showed: false,
                    params: {},
                },
                paymentDetailTagCreate: {
                    showed: false,
                    params: {},
                },
                paymentDetailTagManage: {
                    showed: false,
                    params: {},
                },
                merchantCreate: {
                    showed: false,
                    params: {},
                },
                merchantSettings: {
                    showed: false,
                    params: {},
                },
                paymentCreate: {
                    showed: false,
                    params: {},
                },
                supportCreate: {
                    showed: false,
                    params: {},
                },
                supportEdit: {
                    showed: false,
                    params: {},
                },
                paymentGatewayCreate: {
                    showed: false,
                    params: {},
                },
                paymentGatewayEdit: {
                    showed: false,
                    params: {},
                },
                paymentGatewayBulkSettings: {
                    showed: false,
                    params: {},
                },
                priceParserEdit: {
                    showed: false,
                    params: {},
                },
                payoutCreate: {
                    showed: false,
                    params: {},
                },
                payoutSettings: {
                    showed: false,
                    params: {},
                },
                antiFraudSetting: {
                    showed: false,
                    params: {},
                },
                antiFraudClientOrders: {
                    showed: false,
                    params: {},
                },
            },
        }
    },
    getters: {
        confirmModal: (state) => state.modals.confirm,
        disputeModal: (state) => state.modals.dispute,
        disputeCancelModal: (state) => state.modals.disputeCancel,
        depositModal: (state) => state.modals.deposit,
        withdrawalModal: (state) => state.modals.withdrawal,
        orderModal: (state) => state.modals.order,
        editOrderAmountModal: (state) => state.modals.editOrderAmount,
        userNotesModal: (state) => state.modals.userNotes,
        traderDepositModal: (state) => state.modals.traderDeposit,
        userCreateModal: (state) => state.modals.userCreate,
        userEditModal: (state) => state.modals.userEdit,
        userTempVipHistoryModal: (state) => state.modals.userTempVipHistory,
        paymentDetailCreateModal: (state) => state.modals.paymentDetailCreate,
        paymentDetailEditModal: (state) => state.modals.paymentDetailEdit,
        paymentDetailBulkEditModal: (state) => state.modals.paymentDetailBulkEdit,
        paymentDetailTagCreateModal: (state) => state.modals.paymentDetailTagCreate,
        paymentDetailTagManageModal: (state) => state.modals.paymentDetailTagManage,
        merchantCreateModal: (state) => state.modals.merchantCreate,
        merchantSettingsModal: (state) => state.modals.merchantSettings,
        paymentCreateModal: (state) => state.modals.paymentCreate,
        payoutCreateModal: (state) => state.modals.payoutCreate,
        payoutSettingsModal: (state) => state.modals.payoutSettings,
        supportCreateModal: (state) => state.modals.supportCreate,
        supportEditModal: (state) => state.modals.supportEdit,
        paymentGatewayCreateModal: (state) => state.modals.paymentGatewayCreate,
        paymentGatewayEditModal: (state) => state.modals.paymentGatewayEdit,
        paymentGatewayBulkSettingsModal: (state) => state.modals.paymentGatewayBulkSettings,
        priceParserEditModal: (state) => state.modals.priceParserEdit,
        antiFraudSettingModal: (state) => state.modals.antiFraudSetting,
        antiFraudClientOrdersModal: (state) => state.modals.antiFraudClientOrders,
    },
    actions: {
        openModal(name, params = {}) {
            this.modals[name].showed = true;
            this.modals[name].params = params;
        },
        closeModal(name) {
            this.modals[name].showed = false;
            this.modals[name].params = {};
        },
        openConfirmModal({
             title,
             body = 'Действие невозможно отменить.',
             confirm_button_name = 'Подтвердить',
             cancel_button_name = 'Отмена',
             confirm = null,
             close = null
        } = {}) {
            this.openModal('confirm', {
                title,
                body,
                confirm_button_name,
                cancel_button_name,
                confirm,
                close
            });
        },
        openDisputeModal(props) {
            this.openModal('dispute', props);
        },
        openDisputeCancelModal(props) {
            this.openModal('disputeCancel', props);
        },
        openDepositModal(props) {
            this.openModal('deposit', props);
        },
        openTraderDepositModal(props) {
            this.openModal('traderDeposit', props);
        },
        openWithdrawalModal(props) {
            this.openModal('withdrawal', props);
        },
        openOrderModal(props) {
            this.openModal('order', props);
        },
        openEditOrderAmountModal(props) {
            this.openModal('editOrderAmount', props);
        },
        openUserNotesModal(props) {
            this.openModal('userNotes', props);
        },
        openUserCreateModal(props) {
            this.openModal('userCreate', props);
        },
        openUserEditModal(props) {
            this.openModal('userEdit', props);
        },
        openUserTempVipHistoryModal(props) {
            this.openModal('userTempVipHistory', props);
        },
        openPaymentDetailCreateModal(props) {
            this.openModal('paymentDetailCreate', props);
        },
        openPaymentDetailEditModal(props) {
            this.openModal('paymentDetailEdit', props);
        },
        openPaymentDetailBulkEditModal(props) {
            this.openModal('paymentDetailBulkEdit', props);
        },
        openPaymentDetailTagCreateModal(props) {
            this.openModal('paymentDetailTagCreate', props);
        },
        openPaymentDetailTagManageModal(props) {
            this.openModal('paymentDetailTagManage', props);
        },
        openMerchantCreateModal(props) {
            this.openModal('merchantCreate', props);
        },
        openMerchantSettingsModal(props) {
            this.openModal('merchantSettings', props);
        },
        openPaymentCreateModal(props) {
            this.openModal('paymentCreate', props);
        },
        openPayoutCreateModal(props) {
            this.openModal('payoutCreate', props);
        },
        openPayoutSettingsModal(props) {
            this.openModal('payoutSettings', props);
        },
        openSupportCreateModal(props) {
            this.openModal('supportCreate', props);
        },
        openSupportEditModal(props) {
            this.openModal('supportEdit', props);
        },
        openPaymentGatewayCreateModal(props) {
            this.openModal('paymentGatewayCreate', props);
        },
        openPaymentGatewayEditModal(props) {
            this.openModal('paymentGatewayEdit', props);
        },
        openPaymentGatewayBulkSettingsModal(props) {
            this.openModal('paymentGatewayBulkSettings', props);
        },
        openAntiFraudSettingModal(props) {
            this.openModal('antiFraudSetting', props);
        },
        openAntiFraudClientOrdersModal(props) {
            this.openModal('antiFraudClientOrders', props);
        },
        openPriceParserEditModal(props) {
            this.openModal('priceParserEdit', props);
        },
        closeAll() {
            for (const modal_name in this.modals) {
                this.closeModal(modal_name)
            }
        }
    },
})
