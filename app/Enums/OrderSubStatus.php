<?php

namespace App\Enums;

use App\Traits\Enumable;

enum OrderSubStatus: string
{
    use Enumable;

    case ACCEPTED = 'accepted'; //закрыт руками
    case SUCCESSFULLY_PAID = 'successfully_paid'; //закрыт автоматикой
    case SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE = 'successfully_paid_by_resolved_dispute'; //закрыт принятым спором
    case WAITING_FOR_DETAILS_TO_BE_SELECTED = 'waiting_details_to_be_selected'; //ждет выбора реквизитов
    case WAITING_FOR_PAYMENT = 'waiting_for_payment';//ждет платежа
    case WAITING_FOR_DISPUTE_TO_BE_RESOLVED = 'waiting_for_dispute_to_be_resolved';//ждет решения спора
    case CANCELED_BY_DISPUTE = 'canceled_by_dispute';//отменен спором
    case EXPIRED = 'expired';//отменен по истечению времени
    case CANCELED = 'cancelled';//отменен руками
}
