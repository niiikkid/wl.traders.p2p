<?php

declare(strict_types=1);

return [
    'status' => [
        'provisioning' => 'Создаётся',
        'provisioning_failed' => 'Не удалось создать',
        'pending' => 'В обработке',
        'success' => 'Успешно',
        'fail' => 'Неуспешно',
    ],
    'sub_status' => [
        'provider_selection' => 'Подбор провайдера',
        'failed_to_create' => 'Не удалось создать ни у одного провайдера',
        'successfully_paid' => 'Оплачена',
        'waiting_for_payment' => 'Ожидает оплаты',
        'cancelled' => 'Отменена',
        'successfully_paid_by_resolved_dispute' => 'Оплачена по спору',
        'waiting_for_dispute_to_be_resolved' => 'Ожидает решения спора',
        'canceled_by_dispute' => 'Отменена по спору',
    ],
    'dispute_status' => [
        'opened' => 'Открыт',
        'accepted' => 'Принят',
        'rejected' => 'Отклонён',
    ],
    'transaction_status' => [
        'opened' => 'Открыта у провайдера',
        'failed_to_open' => 'Не удалось открыть',
        'cancelled' => 'Отменена',
        'accepted' => 'Принята в работу',
    ],
    'payment_method' => [
        'card' => 'Карта',
        'sbp' => 'СБП',
        'mobile_commerce' => 'Мобильная коммерция',
        'iban_uah' => 'IBAN (UAH)',
        'e-com' => 'E-COM',
    ],
];
