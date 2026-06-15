export const removedDetailTypes = ['nspk'];

export function isRemovedDetailType(type) {
    return removedDetailTypes.includes(type);
}

export function stripRemovedDetailTypes(types) {
    return (types || []).filter((type) => !isRemovedDetailType(type));
}

export function useFormatPaymentDetail(detail, type) {
    if (type === 'card') {
        return detail.match(/.{1,4}/g).join(' ');
    }
    if (['phone', 'mobile_commerce'].includes(type)) {
        let x = detail.replace(/\D/g, '').match(/(\d{1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);

        return  !x[2] ? x[1] : '+' + x[1] + ' (' + x[2] + ') ' + x[3] + '-' + x[4] + '-' + x[5];
    }
    if (type === 'account_number') {
        return detail.match(/.{1,4}/g).join(' ');
    }
    if (type === 'iban_uah') {
        return detail;
    }
    if (type === 'e-com') {
        return detail;
    }

    return detail;
}
