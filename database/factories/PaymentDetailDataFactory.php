<?php

namespace Database\Factories;

use App\Enums\DetailType;

/**
 * Фабрика для генерации данных платежных реквизитов
 */
class PaymentDetailDataFactory
{
    /**
     * Список имен для банковских карт
     *
     * @var array
     */
    protected static array $bankCardNames = [
        'Карта жены', 'Карта друга', 'Карта тещи', 'Сосед сверху', 
        'Семейная карта', 'Карта брата', 'Карта сестры', 
        'Рабочая карта', 'Запасная карта', 'Основная карта',
        'Санек ПТУ', 'Вован из гаража', 'Карта батя', 'Димон',
        'Карта на пиво', 'Карта на ипотеку', 'Серега сосед', 
        'Карта квартирант', 'Карта для отпуска', 'Карта друган'
    ];

    /**
     * Список имен для мобильных платежей
     *
     * @var array
     */
    protected static array $phonePaymentNames = [
        'Телефон жены', 'Телефон соседа', 'Сосед с 5 этажа', 'Телефон кума', 
        'Вадик связист', 'Телефон шурина', 'Левый номер', 'Запасной телефон',
        'Рабочий телефон', 'Телефон подруги', 'Телефон коллеги', 'Телефон деда',
        'Телефон бати', 'Кирилл программист', 'Братан с района', 'Макс сантехник',
        'Телефон работяга', 'Сергей водила', 'Леха электрик', 'Петрович'
    ];
    
    /**
     * Список имен для банковских счетов
     * 
     * @var array
     */
    protected static array $accountNames = [
        'Счет кума', 'Счет соседа', 'Счет Васяна', 'Счет родственника', 
        'Счет шурина', 'Счет свата', 'Счет друга', 
        'Резервный счет', 'Запасной счет', 'Рабочий счет',
        'Серый счет', 'Белый счет', 'Счет на хату', 'Счет на тачку',
        'ИП Толик', 'ООО "Гараж"', 'Счет Саныча', 'Счет Михалыча',
        'На черный день', 'Заначка'
    ];
    
    /**
     * Список распространенных инициалов
     * 
     * @var array
     */
    protected static array $initials = [
        'АБВ', 'ИВА', 'ПЕТ', 'СИД', 'КУЗ', 'СМИ', 'НОВ', 'ИВА', 'МИХ', 'АЛЕ',
        'МАК', 'ПАВ', 'ГРИ', 'ФЕД', 'СЕР', 'АНД', 'ЕВГ', 'АРТ', 'ВЛА', 'ДМИ',
        'А.С.', 'И.И.', 'П.А.', 'С.В.', 'К.Н.', 'В.В.', 'Н.И.', 'М.А.', 'Е.П.', 'Д.С.',
        'А.В.', 'И.А.', 'П.В.', 'С.А.', 'К.А.', 'В.И.', 'Н.В.', 'М.В.', 'Е.В.', 'Д.А.'
    ];

    /**
     * Генерирует случайный номер карты
     * 
     * @return string
     */
    public static function generateCardNumber(): string
    {
        // Создаем BIN (первые 6 цифр карты)
        $binPrefixes = [
            '4276', '4477', '4817', // Visa 1
            '5469', '5479', '5336', // MasterCard 1
            '2200', '2202', '2204', // Мир 1
            '5536', '5538', '5213', // MasterCard 2
            '4377', '4279', '4189', // Visa 2
            '5212', '5299', '5294', // MasterCard 3
            '4261', '4833', '4154', // Visa 3
            '2201', '2203', '2205'  // Мир 2
        ];
        
        $bin = $binPrefixes[array_rand($binPrefixes)];
        
        // Дополняем номер случайными цифрами (всего 16 цифр в номере карты)
        $remainingLength = 16 - strlen($bin);
        for ($i = 0; $i < $remainingLength; $i++) {
            $bin .= mt_rand(0, 9);
        }
        
        // Маскируем центральную часть
        return substr($bin, 0, 6) . '******' . substr($bin, -4);
    }
    
    /**
     * Генерирует случайный российский номер телефона
     * 
     * @return string
     */
    public static function generatePhoneNumber(): string
    {
        $operators = [
            '903', '905', '906', '909', '911', '912', '915', '916', '919', 
            '920', '925', '926', '929', 
            '962', '963', '964', '965', '966', '967', '968', '969',
            '902', '904', '908', '950', '951', '952', '953', 
            '900', '901', '904', '910', '914', '918', '921', '922', '923', 
            '924', '927', '928', '929', '930', '934', '936', '937', '938', '939',
            '999', '996', '995', '994', '993', '992', '991', '958', '950', '953'
        ];
        
        $operator = $operators[array_rand($operators)];
        $phone = '+7' . $operator;
        
        // Добавляем 7 случайных чисел, чтобы сделать полный номер
        for ($i = 0; $i < 7; $i++) {
            $phone .= mt_rand(0, 9);
        }
        
        return $phone;
    }
    
    /**
     * Генерирует случайный номер счета
     * 
     * @return string
     */
    public static function generateAccountNumber(): string
    {
        // Первые 3 цифры - код валюты (810 - рубли)
        $currencyCode = '810';
        
        // 5 цифр - код отделения банка
        $bankBranch = sprintf('%05d', mt_rand(1, 99999));
        
        // 12 цифр - номер счета
        $accountNumber = '';
        for ($i = 0; $i < 12; $i++) {
            $accountNumber .= mt_rand(0, 9);
        }
        
        return $currencyCode . $bankBranch . $accountNumber;
    }
    
    /**
     * Возвращает массив с новыми данными для платежных реквизитов определенного типа
     * 
     * @param DetailType $detailType тип реквизита
     * @return array
     */
    public static function getRandomPaymentDetailData(DetailType $detailType): array
    {
        $initials = static::$initials[array_rand(static::$initials)];
        
        switch ($detailType) {
            case DetailType::CARD:
                $name = static::$bankCardNames[array_rand(static::$bankCardNames)];
                $detail = static::generateCardNumber();
                break;
                
            case DetailType::PHONE:
                $name = static::$phonePaymentNames[array_rand(static::$phonePaymentNames)];
                $detail = static::generatePhoneNumber();
                break;
                
            case DetailType::ACCOUNT_NUMBER:
                $name = static::$accountNames[array_rand(static::$accountNames)];
                $detail = static::generateAccountNumber();
                break;
                
            default:
                $name = 'Неизвестный тип' . mt_rand(100, 999);
                $detail = 'unknown-' . mt_rand(1000000, 9999999);
        }
        
        return [
            'name' => $name,
            'detail' => $detail,
            'initials' => $initials
        ];
    }
} 