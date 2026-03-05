<?php

namespace Database\Factories;

/**
 * Фабрика для генерации данных мерчантов
 */
class MerchantDataFactory
{
    /**
     * Список названий для мерчантов (без упоминания крипты, обмена, трейдинга)
     *
     * @var array
     */
    protected static array $merchantNames = [
        'Мой любимый магазин', 'Классный выбор', 'Чудо-покупки', 'Семейный магазин', 'Лучшее место', 
        'Счастливый клиент', 'Удобный сервис', 'Твой выбор', 'Надежная компания', 'Домашний магазин',
        'Простые покупки', 'Магазин хороших цен', 'Всегда доступно', 'Только для вас', 'Верный помощник', 
        'Ваш комфорт', 'Идеальный вариант', 'Хорошее решение', 'Быстрая помощь', 'Надежный выбор',
        'Мир удобства', 'Время покупок', 'Лучший сервис', 'Просто и понятно', 'Товары для всех',
        'Выгодные услуги', 'Магазин мечты', 'Просто находка', 'Всё что нужно', 'Отличный сервис'
    ];

    /**
     * Список описаний для мерчантов (общие, без упоминания криптовалют)
     *
     * @var array
     */
    protected static array $merchantDescriptions = [
        'Быстрый и безопасный магазин. Лучшие цены на рынке. Работаем 24/7.',
        'Надежная платформа для покупок. Мгновенная доставка и поддержка.',
        'Удобный сервис для всей семьи. Низкие комиссии и высокая скорость обработки заказов.',
        'Многофункциональный магазин для работы и отдыха. Защита от мошенничества и гарантия возврата.',
        'Автоматическая система покупок. Без регистрации и лишних проверок. Быстрая доставка.',
        'Профессиональный сервис для всех. Гарантия безопасности и анонимности. Поддержка 24/7.',
        'Моментальные платежи. Выгодные цены. Широкий выбор способов оплаты.',
        'Торговая площадка с большим выбором товаров. Максимальная безопасность сделок.',
        'Надежный магазин для всех. Прозрачные условия и гарантии возврата средств.',
        'Современная платформа для покупок. Быстрые доставки и лучшие цены на рынке.',
        'Универсальный магазин с поддержкой всех популярных товаров. Выгодные тарифы на доставку.',
        'Безопасные покупки. Мгновенные доставки и лучшие цены.',
        'Покупки для всей семьи. Удобно и безопасно.',
        'Товары с минимальной комиссией. Автоматический процесс и высокая скорость.',
        'Удобный интерфейс для новичков и профессионалов. Максимальная защита покупок.'
    ];

    /**
     * Список тестовых доменов (без упоминания крипты и трейдинга)
     *
     * @var array
     */
    protected static array $domains = [
        'myshop.test', 'bestchoice.demo', 'quickservice.example', 'megastore.test', 'topshop.demo',
        'easypay.example', 'goodstore.test', 'comfort.demo', 'bestplace.example', 'happyshop.test',
        'goodsale.demo', 'simpleshop.example', 'gigastore.test', 'easyshop.demo', 'coolstore.example',
        'quickbuy.test', 'bestprice.demo', 'digitalshop.example', 'dreamshop.test', 'bizservice.demo',
        'fastmarket.example', 'homeshop.test', 'quickstore.demo', 'onlinestore.example', 'shopplus.test',
        'favshop.demo', 'marketplace.example', 'familystore.test', 'goodshop.demo', 'amazeshop.example'
    ];

    /**
     * Список тестовых callback URL
     *
     * @var array
     */
    protected static array $callbackUrls = [
        'https://{domain}/api/callback', 
        'https://{domain}/api/payment/callback',
        'https://{domain}/api/webhook',
        'https://{domain}/api/payment/success',
        'https://{domain}/callback',
        'https://{domain}/webhook',
        'https://{domain}/api/notification',
        'https://api.{domain}/callback',
        'https://api.{domain}/payment/callback',
        'https://callback.{domain}/api'
    ];

    /**
     * Генерирует callback URL на основе домена
     *
     * @param string $domain
     * @return string
     */
    public static function generateCallbackUrl(string $domain): string
    {
        $template = static::$callbackUrls[array_rand(static::$callbackUrls)];
        return str_replace('{domain}', $domain, $template);
    }

    /**
     * Возвращает массив с новыми данными для мерчанта (только name, description, domain и callback_url)
     *
     * @return array
     */
    public static function getRandomMerchantData(): array
    {
        $name = static::$merchantNames[array_rand(static::$merchantNames)];
        $domain = static::$domains[array_rand(static::$domains)];
        $description = static::$merchantDescriptions[array_rand(static::$merchantDescriptions)];
        $callbackUrl = static::generateCallbackUrl($domain);
        
        return [
            'name' => $name,
            'description' => $description,
            'domain' => $domain,
            'callback_url' => $callbackUrl,
            'payout_callback_url' => $callbackUrl,
        ];
    }
} 