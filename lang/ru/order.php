<?php

declare(strict_types=1);

return [
    'status' => [
        'success' => 'Завершён',
        'fail' => 'Отменён',
        'pending' => 'В обработке',
    ],
    'sub_status' => [
        'accepted' => 'Закрыт вручную',
        'successfully_paid' => 'Оплачен автоматически',
        'successfully_paid_by_resolved_dispute' => 'Оплачен по решённому спору',
        'waiting_details_to_be_selected' => 'Ожидает выбора реквизитов',
        'waiting_for_payment' => 'Ожидает оплаты',
        'waiting_for_dispute_to_be_resolved' => 'Ожидает решения по спору',
        'canceled_by_dispute' => 'Отменён по спору',
        'expired' => 'Отменён по истечению времени',
        'cancelled' => 'Отменён вручную',
    ],
];
