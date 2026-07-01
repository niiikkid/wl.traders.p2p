<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'email' => $this->actor->email,
            ] : null,
            'impersonator' => $this->impersonator ? [
                'id' => $this->impersonator->id,
                'email' => $this->impersonator->email,
            ] : null,
            'actor_role' => $this->actor_role,
            'action' => $this->action?->value,
            'action_label' => $this->actionLabel(),
            'subject_type' => $this->subject_type,
            'subject_label' => $this->subjectLabel(),
            'subject_id' => $this->subject_id,
            'subject_uuid' => $this->subject_uuid,
            'route_name' => $this->route_name,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'changes' => $this->changes ?? [],
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function actionLabel(): string
    {
        return match ($this->action?->value) {
            'created' => 'Создание',
            'updated' => 'Изменение',
            'deleted' => 'Удаление',
            'restored' => 'Восстановление',
            'force_deleted' => 'Полное удаление',
            'role_attached' => 'Роль назначена',
            'role_detached' => 'Роль снята',
            default => (string) $this->action?->value,
        };
    }

    private function subjectLabel(): string
    {
        return match ($this->subject_type) {
            'user' => 'Пользователь',
            'role' => 'Роль',
            'merchant' => 'Мерчант',
            'payment_detail' => 'Реквизит',
            'payment_gateway' => 'Банк',
            'order' => 'Сделка',
            'payout' => 'Выплата',
            'dispute' => 'Спор',
            'wallet' => 'Кошелек',
            'transaction' => 'Транзакция',
            'invoice' => 'Инвойс',
            'setting' => 'Настройка',
            'open_ai_setting' => 'OpenAI',
            'anti_fraud_setting' => 'Антифрод',
            'telegram_bot_setting' => 'Telegram Bot',
            default => (string) $this->subject_type,
        };
    }
}
