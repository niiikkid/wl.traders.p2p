<?php

return [
    'events' => [
        'withdrawal.requested' => 'Запрос на вывод средств',
        'order.assigned' => 'Новая сделка',
        'dispute.opened' => 'Открыт спор',
    ],
    'channels' => [
        'in_app' => 'В панели',
        'telegram' => 'Telegram',
    ],
    'delivery_statuses' => [
        'pending' => 'В очереди',
        'delivered' => 'Доставлено',
        'failed' => 'Ошибка',
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
