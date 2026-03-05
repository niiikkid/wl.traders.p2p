<?php

namespace Database\Factories;

use Illuminate\Support\Str;

/**
 * Фабрика для рандомизации имен и email пользователей
 */
class UserRandomizerFactory
{
    /**
     * Список коротких тестовых имен
     */
    protected static array $shortNames = [
        'Alex', 'Bob', 'Carl', 'Dave', 'Earl', 'Frank', 'Greg', 'Hank', 'Ivan', 'Jack',
        'Kent', 'Liam', 'Mike', 'Neal', 'Owen', 'Paul', 'Quin', 'Ross', 'Stan', 'Tom',
        'Ursa', 'Vick', 'Will', 'Xavi', 'Yuri', 'Zack', 'Adam', 'Bill', 'Chad', 'Dean',
        'Emma', 'Faye', 'Gina', 'Hope', 'Iris', 'Jane', 'Kate', 'Lisa', 'Mary', 'Nina',
        'Olga', 'Pina', 'Rose', 'Sara', 'Tina', 'Uma', 'Vera', 'Wren', 'Xena', 'Yara',
        'Zara', 'Alis', 'Bree', 'Cora', 'Dana', 'Elsa', 'Fran', 'Gwen', 'Hera'
    ];

    /**
     * Список доменов для email
     */
    protected static array $domains = [
        'test.com', 'example.com', 'demo.org', 'sample.net', 'training.dev', 'sandbox.io',
        'dev.com', 'testmail.org', 'example.org', 'demo.net', 'testing.com', 'dev-mail.com'
    ];

    /**
     * Генерирует случайное короткое имя с числовым суффиксом
     */
    public static function generateRandomName(): string
    {
        return static::$shortNames[array_rand(static::$shortNames)] . mt_rand(100, 999);
    }

    /**
     * Генерирует случайный email на основе имени
     */
    public static function generateRandomEmail(?string $name = null): string
    {
        $name = $name ?? static::generateRandomName();
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . mt_rand(1, 999);
        $domain = static::$domains[array_rand(static::$domains)];
        
        return $username . '@' . $domain;
    }

    /**
     * Возвращает массив с новыми данными для пользователя
     */
    public static function getRandomUserData(): array
    {
        $name = static::generateRandomName();
        
        return [
            'name' => $name,
            'email' => static::generateRandomEmail($name)
        ];
    }
} 