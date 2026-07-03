<?php

namespace Database\Seeders;

use App\Models\User;
use App\Utils\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createUser('Администратор', 'admin', 'Super Admin');
        $this->createUser('Трейдер', 'trader', 'Trader');
        $this->createUser('Мерчант', 'merchant', 'Merchant');
        $this->createUser('Тимлидер', 'teamleader', 'Team Leader');
        $this->createUser('Саппорт', 'support', 'Support');
    }

    /**
     * Idempotently create a user with the given role and a wallet.
     * The `email` column is used as the login identifier, not a mailbox.
     */
    private function createUser(string $name, string $login, string $role): void
    {
        if (! Role::query()->where('name', $role)->exists()) {
            return;
        }

        Transaction::run(function () use ($name, $login, $role) {
            $user = User::firstOrCreate(
                ['email' => $login],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'apk_access_token' => strtolower(Str::random(32)),
                    'api_access_token' => strtolower(Str::random(32)),
                    'is_online' => true,
                ]
            );

            if (! $user->wasRecentlyCreated) {
                return;
            }

            $user->assignRole($role);

            services()->wallet()->create($user);
        });
    }
}
