const CYRILLIC_TO_LATIN = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z',
    и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r',
    с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch',
    ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
    ґ: 'g', є: 'ye', і: 'i', ї: 'yi',
};

const transliterate = (value) => {
    return Array.from(String(value).toLowerCase())
        .map((char) => CYRILLIC_TO_LATIN[char] ?? char)
        .join('');
};

/**
 * Generates a payment gateway code from a human-readable name.
 * Example: "Сбербанк" → "sberbank", "Альфа-Банк" → "alfabank"
 */
export const generatePaymentGatewayCode = (name) => {
    if (!name) {
        return '';
    }

    return transliterate(name)
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '')
        .slice(0, 30);
};
