<?php

namespace App\Support\TestData;

use App\Enums\DetailType;
use App\Enums\MarketEnum;
use App\Services\Market\Utils\MarketStore;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Набор детерминированных генераторов правдоподобных данных для демо/тестовой среды.
 *
 * Все методы статические и не имеют побочных эффектов, кроме seedMarketPrices(),
 * который наполняет кэш курсами, чтобы заказы можно было создавать без внешних API.
 */
class DemoDataHelper
{
    /**
     * Ориентировочные sell-курсы (сколько фиата за 1 USDT) для правдоподобных сделок.
     *
     * @var array<string, float>
     */
    public const SELL_RATES = [
        'rub' => 97.5,
        'kzt' => 505.0,
        'eur' => 0.93,
        'tjs' => 10.9,
        'kgs' => 88.5,
        'uah' => 41.5,
        'usd' => 1.0,
        'azn' => 1.70,
        'try' => 34.5,
        'idr' => 16050.0,
        'pln' => 3.98,
    ];

    /**
     * Диапазоны сумм заказов в фиате [min, max] по валютам.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public const AMOUNT_RANGES = [
        'rub' => [1500, 45000],
        'kzt' => [8000, 250000],
        'eur' => [20, 600],
        'tjs' => [200, 6000],
        'kgs' => [1500, 45000],
        'uah' => [800, 20000],
        'usd' => [20, 600],
        'azn' => [30, 1000],
        'try' => [700, 18000],
        'idr' => [300000, 9000000],
        'pln' => [80, 2500],
    ];

    /**
     * Наполняет кэш курсами для всех фиатных валют и рыночных источников,
     * чтобы OrderDetailProvider/MarketService могли работать offline.
     */
    public static function seedMarketPrices(): void
    {
        $markets = [MarketEnum::BYBIT, MarketEnum::BINANCE, MarketEnum::MANUAL];

        foreach (self::SELL_RATES as $code => $sell) {
            if (! Currency::isCurrency($code)) {
                continue;
            }

            $sellUnits = Money::fromPrecision((string) $sell, $code)->toUnits();
            // Небольшой спред: покупка чуть дороже продажи.
            $buyUnits = Money::fromPrecision((string) round($sell * 1.008, 6), $code)->toUnits();

            foreach ($markets as $market) {
                MarketStore::putPrice(new Currency($code), $market, $buyUnits, $sellUnits);
            }
        }
    }

    /**
     * Правдоподобная сумма заказа в фиате (целое число, кратно «красивому» шагу).
     */
    public static function realisticFiatAmount(string $currencyCode): int
    {
        $range = self::AMOUNT_RANGES[strtolower($currencyCode)] ?? [1000, 30000];
        $value = random_int($range[0], $range[1]);

        $step = match (true) {
            $value >= 1_000_000 => 50_000,
            $value >= 100_000 => 5_000,
            $value >= 10_000 => 500,
            $value >= 1_000 => 100,
            default => 5,
        };

        $rounded = (int) (round($value / $step) * $step);

        return max($range[0], $rounded);
    }

