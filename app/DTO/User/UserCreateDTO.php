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
        public ?int $agent_id = null,
        public float $agent_commission_percentage = 0.20,
        public ?string $telegram_username = null,
    ) {}

    public static function makeFromRequest(array $data): static
    {
        return new static(
            login: strtolower($data['login']),
            password: $data['password'],
            role_id: (int) $data['role_id'],
            team_leader_id: $data['team_leader_id'] ?? null,
            agent_id: $data['agent_id'] ?? null,
            agent_commission_percentage: isset($data['agent_commission_percentage'])
                ? (float) $data['agent_commission_percentage']
                : 0.20,
            telegram_username: self::normalizeTelegramUsername($data['telegram_username'] ?? null),
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
