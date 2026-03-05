<?php

namespace Database\Factories;

/**
 * Фабрика для генерации данных мобильных устройств
 */
class DeviceDataFactory
{
    /**
     * Список брендов и моделей устройств
     *
     * @var array
     */
    protected static array $deviceTypes = [
        'Samsung' => [
            'Galaxy S21', 'Galaxy S22', 'Galaxy S23', 'Galaxy S24', 
            'Galaxy Note 20', 'Galaxy A53', 'Galaxy A54', 'Galaxy Tab S8'
        ],
        'Apple' => [
            'iPhone 12', 'iPhone 13', 'iPhone 14', 'iPhone 15', 
            'iPad Pro', 'iPad Air', 'iPad Mini', 'MacBook Pro'
        ],
        'Xiaomi' => [
            'Redmi Note 11', 'Redmi Note 12', 'Redmi Note 13', 
            'Mi 12', 'Mi 13', 'POCO X5', 'POCO F5'
        ],
        'Google' => [
            'Pixel 6', 'Pixel 7', 'Pixel 8', 
            'Pixel Tablet', 'Pixel Fold'
        ],
        'OnePlus' => [
            'OnePlus 10', 'OnePlus 11', 'OnePlus 12', 
            'OnePlus Nord 3', 'OnePlus Pad'
        ],
        'Huawei' => [
            'P50', 'P60', 'Mate 50', 'Mate X3', 
            'Nova 11', 'MatePad Pro'
        ],
        'Sony' => [
            'Xperia 1 V', 'Xperia 5 V', 'Xperia 10 V'
        ],
        'Motorola' => [
            'Edge 40', 'Edge 50', 'Razr 40', 'G54', 'G84'
        ],
        'Nothing' => [
            'Phone 1', 'Phone 2', 'CMF Phone 1'
        ],
        'ASUS' => [
            'Zenfone 10', 'ROG Phone 7', 'ROG Phone 8'
        ]
    ];

    /**
     * Версии Android
     *
     * @var array
     */
    protected static array $androidVersions = [
        '10.0', '11.0', '12.0', '13.0', '14.0'
    ];

    /**
     * Генерирует случайные данные для устройства
     *
     * @return array
     */
    public static function getRandomDeviceData(): array
    {
        // Выбираем случайный бренд
        $brand = array_rand(static::$deviceTypes);
        
        // Выбираем случайную модель из выбранного бренда
        $model = static::$deviceTypes[$brand][array_rand(static::$deviceTypes[$brand])];
        
        // Генерируем случайный ID устройства
        $deviceId = mt_rand(100, 999);
        
        // Формируем название устройства
        $deviceName = "{$brand} {$model} {$deviceId}";
        
        // Генерируем случайный Android ID
        $androidId = strtolower(bin2hex(random_bytes(8)));
        
        // Выбираем случайную версию Android
        $androidVersion = static::$androidVersions[array_rand(static::$androidVersions)];
        
        return [
            'name' => $deviceName,
            'brand' => $brand,
            'device_model' => $model,
            'android_id' => $androidId,
            'android_version' => $androidVersion,
            'manufacturer' => $brand
        ];
    }
} 