    /**
     * @return array<int, string>
     */
    public static function firstNames(): array
    {
        return [
            'Александр', 'Дмитрий', 'Максим', 'Сергей', 'Андрей', 'Алексей', 'Артём', 'Илья',
            'Кирилл', 'Михаил', 'Никита', 'Матвей', 'Роман', 'Егор', 'Арсений', 'Иван',
            'Анна', 'Мария', 'Елена', 'Дарья', 'Алина', 'Ирина', 'Екатерина', 'Ольга',
            'Наталья', 'Виктория', 'Полина', 'Ксения', 'София', 'Юлия', 'Татьяна', 'Светлана',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function lastNames(): array
    {
        return [
            'Иванов', 'Смирнов', 'Кузнецов', 'Попов', 'Васильев', 'Петров', 'Соколов', 'Михайлов',
            'Новиков', 'Фёдоров', 'Морозов', 'Волков', 'Алексеев', 'Лебедев', 'Семёнов', 'Егоров',
            'Павлов', 'Козлов', 'Степанов', 'Николаев', 'Орлов', 'Андреев', 'Макаров', 'Никитин',
        ];
    }

    public static function fullName(): string
    {
        $first = self::firstNames();
        $last = self::lastNames();

        return $first[array_rand($first)].' '.$last[array_rand($last)];
    }

    /**
     * Инициалы вида «Иван И.» для реквизитов.
     */
    public static function initials(): string
    {
        $first = self::firstNames();
        $last = self::lastNames();

        return $first[array_rand($first)].' '.mb_substr($last[array_rand($last)], 0, 1).'.';
    }

    /**
     * @return array<int, string>
     */
    public static function bankNames(): array
    {
        return [
            'Сбербанк', 'Тинькофф Банк', 'ВТБ', 'Альфа-Банк', 'Райффайзенбанк',
            'Газпромбанк', 'Открытие', 'Совкомбанк', 'Росбанк', 'Почта Банк',
            'МКБ', 'Уралсиб', 'ОЗОН Банк', 'ЮMoney',
        ];
    }

    public static function bankName(): string
    {
        $banks = self::bankNames();

        return $banks[array_rand($banks)];
    }

    /**
     * @return array<int, string>
     */
    public static function companyNames(): array
    {
        return [
            'Магазин Электроники', 'Книжный Мир', 'Спортивный Клуб', 'Кафе Уют', 'Автозапчасти Плюс',
            'Цветочный Рай', 'Детский Мир', 'Продукты 24', 'Техносервис', 'Модный Стиль',
            'Интернет-магазин Техники', 'Онлайн-аптека', 'Строительные Материалы', 'Одежда и Обувь',
            'Дом и Сад', 'Красота и Здоровье', 'Спорт и Отдых', 'Бизнес и Офис', 'Хобби и Творчество',
            'GameShop', 'CryptoPay Store', 'FastMart', 'DigitalGoods', 'PremiumMarket',
        ];
    }

    /**
     * @return array<int, array{country_code: string, country: string, region: string, city: string}>
     */
    public static function locations(): array
    {
        return [
            ['country_code' => 'RU', 'country' => 'Россия', 'region' => 'Москва', 'city' => 'Москва'],
            ['country_code' => 'RU', 'country' => 'Россия', 'region' => 'Санкт-Петербург', 'city' => 'Санкт-Петербург'],
            ['country_code' => 'RU', 'country' => 'Россия', 'region' => 'Свердловская область', 'city' => 'Екатеринбург'],
            ['country_code' => 'RU', 'country' => 'Россия', 'region' => 'Новосибирская область', 'city' => 'Новосибирск'],
            ['country_code' => 'KZ', 'country' => 'Казахстан', 'region' => 'Алматы', 'city' => 'Алматы'],
            ['country_code' => 'KZ', 'country' => 'Казахстан', 'region' => 'Астана', 'city' => 'Астана'],
            ['country_code' => 'UA', 'country' => 'Украина', 'region' => 'Киев', 'city' => 'Киев'],
            ['country_code' => 'TR', 'country' => 'Турция', 'region' => 'Стамбул', 'city' => 'Стамбул'],
            ['country_code' => 'GE', 'country' => 'Грузия', 'region' => 'Тбилиси', 'city' => 'Тбилиси'],
            ['country_code' => 'AE', 'country' => 'ОАЭ', 'region' => 'Дубай', 'city' => 'Дубай'],
        ];
    }

    /**
     * @return array<int, array{ua: string, browser: string, os: string, device: string}>
     */
    public static function userAgents(): array
    {
        return [
            [
                'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'browser' => 'Chrome', 'os' => 'Windows', 'device' => 'desktop',
            ],
            [
                'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
                'browser' => 'Safari', 'os' => 'macOS', 'device' => 'desktop',
            ],
            [
                'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
                'browser' => 'Safari', 'os' => 'iOS', 'device' => 'mobile',
            ],
            [
                'ua' => 'Mozilla/5.0 (Linux; Android 13; SM-A536B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
                'browser' => 'Chrome', 'os' => 'Android', 'device' => 'mobile',
            ],
            [
                'ua' => 'Mozilla/5.0 (X11; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0',
                'browser' => 'Firefox', 'os' => 'Linux', 'device' => 'desktop',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function androidDeviceNames(): array
    {
        return [
            'Samsung Galaxy A54', 'Samsung Galaxy S23', 'Xiaomi Redmi Note 12', 'Xiaomi 13',
            'Huawei P60', 'Google Pixel 7a', 'OnePlus 11', 'Realme 11 Pro', 'Motorola Edge 40',
            'Honor 90', 'Tecno Camon 20', 'POCO X5 Pro',
        ];
    }

    public static function ip(): string
    {
        return random_int(1, 223).'.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254);
    }

    public static function txHash(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function transactionId(): string
    {
        return (string) random_int(1_000_000, 999_999_999);
    }

    public static function androidId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Генерирует значение реквизита нужного типа.
     */
    public static function detailValue(DetailType $type, string $currencyCode): string
    {
        return match ($type) {
            DetailType::CARD => self::generateCard(),
            DetailType::PHONE, DetailType::MOBILE_COMMERCE => self::phoneForCurrency($currencyCode),
            DetailType::ACCOUNT_NUMBER => self::accountNumber(),
            DetailType::IBAN_UAH => self::ibanUah(),
            DetailType::E_COM => self::generateCard(),
        };
    }

    public static function generateCard(): string
    {
        // Валидный по Луну номер: 60% МИР (2200), иначе Visa (4).
        $prefix = random_int(0, 9) < 6 ? '2200' : '4';
        $base = $prefix;
        while (strlen($base) < 15) {
            $base .= (string) random_int(0, 9);
        }

        return $base.self::luhnChecksumDigit($base);
    }

    public static function phoneForCurrency(string $currencyCode): string
    {
        return match (strtolower($currencyCode)) {
            'kzt' => '77'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'uah' => '380'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'try' => '90'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            default => '79'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
        };
    }

    public static function accountNumber(): string
    {
        return '408'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT)
            .str_pad((string) random_int(0, 99999999999), 12, '0', STR_PAD_LEFT);
    }

    public static function ibanUah(): string
    {
        return 'UA'.str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT)
            .'305299'.str_pad((string) random_int(0, 99999999999999999), 19, '0', STR_PAD_LEFT);
    }

    public static function luhnChecksumDigit(string $number): string
    {
        $sum = 0;
        $alt = true;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    /**
     * TRON (TRC20) адрес для вывода средств.
     */
    public static function tronAddress(): string
    {
        return 'T'.Str::random(33);
    }

    /**
     * Текст банковского SMS/PUSH о зачислении с суммой заказа.
     */
    public static function bankMessage(int $amount, string $last4): string
    {
        $balance = number_format(random_int(5_000, 900_000), 0, '.', ' ');
        $sender = self::fullName();

        $templates = [
            "Perevod {$amount}r ot {$sender}. Balans: {$balance}r. Karta *{$last4}",
            "Postuplenie {$amount} RUB na kartu *{$last4}. Dostupno {$balance} RUB",
            "Zachislenie *{$last4} +{$amount}r; ostatok {$balance}r",
            "Вы получили перевод {$amount} ₽ на карту *{$last4}. Баланс {$balance} ₽",
            "+{$amount} руб. Перевод от {$sender}. Доступно: {$balance} руб",
            "SBP: postuplenie {$amount}.00 RUB. Schet *{$last4}. Balans {$balance}.00",
        ];

        return $templates[array_rand($templates)];
    }

    /**
     * Создаёт временный корректный PNG-файл и оборачивает его в UploadedFile
     * (для чеков споров и банковских выписок).
     */
    public static function pngUploadedFile(string $originalName): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.strtolower(Str::random(32)).'.png';

        // 1x1 прозрачный PNG.
        file_put_contents(
            $path,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        return new UploadedFile($path, $originalName, 'image/png', null, true);
    }

    /**
     * Минимальный Tiptap/JSON + HTML для новостного поста.
     *
     * @return array{json: array<string, mixed>, html: string}
     */
    public static function newsContent(string $title, string $paragraph): array
    {
        return [
            'json' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'heading',
                        'attrs' => ['level' => 2],
                        'content' => [['type' => 'text', 'text' => $title]],
                    ],
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $paragraph]],
                    ],
                ],
            ],
            'html' => '<h2>'.e($title).'</h2><p>'.e($paragraph).'</p>',
        ];
    }
}
