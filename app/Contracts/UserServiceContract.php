<?php

namespace App\Contracts;

use App\DTO\User\UserCreateDTO;
use App\DTO\User\UserUpdateDTO;
use App\Models\User;

interface UserServiceContract
{
    public function create(UserCreateDTO $data): User;

    public function update(UserUpdateDTO $data, User $user): User;
}


