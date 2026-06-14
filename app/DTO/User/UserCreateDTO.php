<?php

namespace App\DTO\User;

use App\DTO\BaseDTO;

readonly class UserCreateDTO extends BaseDTO
{
    public function __construct(
        public string $login,
        public string $password,
        public int $role_id,
        public ?int $team_leader_id = null,
        public ?string $telegram_username = null,
        public ?string $team_leader_insurance_mode = null,
        public ?int $team_leader_trader_limit = null,
        public ?int $team_leader_reserve_balance_limit = null,
        public ?int $team_leader_reserve_stop_threshold = null,
    ) {}

    public static function makeFromRequest(array $data): static
    {
        return new static(
            login: strtolower($data['login']),
            password: $data['password'],
            role_id: (int) $data['role_id'],
            team_leader_id: $data['team_leader_id'] ?? null,
            telegram_username: self::normalizeTelegramUsername($data['telegram_username'] ?? null),
            team_leader_insurance_mode: $data['team_leader_insurance_mode'] ?? null,
            team_leader_trader_limit: isset($data['team_leader_trader_limit']) ? (int) $data['team_leader_trader_limit'] : null,
            team_leader_reserve_balance_limit: isset($data['team_leader_reserve_balance_limit']) ? (int) $data['team_leader_reserve_balance_limit'] : null,
            team_leader_reserve_stop_threshold: isset($data['team_leader_reserve_stop_threshold']) ? (int) $data['team_leader_reserve_stop_threshold'] : null,
        );
    }

    private static function normalizeTelegramUsername(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return ltrim($value, '@');
    }
}
