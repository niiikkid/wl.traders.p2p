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
        // Создаем администратора
        Transaction::run(function () {
            $admin = User::create([
                'name' => 'Администратор',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'apk_access_token' => strtolower(Str::random(32)),
                'api_access_token' => strtolower(Str::random(32)),
                'avatar_uuid' => 'admin@example.com',
                'avatar_style' => 'adventurer',
                'is_online' => true,
            ]);

            $admin->assignRole('Super Admin');

            services()->wallet()->create($admin);
        });

        // Создаем трейдера
        Transaction::run(function () {
            $trader = User::create([
                'name' => 'Трейдер',
                'email' => 'trader@example.com',
                'password' => Hash::make('password'),
                'apk_access_token' => strtolower(Str::random(32)),
                'api_access_token' => strtolower(Str::random(32)),
                'avatar_uuid' => 'trader@example.com',
                'avatar_style' => 'adventurer',
                'is_online' => true,
            ]);

            $trader->assignRole('Trader');

            services()->wallet()->create($trader);
        });

        // Создаем мерчанта
        Transaction::run(function () {
            $merchant = User::create([
                'name' => 'Мерчант',
                'email' => 'merchant@example.com',
                'password' => Hash::make('password'),
                'apk_access_token' => strtolower(Str::random(32)),
                'api_access_token' => strtolower(Str::random(32)),
                'avatar_uuid' => 'merchant@example.com',
                'avatar_style' => 'adventurer',
                'is_online' => true,
            ]);

            $merchant->assignRole('Merchant');

            services()->wallet()->create($merchant);
        });

        // Создаем тимлидера, если такая роль существует
        if (Role::where('name', 'Team Leader')->exists()) {
            Transaction::run(function () {
                $teamLeader = User::create([
                    'name' => 'Тимлидер',
                    'email' => 'teamleader@example.com',
                    'password' => Hash::make('password'),
                    'apk_access_token' => strtolower(Str::random(32)),
                    'api_access_token' => strtolower(Str::random(32)),
                    'avatar_uuid' => 'teamleader@example.com',
                    'avatar_style' => 'adventurer',
                'is_online' => true,
                ]);

                $teamLeader->assignRole('Team Leader');

                services()->wallet()->create($teamLeader);
            });
        }
        
        // Создаем саппорта, если такая роль существует
        if (Role::where('name', 'Support')->exists()) {
            Transaction::run(function () {
                $support = User::create([
                    'name' => 'Саппорт',
                    'email' => 'support@example.com',
                    'password' => Hash::make('password'),
                    'apk_access_token' => strtolower(Str::random(32)),
                    'api_access_token' => strtolower(Str::random(32)),
                    'avatar_uuid' => 'support@example.com',
                    'avatar_style' => 'adventurer',
                'is_online' => true,
                ]);

                $support->assignRole('Support');

                services()->wallet()->create($support);
            });
        }
    }
} 