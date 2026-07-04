const WALLET_DEPOSIT_INVOICE_STATUS_BADGES = {
    pending: 'badge-info',
    processing: 'badge-warning',
    paid: 'badge-success',
    expired: 'badge-warning badge-outline',
    cancelled: 'badge-error',
    amount_mismatch: 'badge-error',
    failed: 'badge-error',
};

export function walletDepositInvoiceStatusBadge(status) {
    return WALLET_DEPOSIT_INVOICE_STATUS_BADGES[status] ?? 'badge-outline badge-base-content';
}
