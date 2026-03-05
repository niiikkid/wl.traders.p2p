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
    ) {}

    public static function makeFromRequest(array $data): static
    {
        return new static(
            login: strtolower($data['login']),
            password: $data['password'],
            role_id: (int) $data['role_id'],
            team_leader_id: $data['team_leader_id'] ?? null,
        );
    }
}


