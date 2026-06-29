<?php

namespace App\Contracts;

use App\DTO\User\UserCreateDTO;
use App\DTO\User\UserUpdateDTO;
use App\Models\User;

interface UserServiceContract
{
    public function create(UserCreateDTO $data): User;

    public function update(UserUpdateDTO $data, User $user): User;

    /**
     * @return array{status: string}
     */
    public function requestAvatarGeneration(User $user): array;

    /**
     * @return array{caption: string, avatar_url: string}
     */
    public function regenerateAvatar(User $user): array;
}
