<?php

namespace Database\Factories;

use App\Enums\NetworkEnum;

/**
 * Фабрика для генерации данных инвойсов
 */
class InvoiceDataFactory
{
    /**
     * Префиксы для external_id для разных платежных систем
     *
     * @var array
     */
    protected static array $externalIdPrefixes = [
        'PAY-', 'INV-', 'TRX-', 'PMT-', 'CRY-', 'BIT-', 'ETH-', 'BNB-', 
        'CRYP-', 'PID-', 'EXT-', 'DGT-', 'XID-', 'SYS-', 'NET-'
    ];

    /**
     * Генерирует случайный external_id
     *
     * @return string
     */
    public static function generateExternalId(): string
    {
        $prefix = static::$externalIdPrefixes[array_rand(static::$externalIdPrefixes)];
        $randomPart = strtoupper(bin2hex(random_bytes(8)));
        
        return $prefix . $randomPart;
    }

    /**
     * Генерирует случайный адрес криптовалютного кошелька для указанной сети
     *
     * @param NetworkEnum|null $network
     * @return string
     */
    public static function generateAddress(?NetworkEnum $network = null): string
    {
        // Используем TRX по умолчанию, если сеть не указана
        if ($network === null) {
            $network = NetworkEnum::TRX;
        }
        
        switch ($network) {
            case NetworkEnum::ETH:
            case NetworkEnum::BSC:
            case NetworkEnum::ARB:
            case NetworkEnum::AVAX:
            case NetworkEnum::MATIC:
                // Адрес Ethereum-совместимой сети (EVM): 0x + 40 символов (20 байт)
                return '0x' . bin2hex(random_bytes(20));
                
            case NetworkEnum::TRX:
                // Адрес TRON начинается с 'T' и имеет длину 34 символа
                return 'T' . bin2hex(random_bytes(16));
                
            default:
                // Общий формат для других сетей
                return '0x' . bin2hex(random_bytes(20));
        }
    }

    /**
     * Генерирует случайный хеш транзакции для указанной сети
     *
     * @param NetworkEnum|null $network
     * @return string
     */
    public static function generateTxHash(?NetworkEnum $network = null): string
    {
        // Используем TRX по умолчанию, если сеть не указана
        if ($network === null) {
            $network = NetworkEnum::TRX;
        }
        
        switch ($network) {
            case NetworkEnum::ETH:
            case NetworkEnum::BSC:
            case NetworkEnum::ARB:
            case NetworkEnum::AVAX:
            case NetworkEnum::MATIC:
                // Хеш транзакции Ethereum-совместимой сети: 0x + 64 символа (32 байта)
                return '0x' . bin2hex(random_bytes(32));
                
            case NetworkEnum::TRX:
                // Хеш транзакции TRON, обычно 64 символа без префикса
                return bin2hex(random_bytes(32));
                
            default:
                // Общий формат для других сетей
                return '0x' . bin2hex(random_bytes(32));
        }
    }

    /**
     * Генерирует случайный ID транзакции (обычно используется для внешних систем)
     *
     * @return string
     */
    public static function generateTransactionId(): string
    {
        // Варианты форматов ID транзакций
        $formats = [
            'TX{random}',        // TX + случайные символы
            '{random}',          // Просто случайные символы
            '{random}-{date}',   // Случайные символы-дата
            '{date}-{random}',   // Дата-случайные символы
            'TRX_{timestamp}',   // TRX_ + временная метка
            '{timestamp}_{random}' // Временная метка_случайные символы
        ];
        
        // Выбираем случайный формат
        $format = $formats[array_rand($formats)];
        
        // Подставляем данные в формат
        $result = str_replace(
            ['{random}', '{date}', '{timestamp}'],
            [
                strtoupper(bin2hex(random_bytes(6))),
                date('Ymd'),
                time()
            ],
            $format
        );
        
        return $result;
    }

    /**
     * Возвращает массив с новыми данными для инвойса для указанной сети
     *
     * @param NetworkEnum|null $network
     * @return array
     */
    public static function getRandomInvoiceData(?NetworkEnum $network = null): array
    {
        // Используем TRX по умолчанию, если сеть не указана
        if ($network === null) {
            $network = NetworkEnum::TRX;
        }
        
        return [
            'external_id' => static::generateExternalId(),
            'address' => static::generateAddress($network),
            'tx_hash' => static::generateTxHash($network),
            'transaction_id' => static::generateTransactionId()
        ];
    }
} 