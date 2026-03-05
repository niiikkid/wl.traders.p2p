<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\DTO\User\UserCreateDTO;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use App\Enums\DetailType;
use App\DTO\PaymentDetail\PaymentDetailCreateDTO;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Enums\BalanceType;
use App\DTO\Merchant\MerchantCreateDTO;
use App\Models\Merchant as MerchantModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use App\Exceptions\DisputeException;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;

class GenerateTestDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-test-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Генерирует тестовые данные для проекта';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Начинаем генерацию тестовых данных...');

        $words = [
            'alex', 'mike', 'john', 'david', 'steve', 'paul', 'mark', 'tom', 'nick', 'joe',
            'anna', 'lisa', 'mary', 'sara', 'kate', 'jane', 'emma', 'lucy', 'rose', 'amy',
            'max', 'dan', 'sam', 'leo', 'ben', 'jake', 'ryan', 'luke', 'noah', 'jack',
            'sophia', 'olivia', 'ava', 'isabella', 'mia', 'charlotte', 'amelia', 'harper', 'evelyn', 'abigail',
            'gamer', 'player', 'pro', 'master', 'boss', 'king', 'queen', 'ninja', 'warrior', 'hunter',
            'cool', 'swift', 'sharp', 'bright', 'dark', 'light', 'fast', 'slow', 'big', 'small'
        ];

        $rolesMap = [
            'merchant' => 'Merchant',
            'trader' => 'Trader',
            'support' => 'Support',
        ];

        $roleIds = [];
        foreach ($rolesMap as $key => $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                $this->warn("Роль '{$roleName}' не найдена. Пропускаю создание пользователей этой роли.");
                continue;
            }
            $roleIds[$key] = (int) $role->id;
        }

        // 3 мерчанта
        if (isset($roleIds['merchant'])) {
            for ($i = 1; $i <= 3; $i++) {
                // Генерируем уникальный логин
                do {
                    $login = $words[array_rand($words)] . random_int(1, 999999);
                } while (User::where('email', strtolower($login))->exists());
                services()->user()->create(new UserCreateDTO(
                    login: $login,
                    password: 'password',
                    role_id: $roleIds['merchant'],
                ));
            }
        }

        // 30 трейдеров (увеличено в 3 раза)
        if (isset($roleIds['trader'])) {
            for ($i = 1; $i <= 30; $i++) {
                // Генерируем уникальный логин
                do {
                    $login = $words[array_rand($words)] . random_int(1, 999999);
                } while (User::where('email', strtolower($login))->exists());
                services()->user()->create(new UserCreateDTO(
                    login: $login,
                    password: 'password',
                    role_id: $roleIds['trader'],
                ));
            }
        }

        // 1 саппорт
        if (isset($roleIds['support'])) {
            // Генерируем уникальный логин
            do {
                $login = $words[array_rand($words)] . random_int(1, 999999);
            } while (User::where('email', strtolower($login))->exists());
            services()->user()->create(new UserCreateDTO(
                login: $login,
                password: 'password',
                role_id: $roleIds['support'],
            ));
        }

        // Устанавливаем is_online = 1 для всех трейдеров и администраторов
        User::query()
            ->role(['Trader', 'Super Admin'])
            ->update(['is_online' => 1]);

        // Этап 2. Создание устройств для всех пользователей с трейдерским функционалом
        // (включая администраторов, т.к. у них есть трейдерский функционал)
        $androidDeviceNames = [
            'Samsung Galaxy A51',
            'Xiaomi Redmi Note 9',
            'Huawei P30 Lite',
            'Google Pixel 4a',
            'OnePlus Nord N10',
            'Samsung Galaxy S10e',
            'Xiaomi Mi 9T',
            'Realme 7',
            'Motorola Moto G7',
            'Nokia 7.2',
        ];

        // Собираем всех пользователей с ролями Trader и Super Admin
        $eligibleUsers = User::query()
            ->role(['Trader', 'Super Admin'])
            ->get();

        foreach ($eligibleUsers as $eligibleUser) {
            // Пропускаем, если у пользователя уже есть устройство(а)
            $hasDevice = UserDevice::query()->where('user_id', $eligibleUser->id)->exists();
            if ($hasDevice) {
                continue;
            }

            $deviceName = $androidDeviceNames[array_rand($androidDeviceNames)];
            services()->device()->create($eligibleUser->id, $deviceName);
        }

        // Этап 3. Создание платежных реквизитов: 5 на пользователя (3 карты, 2 телефона)
        $this->info('Создаю реквизиты для трейдеров и администраторов...');

        $eligibleUsers = User::query()
            ->role(['Trader', 'Super Admin'])
            ->get();

        // Выбор активных шлюзов, поддерживающих нужные типы реквизитов
        $activeGateways = queries()->paymentGateway()->getAllActive();
        $rubGateways = $activeGateways->filter(fn($pg) => strtolower($pg->currency->getCode()) === 'rub');
        $cardGateways = $rubGateways->filter(fn($pg) => in_array(DetailType::CARD, $pg->detail_types ?? []))->pluck('id')->values()->all();
        $phoneGateways = $rubGateways->filter(fn($pg) => in_array(DetailType::PHONE, $pg->detail_types ?? []))->pluck('id')->values()->all();

        foreach ($eligibleUsers as $user) {
            $userDeviceId = UserDevice::where('user_id', $user->id)->value('id');

            if (! $userDeviceId) {
                continue;
            }

            // 3 карты: 2 активные, 1 выключена
            for ($i = 0; $i < 3; $i++) {
                if (empty($cardGateways)) break;
                $isActive = $i < 2; // первые две активные
                // генерируем уникальную карту
                do {
                    $cardNumber = self::generateMirCard();
                } while (\App\Models\PaymentDetail::where('detail', $cardNumber)->exists());
                $dailyLimit = self::randomDailyLimitRub();

                $dto = new PaymentDetailCreateDTO(
                    name: 'Реквизит карты',
                    detail: $cardNumber,
                    detail_type: DetailType::CARD,
                    initials: 'Иван Иванов',
                    is_active: $isActive,
                    daily_limit: $dailyLimit,
                    currency: 'rub',
                    payment_gateway_ids: [ $cardGateways[array_rand($cardGateways)] ],
                    max_pending_orders_quantity: rand(1,3),
                    order_interval_minutes: random_int(0, 1) === 0 ? null : random_int(1, 6) * 5,
                    user_device_id: $userDeviceId,
                    user_id: $user->id,
                    min_order_amount: random_int(1, 5) * 1000,
                    max_order_amount: random_int(1, 10) * 50000,
                );
                services()->paymentDetail()->create($dto);
            }

            // 2 телефона: 1 активен, 1 отключен
            for ($i = 0; $i < 2; $i++) {
                if (empty($phoneGateways)) break;
                $isActive = $i === 0; // первый включен, второй выключен
                // генерируем уникальный номер телефона
                do {
                    $phone = self::generateRuMobile();
                } while (\App\Models\PaymentDetail::where('detail', $phone)->exists());
                $dailyLimit = self::randomDailyLimitRub();

                $dto = new PaymentDetailCreateDTO(
                    name: 'Телефон для переводов',
                    detail: $phone,
                    detail_type: DetailType::PHONE,
                    initials: 'Иван Иванов',
                    is_active: $isActive,
                    daily_limit: $dailyLimit,
                    currency: 'rub',
                    payment_gateway_ids: [ $phoneGateways[array_rand($phoneGateways)] ],
                    max_pending_orders_quantity: rand(1,3),
                    order_interval_minutes: random_int(0, 1) === 0 ? null : random_int(1, 6) * 5,
                    user_device_id: $userDeviceId,
                    user_id: $user->id,
                    min_order_amount: random_int(1, 5) * 1000,
                    max_order_amount: random_int(1, 10) * 50000,
                );
                services()->paymentDetail()->create($dto);
            }
        }

        // Этап 4. Начисление депозитов в USDT (TRC20) трейдерам и администраторам
        $this->info('Начисляю тестовые депозиты в USDT (TRC20)...');

        // используем тех же пользователей с ролями Trader и Super Admin
        foreach ($eligibleUsers as $user) {
            if (! $user->wallet) {
                continue;
            }

            $amountUsd = self::randomUsdtAmount();
            $transactionId = self::generateTransactionId();
            $txHash = self::generateTronTxHash();

            services()->invoice()->deposit(
                walletID: $user->wallet->id,
                amount: Money::fromPrecision($amountUsd, Currency::USDT()),
                balanceType: BalanceType::TRUST,
                transactionID: (string) $transactionId,
                txHash: $txHash,
            );
        }

        // Этап 5. Создание мерчантов для пользователей с ролью Merchant и Super Admin
        $this->info('Создаю мерчантов для пользователей с ролью Merchant и Super Admin...');

        $merchantUsers = User::query()
            ->role(['Merchant', 'Super Admin'])
            ->get();

        $merchantNames = [
            'Магазин Электроники',
            'Книжный Мир',
            'Спортивный Клуб',
            'Кафе Уют',
            'Автозапчасти Плюс',
            'Цветочный Рай',
            'Детский Мир',
            'Продукты 24',
            'Техносервис',
            'Модный Стиль',
            'Интернет-магазин Техники',
            'Онлайн-аптека',
            'Строительные Материалы',
            'Одежда и Обувь',
            'Дом и Сад',
            'Красота и Здоровье',
            'Спорт и Отдых',
            'Автомобили и Мотоциклы',
            'Бизнес и Офис',
            'Хобби и Творчество',
        ];

        $projectDomains = [
            'electronics-shop',
            'book-world',
            'sport-club',
            'cozy-cafe',
            'auto-parts',
            'flower-paradise',
            'kids-world',
            'products-24',
            'techno-service',
            'fashion-style',
            'tech-store',
            'online-pharmacy',
            'building-materials',
            'clothing-shoes',
            'home-garden',
            'beauty-health',
            'sport-leisure',
            'auto-moto',
            'business-office',
            'hobby-creative',
        ];

        foreach ($merchantUsers as $merchantUser) {
            // Создаем по 3 мерчанта для каждого пользователя
            for ($i = 1; $i <= 3; $i++) {
                $randomIndex = array_rand($merchantNames);
                $name = $merchantNames[$randomIndex] . ' ' . $i;
                $projectLink = 'https://' . $projectDomains[$randomIndex] . '-' . $i . '-example.com';

                $merchant = services()->merchant()->create(new MerchantCreateDTO(
                    user_id: $merchantUser->id,
                    name: $name,
                    description: 'Тестовый мерчант #' . $i,
                    project_link: $projectLink,
                ));

                // Устанавливаем статусы для мерчантов:
                // 1-й мерчант: не валидирован, не забанен (оставляем как есть)
                // 2-й мерчант: валидирован, не забанен
                // 3-й мерчант: валидирован, забанен
                if ($i === 2) {
                    // Валидируем второй мерчант
                    $merchant->update(['validated_at' => now()]);
                } elseif ($i === 3) {
                    // Валидируем и баним третий мерчант
                    $merchant->update([
                        'validated_at' => now(),
                        'banned_at' => now(),
                    ]);
                }
            }
        }

        // Этап 6. Создание мерчант-саппортов и привязка к активному магазину
        $this->info('Создаю мерчант-саппортов...');

        $merchantSupportRole = Role::where('name', 'Merchant Support')->first();
        if (! $merchantSupportRole) {
            $this->warn("Роль 'Merchant Support' не найдена. Пропускаю создание саппортов.");
        } else {
            $merchantOwners = User::query()
                ->role(['Merchant', 'Super Admin'])
                ->get();

            foreach ($merchantOwners as $merchantOwner) {
                // Ищем один активный, валидированный и не заблокированный магазин текущего мерчанта
                $activeMerchant = MerchantModel::query()
                    ->where('user_id', $merchantOwner->id)
                    ->whereNotNull('validated_at')
                    ->whereNull('banned_at')
                    ->where('active', true)
                    ->orderByDesc('id')
                    ->first();

                // Если у администратора нет собственных магазинов – берем любой активный валидированный магазин
                if (! $activeMerchant) {
                    $activeMerchant = MerchantModel::query()
                        ->whereNotNull('validated_at')
                        ->whereNull('banned_at')
                        ->where('active', true)
                        ->orderByDesc('id')
                        ->first();
                }

                if (! $activeMerchant) {
                    continue;
                }

                // Генерируем уникальный логин для саппорта (сохраняется в поле email)
                do {
                    $login = $words[array_rand($words)] . random_int(1, 999999);
                } while (User::where('email', strtolower($login))->exists());

                $supportUser = User::create([
                    'name' => '',
                    'email' => strtolower($login),
                    'password' => Hash::make('password'),
                    'apk_access_token' => strtolower(Str::random(32)),
                    'api_access_token' => strtolower(Str::random(32)),
                    'avatar_uuid' => strtolower($login),
                    'avatar_style' => 'adventurer',
                    // Привязываем саппорта к владельцу-мерчанту
                    'merchant_id' => $merchantOwner->id,
                    'traffic_enabled_at' => now(),
                ]);

                $supportUser->assignRole($merchantSupportRole);

                // Даем доступ к одному активному магазину
                $supportUser->merchants()->sync([$activeMerchant->id]);

                // Создаем кошелек саппорту
                services()->wallet()->create($supportUser);
            }
        }

        // Этап 7. Создание Team Leader пользователей (2 шт.) с процентом комиссии от рефералов 0.20
        $this->info('Создаю Team Leader пользователей...');

        $teamLeaderRole = Role::where('name', 'Team Leader')->first();
        if (! $teamLeaderRole) {
            $this->warn("Роль 'Team Leader' не найдена. Пропускаю создание Team Leader пользователей.");
        } else {
            for ($i = 1; $i <= 2; $i++) {
                // Генерируем уникальный логин
                do {
                    $login = $words[array_rand($words)] . random_int(1, 999999);
                } while (User::where('email', strtolower($login))->exists());

                $leader = User::create([
                    'name' => '',
                    'email' => strtolower($login),
                    'password' => Hash::make('password'),
                    'apk_access_token' => strtolower(Str::random(32)),
                    'api_access_token' => strtolower(Str::random(32)),
                    'avatar_uuid' => strtolower($login),
                    'avatar_style' => 'adventurer',
                    'traffic_enabled_at' => now(),
                    'referral_commission_percentage' => 0.20,
                ]);

                $leader->assignRole($teamLeaderRole);

                // Создаем кошелек Team Leader
                services()->wallet()->create($leader);
            }
        }

        // Этап 8. Закрепляем трейдеров за случайными Team Leader (если не закреплены)
        $this->info('Закрепляю трейдеров за тим лидами...');

        $teamLeaders = User::query()
            ->role(['Team Leader', 'Super Admin'])
            ->pluck('id');

        if ($teamLeaders->isNotEmpty()) {
            $tradersWithoutLeader = User::query()
                ->role(['Trader'])
                ->whereNull('team_leader_id')
                ->get();

            foreach ($tradersWithoutLeader as $trader) {
                $trader->update([
                    'team_leader_id' => $teamLeaders->random(),
                ]);
            }
        }

        // Этап 9. Симуляция 100 H2H запросов на создание сделок через HTTP API (каждый заказ — отдельный анонимный job)
        $this->info('Постановка 100 отдельных задач на симуляцию H2H API запросов в очередь test-data...');
        $totalJobs = 300;
        for ($i = 0; $i < $totalJobs; $i++) {
            dispatch(function () use ($i, $totalJobs) {
                try {
                    self::simulateSingleH2HOrder($i, $totalJobs);
                } catch (\Throwable $e) {
                    \Log::error('simulateSingleH2HOrder failed: ' . $e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            })->onQueue('test-data');
        }

        $this->info('Все задачи симуляции отправлены в очередь test-data.');

        // После постановки задач на создание сделок — ставим задачу на создание заявок на вывод
        dispatch(function () {
            try {
                self::simulateWithdrawalsJob();
            } catch (\Throwable $e) {
                \Log::error('simulateWithdrawalsJob failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        })->onQueue('test-data');

        // Этап 11. Подключение девайсов приложения и генерация тестовых банковских сообщений (SMS/PUSH)
        dispatch(function () {
            try {
                self::simulateAppDeviceAndSmsJob();
            } catch (\Throwable $e) {
                \Log::error('simulateAppDeviceAndSmsJob failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        })->onQueue('test-data');

        $this->info('Генерация тестовых данных завершена!');

        return Command::SUCCESS;
    }

    /**
     * Создать заявки на вывод средств для всех мерчантов и администраторов с положительным MERCHANT балансом.
     * Для каждого пользователя — 3 заявки: SUCCESS, FAIL (c возвратом), PENDING.
     */
    private static function simulateWithdrawalsJob(): void
    {
        // Берём пользователей с ролями Merchant и Super Admin
        $users = User::query()
            ->role(['Merchant', 'Super Admin'])
            ->with('wallet')
            ->get();

        foreach ($users as $user) {
            if (! $user->wallet) {
                continue;
            }

            // Доступный MERCHANT баланс
            try {
                $available = services()->wallet()->getTotalAvailableBalance($user->wallet, BalanceType::MERCHANT);
            } catch (\Throwable $e) {
                continue;
            }

            // Преобразуем в целые единицы USDT (доллары) для удобного деления
            $balanceInt = (int) $available->toBeauty();
            if ($balanceInt <= 0) {
                continue;
            }

            // Определим случайное количество заявок (5..8), но не меньше 3 и по средствам
            $minRequests = 3;
            $maxRequests = 8;
            $desiredRequests = random_int(5, 8);

            // Готовим распределение сумм на desiredRequests слотов, оставляя возможность для 3 последних PENDING
            $remaining = $balanceInt;
            $amounts = [];
            $roundCandidates = [50, 100, 150, 200, 300, 500, 1000];
            $minUnit = 10;

            for ($i = 0; $i < $desiredRequests; $i++) {
                $mustLeft = $minUnit * max(0, ($desiredRequests - $i - 1));
                $cands = array_values(array_filter($roundCandidates, function (int $v) use ($remaining, $mustLeft) {
                    return $v <= max(0, $remaining - $mustLeft);
                }));

                if (empty($cands)) {
                    $slotsLeft = ($desiredRequests - $i);
                    if ($slotsLeft <= 0) { break; }
                    $alloc = intdiv($remaining, $slotsLeft);
                    $alloc = max($minUnit, intdiv($alloc, $minUnit) * $minUnit);
                    if ($alloc > $remaining) { $alloc = $remaining; }
                    if ($alloc <= 0) { break; }
                } else {
                    $alloc = $cands[array_rand($cands)];
                }

                $amounts[] = $alloc;
                $remaining -= $alloc;

                if ($remaining < $minUnit && ($desiredRequests - $i - 1) > 0) {
                    // недостаточно средств на оставшиеся слоты — ограничим количество
                    $desiredRequests = $i + 1;
                    break;
                }
            }

            if (count($amounts) < $minRequests) {
                // недостаточно средств для хотя бы 3 заявок — пропускаем
                continue;
            }

            // Создаём все заявки в статусе PENDING, сохраняя порядок создания
            $createdInvoices = [];
            foreach ($amounts as $amt) {
                try {
                    $invoice = services()->invoice()->createWithdrawal(
                        walletID: $user->wallet->id,
                        amount: Money::fromPrecision($amt, Currency::USDT()),
                        address: 'TRC20-TEST-' . uniqid('', true),
                        balanceType: BalanceType::MERCHANT,
                    );
                    $createdInvoices[] = $invoice;
                } catch (\Throwable $e) {
                    // ignore отдельные ошибки создания, продолжаем
                }
            }

            if (count($createdInvoices) < $minRequests) {
                continue;
            }

            // Последние три оставляем PENDING; остальные случайно завершаем или отклоняем
            $countCreated = count($createdInvoices);
            $limitPending = 3;
            $boundary = max(0, $countCreated - $limitPending);
            for ($i = 0; $i < $boundary; $i++) {
                $invoice = $createdInvoices[$i];
                try {
                    if ((bool) random_int(0, 1)) {
                        services()->invoice()->finishWithdrawal($invoice->id);
                    } else {
                        services()->invoice()->cancelWithdrawal($invoice->id);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    /**
     * Симулировать создание H2H-сделок через HTTP API.
     */
    private static function simulateH2HOrdersJob(int $count = 100): void
    {
        $merchants = MerchantModel::query()
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->with('user')
            ->get();

        if ($merchants->isEmpty()) {
            \Log::warning('Нет доступных мерчантов для симуляции H2H заказов.');
            return;
        }

        $detailTypes = [DetailType::CARD->value, DetailType::PHONE->value];
        $apiUrl = rtrim(config('app.url'), '/').'/api/h2h/order';
        $callbackUrl = rtrim(config('app.url'), '/').'/api/test/h2h-callback';

        for ($i = 0; $i < $count; $i++) {
            $merchant = $merchants[$i % $merchants->count()];

            // Пропускаем мерчанта без пользователя/токена
            if (! $merchant->user || empty($merchant->user->api_access_token)) {
                continue;
            }

            $payload = [
                'external_id' => 'ext-'.Str::uuid()->toString(),
                'amount' => random_int(1000, 5000),
                'currency' => 'rub',
                'payment_detail_type' => $detailTypes[array_rand($detailTypes)],
                'merchant_id' => $merchant->uuid,
                'callback_url' => $callbackUrl,
            ];

            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Access-Token' => $merchant->user->api_access_token,
                        // Можно варьировать время ожидания, оставим дефолт
                    ])
                    ->post($apiUrl, $payload);

                if (! $response->ok()) {
                    if ($response->status() === 400) {
                        $message = null;
                        try {
                            $json = $response->json();
                            if (is_array($json) && ($json['success'] ?? null) === false && isset($json['message'])) {
                                $message = is_string($json['message']) ? $json['message'] : null;
                            }
                        } catch (\Throwable $e) {
                            // ignore JSON parse errors and fallback to raw body below
                        }

                        if ($message !== null) {
                            \Log::warning('H2H create failed: ' . $message);
                        } else {
                            \Log::warning('H2H create failed: HTTP ' . $response->status() . ' ' . $response->body());
                        }
                    } else {
                        \Log::warning('H2H create failed: HTTP ' . $response->status() . ' ' . $response->body());
                    }
                } else {
                    // Успешно создано — попытаемся досрочно завершить половину сделок, половину — отменить
                    try {
                        $json = $response->json();
                        $orderId = is_array($json) ? ($json['data']['order_id'] ?? null) : null;
                    } catch (\Throwable $e) {
                        $orderId = null;
                    }

                    if (is_string($orderId) && $orderId !== '') {
                        $shouldFinish = (bool) random_int(0, 1);
                        $endpoint = rtrim(config('app.url'), '/')."/api/h2h/order/{$orderId}/".($shouldFinish ? 'finish' : 'cancel');

                        try {
                            $finishResponse = Http::timeout(10)
                                ->withHeaders([
                                    'Accept' => 'application/json',
                                    'Access-Token' => $merchant->user->api_access_token,
                                ])
                                ->patch($endpoint);

                            if (! $finishResponse->ok()) {
                                \Log::warning('H2H post-create action failed: HTTP ' . $finishResponse->status() . ' ' . $finishResponse->body());
                            }
                        } catch (\Throwable $e) {
                            \Log::warning('H2H post-create action exception: '.$e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('H2H create exception: '.$e->getMessage());
            }
        }
    }

    /**
     * Симулировать создание одной H2H-сделки через HTTP API.
     */
    private static function simulateSingleH2HOrder(int $index = 0, int $total = 1): void
    {
        $merchants = MerchantModel::query()
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->with('user')
            ->get();

        if ($merchants->isEmpty()) {
            \Log::warning('Нет доступных мерчантов для симуляции H2H заказа.');
            return;
        }

        $detailTypes = [DetailType::CARD->value, DetailType::PHONE->value];
        $apiUrl = rtrim(config('app.url'), '/').'/api/h2h/order';
        $callbackUrl = rtrim(config('app.url'), '/').'/api/test/h2h-callback';

        $merchant = $merchants->random();

        // Пропускаем мерчанта без пользователя/токена
        if (! $merchant->user || empty($merchant->user->api_access_token)) {
            return;
        }

        $payload = [
            'external_id' => 'ext-'.Str::uuid()->toString(),
            'amount' => random_int(1000, 5000),
            'currency' => 'rub',
            'payment_detail_type' => $detailTypes[array_rand($detailTypes)],
            'merchant_id' => $merchant->uuid,
            'callback_url' => $callbackUrl,
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Access-Token' => $merchant->user->api_access_token,
                ])
                ->post($apiUrl, $payload);

            if (! $response->ok()) {
                if ($response->status() === 400) {
                    $message = null;
                    try {
                        $json = $response->json();
                        if (is_array($json) && ($json['success'] ?? null) === false && isset($json['message'])) {
                            $message = is_string($json['message']) ? $json['message'] : null;
                        }
                    } catch (\Throwable $e) {
                        // ignore JSON parse errors and fallback to raw body below
                    }

                    if ($message !== null) {
                        \Log::warning('H2H create failed: ' . $message);
                    } else {
                        \Log::warning('H2H create failed: HTTP ' . $response->status() . ' ' . $response->body());
                    }
                } else {
                    \Log::warning('H2H create failed: HTTP ' . $response->status() . ' ' . $response->body());
                }
                return;
            }

            // Успешно создано — попытаемся досрочно завершить половину сделок, половину — отменить
            try {
                $json = $response->json();
                $orderId = is_array($json) ? ($json['data']['order_id'] ?? null) : null;
            } catch (\Throwable $e) {
                $orderId = null;
            }

            if (is_string($orderId) && $orderId !== '') {
                $shouldFinish = (bool) random_int(0, 1);
                $endpoint = rtrim(config('app.url'), '/')."/api/h2h/order/{$orderId}/".($shouldFinish ? 'finish' : 'cancel');

                try {
                    $finishResponse = Http::timeout(10)
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'Access-Token' => $merchant->user->api_access_token,
                        ])
                        ->patch($endpoint);

                    if (! $finishResponse->ok()) {
                        \Log::warning('H2H post-create action failed: HTTP ' . $finishResponse->status() . ' ' . $finishResponse->body());
                    }
                } catch (\Throwable $e) {
                    \Log::warning('H2H post-create action exception: '.$e->getMessage());
                }

                // После создания сделки распределяем ее по последним 30 дням и корректируем времена
                try {
                    // Равномерное распределение по последним 30 дням
                    $windowDays = 30;
                    $bucket = $total > 0 ? (int) floor(($index % $total) * $windowDays / $total) : 0; // 0..29
                    if ($bucket < 0) { $bucket = 0; }
                    if ($bucket >= $windowDays) { $bucket = $windowDays - 1; }

                    $daysAgo = $bucket; // 0..29
                    $createdAt = Carbon::now()
                        ->subDays($daysAgo)
                        ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));
                    $expiresAt = (clone $createdAt)->addMinutes(10);
                    $finishedAt = (clone $createdAt)->addMinutes(random_int(3, 8));

                    DB::table('orders')
                        ->where('uuid', $orderId)
                        ->update([
                            'created_at' => $createdAt,
                            'expires_at' => $expiresAt,
                            'finished_at' => $finishedAt,
                            'updated_at' => $finishedAt,
                        ]);
                } catch (\Throwable $e) {
                    \Log::warning('Failed to update order timestamps for distribution: '.$e->getMessage());
                }

                // Создаём споры для половины отменённых сделок с прикреплением примерного чека
                try {
                    $orderModel = Order::where('uuid', $orderId)->with('dispute')->first();
                    if ($orderModel && $orderModel->status->equals(OrderStatus::FAIL)) {
                        // считаем отменённой ручной отменой; споры только для каждой второй
                        if ($orderModel->sub_status && $orderModel->sub_status->equals(OrderSubStatus::CANCELED) && ($index % 2 === 1)) {
                            $exampleReceiptPath = base_path('example_check.png');
                            if (file_exists($exampleReceiptPath)) {
                                $tmpDir = storage_path('framework/testing/disputes');
                                if (! is_dir($tmpDir)) {
                                    @mkdir($tmpDir, 0777, true);
                                }
                                $tmpPath = $tmpDir.'/example_check_'.uniqid('', true).'.png';
                                if (@copy($exampleReceiptPath, $tmpPath)) {
                                    $uploadedReceipt = new UploadedFile(
                                        $tmpPath,
                                        'example_check.png',
                                        'image/png',
                                        null,
                                        true
                                    );
                                    try {
                                        $dispute = services()->dispute()->create($orderModel->id, $uploadedReceipt);

                                        // 1/3 оставить PENDING, 1/3 принять, 1/3 отклонить с причиной
                                        $roll = random_int(0, 2);
                                        if ($roll === 1) {
                                            try {
                                                services()->dispute()->accept($dispute->id);
                                            } catch (DisputeException $e) {
                                                // ignore
                                            }
                                        } elseif ($roll === 2) {
                                            $reasons = [
                                                'Некорректные данные платежа',
                                                'Несоответствие сумм на чеке',
                                                'Чек не читаем или повреждён',
                                                'Истёк срок оплаты',
                                                'Платёж не найден в системе банка',
                                            ];
                                            $reason = $reasons[array_rand($reasons)];
                                            try {
                                                services()->dispute()->cancel($dispute->id, $reason);
                                            } catch (DisputeException $e) {
                                                // ignore
                                            }
                                        }
                                    } catch (DisputeException $e) {
                                        // ignore dispute creation issues in test data generation
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore any unexpected exceptions during dispute auto-creation
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('H2H create exception: '.$e->getMessage());
        }
    }

    private static function generateRuMobile(): string
    {
        // Российский мобильный номер: начинается с 79 и ещё 9 цифр (итого 11)
        return '79' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    private static function generateMirCard(): string
    {
        // Сгенерируем валидный по Луну номер карты, начинающийся на 2 (МИР)
        $prefix = '2200';
        $length = 16;
        $base = $prefix;
        while (strlen($base) < $length - 1) {
            $base .= (string) random_int(0, 9);
        }

        $checksum = self::luhnChecksumDigit($base);
        return $base . $checksum;
    }

    private static function randomDailyLimitRub(): int
    {
        // от 10_000 до 100_000 с шагом 10_000
        $steps = range(1, 10);
        return $steps[array_rand($steps)] * 10000;
    }

    private static function luhnChecksumDigit(string $number): string
    {
        $sum = 0;
        $alt = true;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
            $alt = ! $alt;
        }
        $digit = (10 - ($sum % 10)) % 10;
        return (string) $digit;
    }

    private static function randomUsdtAmount(): int
    {
        // от 2000 до 5000 с шагом 500 (в долларах США)
        $options = range(2000, 5000, 500);
        return $options[array_rand($options)];
    }

    private static function generateTransactionId(): int
    {
        // Эмуляция числового идентификатора транзакции
        return random_int(1_000_000, 999_999_999);
    }

    private static function generateTronTxHash(): string
    {
        // Эмуляция TRON tx hash: 64-символьная hex-строка
        return bin2hex(random_bytes(32));
    }

    /**
     * Подключить устройства приложений (3 трейдера и все администраторы) и диспатчить отдельный job на каждый девайс.
     */
    private static function simulateAppDeviceAndSmsJob(): void
    {
        $traders = User::query()->role(['Trader'])->inRandomOrder()->limit(3)->get();
        $admins = User::query()->role(['Super Admin'])->get();

        $users = $traders->concat($admins)->unique('id');

        foreach ($users as $user) {
            /** @var UserDevice|null $device */
            $device = UserDevice::query()->where('user_id', $user->id)->orderByDesc('id')->first();
            if (! $device) {
                continue;
            }

            $deviceId = (int) $device->id;

            dispatch(function () use ($deviceId) {
                try {
                    self::simulateAppDeviceAndSmsForDevice($deviceId);
                } catch (\Throwable $e) {
                    \Log::error('simulateAppDeviceAndSmsForDevice failed: ' . $e->getMessage(), [
                        'device_id' => $deviceId,
                        'exception' => $e,
                    ]);
                }
            })->onQueue('test-data');
        }
    }

    /**
     * Обработать подключение и отправку сообщений для одного девайса.
     */
    private static function simulateAppDeviceAndSmsForDevice(int $deviceId): void
    {
        /** @var UserDevice|null $device */
        $device = UserDevice::query()->find($deviceId);
        if (! $device) {
            return;
        }

        $apiBase = rtrim(config('app.url'), '/');
        $connectUrl = $apiBase . '/api/app/device/connect';
        $smsUrl = $apiBase . '/api/app/sms';

        // Подключаем при необходимости
        if (! $device->connected_at) {
            $payload = [
                'android_id' => 'android-' . Str::random(16),
                'device_model' => 'Model ' . random_int(10, 99),
                'android_version' => (string) random_int(10, 14),
                'manufacturer' => 'TestVendor',
                'brand' => 'TestBrand',
            ];

            try {
                Http::timeout(10)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Access-Token' => $device->token,
                    ])
                    ->post($connectUrl, $payload);
            } catch (\Throwable $e) {
                // пропустим ошибку подключения
            }
        }

        // Пул активных шлюзов с отправителями
        $activeGateways = queries()->paymentGateway()->getAllActive();
        $gatewaysWithSenders = $activeGateways->filter(function ($pg) {
            return is_array($pg->sms_senders) && count($pg->sms_senders) > 0;
        })->values();

        if ($gatewaysWithSenders->isEmpty()) {
            return;
        }

        // Отправим по 10 тестовых сообщений
        for ($i = 0; $i < 10; $i++) {
            $gateway = $gatewaysWithSenders[random_int(0, $gatewaysWithSenders->count() - 1)];
            $sender = $gateway->sms_senders[array_rand($gateway->sms_senders)];

            $amount = random_int(500, 150000);
            $last4 = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $templates = [
                "+ {$amount} ₽ — баланс: 12 345 ₽ {$last4}",
                "Перевод {$amount}р от Иван И. баланс 45 678р",
                "Поступление {$amount} RUB на карту *{$last4}",
                "Зачисление *{$last4} RUR {$amount}; остаток 10 000",
                "Вы получили перевод: {$amount} руб на карту MIR{$last4}",
                "Пополнение счета на {$amount} ₽",
            ];
            $message = $templates[array_rand($templates)];

            $type = random_int(0, 1) === 0 ? 'sms' : 'push';
            $timestamp = now()->subMinutes(random_int(0, 120))->getTimestamp();

            $smsPayload = [
                'sender' => $sender,
                'message' => $message,
                'timestamp' => $timestamp,
                'type' => $type,
            ];

            try {
                Http::timeout(10)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Access-Token' => $device->token,
                        'Idempotency-Key' => (string) Str::uuid(),
                    ])
                    ->post($smsUrl, $smsPayload);
            } catch (\Throwable $e) {
                // игнорируем ошибки отдельных сообщений
            }
        }
    }
}
