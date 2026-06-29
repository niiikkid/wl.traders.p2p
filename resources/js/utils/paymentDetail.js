export const removedDetailTypes = ['nspk'];

export function isRemovedDetailType(type) {
    return removedDetailTypes.includes(type);
}

export function stripRemovedDetailTypes(types) {
    return (types || []).filter((type) => !isRemovedDetailType(type));
}

const phoneFormatsByCurrency = {
    RUB: { country: 'Россия', code: '7', groups: [3, 3, 2, 2], placeholder: '+7 (999) 123-45-67' },
    UAH: { country: 'Украина', code: '380', groups: [2, 3, 2, 2], placeholder: '+380 (99) 123-45-67' },
    KZT: { country: 'Казахстан', code: '7', groups: [3, 3, 2, 2], placeholder: '+7 (701) 123-45-67' },
    UZS: { country: 'Узбекистан', code: '998', groups: [2, 3, 2, 2], placeholder: '+998 (90) 123-45-67' },
    KGS: { country: 'Кыргызстан', code: '996', groups: [3, 3, 3], placeholder: '+996 (555) 123-456' },
    TJS: { country: 'Таджикистан', code: '992', groups: [2, 3, 2, 2], placeholder: '+992 (90) 123-45-67' },
    BYN: { country: 'Беларусь', code: '375', groups: [2, 3, 2, 2], placeholder: '+375 (29) 123-45-67' },
    AZN: { country: 'Азербайджан', code: '994', groups: [2, 3, 2, 2], placeholder: '+994 (50) 123-45-67' },
    GEL: { country: 'Грузия', code: '995', groups: [3, 2, 2, 2], placeholder: '+995 (555) 12-34-56' },
};

const defaultPhoneFormat = phoneFormatsByCurrency.RUB;

export function getPaymentDetailInputMeta(type, currency = null) {
    if (['phone', 'mobile_commerce'].includes(type)) {
        const format = getPhoneFormatByCurrency(currency);

        return {
            badge: format.country,
            prefix: `+${format.code}`,
            placeholder: format.placeholder,
            helper: `Формат для страны: ${format.country}. Код страны подставится автоматически.`,
        };
    }

    const meta = {
        card: {
            badge: 'Карта',
            prefix: 'CARD',
            placeholder: '0000 0000 0000 0000',
            helper: 'Введите номер карты — пробелы добавятся автоматически.',
        },
        account_number: {
            badge: 'Счет',
            prefix: 'ACC',
            placeholder: '0000 0000 0000 0000 0000',
            helper: 'Введите номер банковского счета — он будет разбит на группы.',
        },
        iban_uah: {
            badge: 'IBAN',
            prefix: 'UA',
            placeholder: 'UA54 3220 0100 0002 6200 3537 8963 5',
            helper: 'IBAN приводится к верхнему регистру и группируется для проверки.',
        },
        'e-com': {
            badge: 'URL',
            prefix: 'https://',
            placeholder: 'https://example.com/pay',
            helper: 'Вставьте полную ссылку на страницу оплаты.',
        },
    };

    return meta[type] ?? {
        badge: 'Реквизит',
        prefix: null,
        placeholder: '',
        helper: null,
    };
}

export function formatPaymentDetailInput(value, type, currency = null) {
    const normalized = normalizePaymentDetailInput(value, type, currency);

    if (type === 'card') {
        return groupCharacters(normalized, 4);
    }

    if (['phone', 'mobile_commerce'].includes(type)) {
        return formatPhone(normalized, getPhoneFormatByCurrency(currency));
    }

    if (type === 'account_number') {
        return groupCharacters(normalized, 4);
    }

    if (type === 'iban_uah') {
        return groupCharacters(normalized, 4);
    }

    return normalized;
}

export function normalizePaymentDetailInput(value, type, currency = null) {
    const text = String(value ?? '').trim();

    if (['card', 'account_number'].includes(type)) {
        return text.replace(/\D/g, '');
    }

    if (['phone', 'mobile_commerce'].includes(type)) {
        return normalizePhone(text, getPhoneFormatByCurrency(currency));
    }

    if (type === 'iban_uah') {
        return text.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
    }

    return text;
}

export function useFormatPaymentDetail(detail, type) {
    if (!detail) {
        return detail;
    }

    if (type === 'card') {
        return groupCharacters(detail, 4);
    }
    if (['phone', 'mobile_commerce'].includes(type)) {
        return formatPhone(detail.replace(/\D/g, ''), detectPhoneFormat(detail));
    }
    if (type === 'account_number') {
        return groupCharacters(detail, 4);
    }
    if (type === 'iban_uah') {
        return groupCharacters(detail, 4);
    }
    if (type === 'e-com') {
        return detail;
    }

    return detail;
}

function getPhoneFormatByCurrency(currency) {
    return phoneFormatsByCurrency[String(currency ?? '').toUpperCase()] ?? defaultPhoneFormat;
}

function normalizePhone(value, format) {
    const digits = value.replace(/\D/g, '');

    if (!digits) {
        return '';
    }

    if (digits.startsWith(format.code)) {
        return digits;
    }

    if (format.code === '7' && digits.startsWith('8')) {
        return `7${digits.slice(1)}`;
    }

    return `${format.code}${digits}`;
}

function formatPhone(value, format) {
    const digits = normalizePhone(value, format);

    if (!digits) {
        return '';
    }

    const localNumber = digits.slice(format.code.length);
    const groupedLocalNumber = splitByGroups(localNumber, format.groups);
    const [operatorCode, ...rest] = groupedLocalNumber;
    const tail = rest.filter(Boolean).join('-');

    if (!operatorCode) {
        return `+${digits}`;
    }

    return tail ? `+${format.code} (${operatorCode}) ${tail}` : `+${format.code} (${operatorCode}`;
}

function detectPhoneFormat(value) {
    const digits = String(value ?? '').replace(/\D/g, '');
    const matchedFormat = Object.values(phoneFormatsByCurrency)
        .sort((left, right) => right.code.length - left.code.length)
        .find((format) => digits.startsWith(format.code));

    return matchedFormat ?? defaultPhoneFormat;
}

function groupCharacters(value, size) {
    return String(value ?? '').replace(/\s/g, '').match(new RegExp(`.{1,${size}}`, 'g'))?.join(' ') ?? '';
}

function splitByGroups(value, groups) {
    const chunks = [];
    let offset = 0;

    groups.forEach((size) => {
        const chunk = value.slice(offset, offset + size);

        if (chunk) {
            chunks.push(chunk);
        }

        offset += size;
    });

    const rest = value.slice(offset);

    if (rest) {
        chunks.push(rest);
    }

    return chunks;
}
