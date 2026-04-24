<?php

return [
    /*
     * Keys match NotificationEvent values (e.g. withdrawal.requested).
     * Laravel trans() treats dots as nesting, so nested arrays are required here.
     */
    'events' => [
        'withdrawal' => [
            'requested' => 'Запрос на вывод средств',
        ],
        'order' => [
            'assigned' => 'Новая сделка',
        ],
        'dispute' => [
            'opened' => 'Открыт спор',
        ],
        'trust' => [
            'balance' => [
                'low' => 'Низкий траст-баланс',
            ],
        ],
        'message' => [
            'received' => 'Новое сообщение (SMS/PUSH)',
        ],
    ],
    'message_scopes' => [
        'all' => 'Для всех сообщений',
        'with_order' => 'Только для сообщений с прикрепленной сделкой',
    ],
    'templates' => [
        'withdrawal_requested' => [
            'title' => 'Запрос на вывод средств',
            'body' => 'Пользователь :user_email запросил вывод :amount :currency. ID заявки: :invoice_id.',
        ],
        'order_assigned' => [
            'title' => 'Новая сделка',
            'body' => 'Вам назначена новая сделка :order_uuid на сумму :amount :currency.',
        ],
        'dispute_opened' => [
            'title' => 'Открыт спор',
            'body' => 'По сделке :order_uuid открыт спор. ID спора: :dispute_id.',
        ],
        'trust_balance_low' => [
            'title' => 'Низкий траст-баланс',
            'body' => 'Ваш траст-баланс снизился до :current_balance :currency. Пополните средства, чтобы избежать остановки работы.',
        ],
        'message_received' => [
            'title' => 'Новое сообщение (SMS/PUSH)',
            'body' => '',
        ],
    ],
    'telegram' => [
        'start' => [
            'missing_token' => 'Для привязки бота перейдите по ссылке из панели и нажмите Start.',
            'success' => 'Бот успешно привязан. Теперь уведомления будут приходить сюда.',
            'invalid_token' => 'Ссылка привязки недействительна или устарела.',
            'error' => 'Не удалось привязать бота. Попробуйте позже.',
        ],
    ],
];
