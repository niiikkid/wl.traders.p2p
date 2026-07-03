<?php

namespace App\Console\Commands\Dev;

use App\DTO\Merchant\MerchantCreateDTO;
use App\DTO\PaymentDetail\PaymentDetailCreateDTO;
use App\DTO\User\UserCreateDTO;
use App\Enums\BalanceType;
use App\Enums\DetailType;
use App\Enums\NetworkEnum;
use App\Enums\RateSourceType;
use App\Enums\TeamLeaderInsuranceMode;
use App\Enums\TelegramChatMessageStatus;
use App\Enums\TelegramChatMessageType;
use App\Enums\TelegramChatParserType;
use App\Enums\TelegramChatStatus;
use App\Enums\TelegramChatType;
use App\Enums\UserActivityAction;
use App\Enums\WalletDepositInvoiceStatus;
use App\Enums\WalletDepositMatchType;
use App\Jobs\TestData\FinalizeDemoDataJob;
use App\Jobs\TestData\SeedDeviceMessagesJob;
use App\Jobs\TestData\SeedMerchantOrdersJob;
use App\Jobs\TestData\SeedMerchantPayoutsJob;
use App\Models\AntiFraudLog;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\NewsPost;
use App\Models\NewsPostReaction;
use App\Models\PaymentGateway;
use App\Models\RateSource;
use App\Models\Setting;
use App\Models\TelegramAccount;
use App\Models\TelegramChat;
use App\Models\TelegramChatMessage;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserDevice;
use App\Models\UserLoginHistory;
use App\Models\UserOnlinePeriod;
use App\Models\Wallet;
use App\Models\WalletDepositAddress;
use App\Models\WalletDepositInvoice;
use App\Models\WithdrawalAddress;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Support\TestData\DemoDataHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Полностью наполняет проект правдоподобными демо-данными: пользователи всех ролей,
 * устройства, реквизиты, депозиты, мерчанты, антифрод, выводы, новости, Telegram,
 * журналы активности/входов, онлайн-периоды, а также заказы, выплаты, споры, SMS
 * и логи API — последние ставятся в очередь `test-data` для устойчивой генерации
 * больших объёмов без падения по таймауту.
 */
class GenerateTestDataCommand extends Command
{
    protected $signature = 'dev:test-data:generate
        {--team-leaders=4 : Количество тимлидов}
        {--traders=25 : Количество трейдеров}
        {--merchant-users=6 : Количество пользователей-мерчантов}
        {--supports=4 : Количество саппортов}
        {--orders=200 : Заказов на одного активного мерчанта}
        {--payouts=50 : Выплат на одного активного мерчанта}
        {--sms=40 : SMS/PUSH на одно устройство}
        {--days=30 : Разброс данных по последним N дням}
        {--force : Разрешить запуск в production}';

    protected $aliases = ['app:generate-test-data'];

    protected $description = 'Генерирует полный набор правдоподобных демо-данных для проекта';

    private int $days = 30;

    /** @var array<string, int> */
    private array $roleIds = [];

    public function handle(): int
    {
        if (is_production() && ! $this->option('force')) {
            $this->error('Команда заблокирована в production. Используйте --force осознанно.');

            return Command::FAILURE;
        }

        @set_time_limit(0);
        // Блокируем реальные исходящие HTTP-вызовы на время синхронной фазы генерации.
        Http::fake();

        $this->days = max(1, (int) $this->option('days'));

        // Снимаем защиту mass-assignment, чтобы можно было проставлять исторические
        // created_at/updated_at напрямую при создании журналов (иначе даты = сейчас).
        Model::unguard();
        try {
            return $this->generate();
        } finally {
            Model::reguard();
        }
    }

    private function generate(): int
    {
        $this->prepare();

        if (! $this->resolveRoles()) {
            return Command::FAILURE;
        }

        $this->info('Создаю выдуманные банки (платёжные шлюзы) и источники курсов...');
        $this->createFictionalGateways();
        $this->createRateSources();

        $this->info('Создаю пользователей всех ролей...');
        $teamLeaders = $this->createTeamLeaders((int) $this->option('team-leaders'));
        $traders = $this->createTraders((int) $this->option('traders'), $teamLeaders);
        $merchantUsers = $this->createSimpleUsers('merchant', 'Merchant', (int) $this->option('merchant-users'));
        $this->createSimpleUsers('support', 'Support', (int) $this->option('supports'), fn (User $u) => $u->update([
            'support_can_view_deposits' => (bool) random_int(0, 1),
            'support_can_edit_order_amount' => (bool) random_int(0, 1),
            'support_can_use_manual_control_acq' => (bool) random_int(0, 1),
        ]));

        $tradersLike = $this->tradersLikeUsers();

        $this->info('Создаю устройства и реквизиты...');
        $this->createDevices($tradersLike);
        $this->createPaymentDetails($tradersLike);

        $this->info('Начисляю депозиты (trust / reserve / merchant)...');
        $this->fundTraders($tradersLike);
        $this->fundTeamLeaders($teamLeaders);

        $this->info('Создаю мерчантов, антифрод и клиентов...');
        $merchants = $this->createMerchants($merchantUsers);
        $this->fundMerchants($merchants);

        $this->info('Создаю адреса и заявки на вывод...');
        $this->createWithdrawals($tradersLike);

        $this->info('Создаю крипто-процессинг: адреса пополнения и invoice...');
        $this->createCryptoProcessing();

        $this->info('Создаю новости, Telegram, журналы входов и онлайн-периоды...');
        $this->createNews();
        $this->createTelegramData($traders);
        $this->createLoginHistory();
        $this->createOnlinePeriods($tradersLike);
        $this->createFoundationActivityLogs();

        $this->info('Ставлю в очередь генерацию заказов, выплат и SMS...');
        $this->dispatchHeavyJobs();

        $this->newLine();
        $this->info('Синхронная часть завершена. Оставшиеся данные генерируются в очереди `test-data`.');
        $this->warn('Убедитесь, что запущен Horizon (php artisan horizon) или воркеры очередей: test-data, order, callback, payout, notifications, sms.');

        return Command::SUCCESS;
    }

    private function prepare(): void
    {
        DemoDataHelper::seedMarketPrices();

        if (Setting::query()->count() === 0) {
            $this->call('app:install-settings');
        }
    }

    /**
     * Создаёт выдуманные банки-шлюзы для гривны (банки в проекте больше не сидируются).
     */
    private function createFictionalGateways(): void
    {
        $currency = DemoDataHelper::DEMO_CURRENCY;

        // Лимиты хранятся как человекочитаемые суммы фиата (как в справочнике банков).
        $minLimit = '100';
        $maxLimit = '50000';

        $created = 0;
        foreach (DemoDataHelper::fictionalBanks() as $bank) {
            if (PaymentGateway::query()->where('code', $bank['code'])->exists()) {
                continue;
            }

            DB::table('payment_gateways')->insert([
                'code' => $bank['code'],
                'logo' => '',
                'name' => $bank['name'],
                'currency' => $currency,
                'is_active' => 1,
                'max_limit' => $maxLimit,
                'min_limit' => $minLimit,
                'nspk_schema' => null,
                'sms_senders' => json_encode($bank['senders'], JSON_UNESCAPED_UNICODE),
                'detail_types' => json_encode(['card', 'phone'], JSON_UNESCAPED_UNICODE),
                'is_intrabank' => 0,
                'commission_rate' => 2.5,
                'service_commission_rate' => 8.0,
                'reservation_time_for_orders' => random_int(15, 30),
                'reservation_time_for_payouts' => random_int(10, 20),
                'trader_commission_rate_for_orders' => (float) random_int(5, 8),
                'trader_commission_rate_for_payouts' => (float) random_int(1, 3),
                'trader_commission_tiers_for_orders' => null,
                'total_service_commission_rate_for_orders' => (float) random_int(9, 12),
                'total_service_commission_rate_for_payouts' => (float) random_int(2, 4),
                'total_service_commission_tiers_for_orders' => null,
                'use_flexible_trader_commission_for_orders' => 0,
            ]);
            $created++;
        }

        $this->line("  Банков-шлюзов: {$created}");
    }

    /**
     * Создаёт сущности источников курсов (RateSource) — ручные, с готовым курсом.
     */
    private function createRateSources(): void
    {
        $created = 0;
        foreach (DemoDataHelper::SELL_RATES as $code => $rate) {
            if (! Currency::isCurrency($code)) {
                continue;
            }

            RateSource::query()->updateOrCreate(
                ['base_currency' => 'usdt', 'quote_currency' => $code, 'type' => RateSourceType::MANUAL->value],
                [
                    'name' => 'Ручной курс USDT/'.strtoupper($code),
                    'rate' => Money::fromPrecision((string) $rate, $code),
                    'rate_currency' => $code,
                    'settings' => ['manual' => ['sell' => ['rate' => $rate], 'buy' => ['rate' => round($rate * 1.008, 6)]]],
                    'is_active' => true,
                    'last_refreshed_at' => now(),
                ],
            );
            $created++;
        }

        $this->line("  Источников курсов: {$created}");
    }

    private function resolveRoles(): bool
    {
        $needed = ['Super Admin', 'Trader', 'Merchant', 'Team Leader', 'Support'];

        foreach ($needed as $name) {
            $role = Role::query()->where('name', $name)->first();
            if ($role) {
                $this->roleIds[$name] = (int) $role->id;
            }
        }

        if (! isset($this->roleIds['Trader'], $this->roleIds['Merchant'])) {
            $this->error('Базовые роли не найдены. Сначала выполните миграции и db:seed.');

            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, User>
     */
    private function createTeamLeaders(int $count): Collection
    {
        $leaders = collect();
        if (! isset($this->roleIds['Team Leader'])) {
            return $leaders;
        }

        for ($i = 1; $i <= $count; $i++) {
            $sharedReserve = $i % 2 === 0;
            $login = $this->uniqueLogin('lead');

            $user = services()->user()->create(new UserCreateDTO(
                login: $login,
                password: 'password',
                role_id: $this->roleIds['Team Leader'],
                telegram_username: $login,
                team_leader_insurance_mode: $sharedReserve
                    ? TeamLeaderInsuranceMode::TeamLeaderReserve->value
                    : TeamLeaderInsuranceMode::TraderReserve->value,
                team_leader_trader_limit: random_int(10, 30),
                team_leader_reserve_balance_limit: $sharedReserve ? 100000 : null,
                team_leader_reserve_stop_threshold: $sharedReserve ? 500 : null,
            ));

            $user->update([
                'referral_commission_percentage' => round(random_int(15, 30) / 100, 2),
                'payout_referral_commission_percentage' => round(random_int(10, 25) / 100, 2),
            ]);

            $leaders->push($user->fresh());
        }

        $this->line("  Тимлидов: {$leaders->count()}");

        return $leaders;
    }

    /**
     * @param  Collection<int, User>  $teamLeaders
     * @return Collection<int, User>
     */
    private function createTraders(int $count, Collection $teamLeaders): Collection
    {
        $traders = collect();

        for ($i = 1; $i <= $count; $i++) {
            $user = services()->user()->create(new UserCreateDTO(
                login: $this->uniqueLogin('trader'),
                password: 'password',
                role_id: $this->roleIds['Trader'],
                team_leader_id: $teamLeaders->isNotEmpty() && random_int(0, 100) < 80
                    ? (int) $teamLeaders->random()->id
                    : null,
            ));

            $user->update([
                'is_online' => random_int(1, 100) <= 85,
                'stop_traffic' => random_int(1, 100) <= 10,
                'payouts_enabled' => random_int(1, 100) <= 70,
                'payout_hold_enabled' => false,
                'payout_active_payouts_limit' => random_int(20, 50),
                'can_work_without_device' => random_int(1, 100) <= 20,
                'sms_auto_close_orders_enabled' => random_int(1, 100) <= 40,
            ]);

            $traders->push($user->fresh());
        }

        $this->line("  Трейдеров: {$traders->count()}");

        return $traders;
    }

    /**
     * @return Collection<int, User>
     */
    private function createSimpleUsers(string $prefix, string $roleName, int $count, ?callable $after = null): Collection
    {
        $users = collect();
        if (! isset($this->roleIds[$roleName])) {
            return $users;
        }

        for ($i = 1; $i <= $count; $i++) {
            $user = services()->user()->create(new UserCreateDTO(
                login: $this->uniqueLogin($prefix),
                password: 'password',
                role_id: $this->roleIds[$roleName],
            ));

            if ($after) {
                $after($user);
            }

            $users->push($user->fresh());
        }

        $this->line("  {$roleName}: {$users->count()}");

        return $users;
    }

    /**
     * Пользователи с трейдерским функционалом (трейдеры + супер-админы).
     *
     * @return Collection<int, User>
     */
    private function tradersLikeUsers(): Collection
    {
        return User::query()->role(['Trader', 'Super Admin'])->get();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function createDevices(Collection $users): void
    {
        $names = DemoDataHelper::androidDeviceNames();

        foreach ($users as $user) {
            if (UserDevice::query()->where('user_id', $user->id)->exists()) {
                continue;
            }

            $device = services()->device()->create($user->id, $names[array_rand($names)]);

            services()->device()->update(
                device: $device,
                android_id: DemoDataHelper::androidId(),
                device_model: $names[array_rand($names)],
                android_version: (string) random_int(10, 14),
                manufacturer: 'Android',
                brand: explode(' ', $names[array_rand($names)])[0],
                device_connect_snapshot: json_encode(['source' => 'demo'], JSON_UNESCAPED_UNICODE),
            );
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function createPaymentDetails(Collection $users): void
    {
        $currency = DemoDataHelper::DEMO_CURRENCY;
        $active = queries()->paymentGateway()->getAllActive();

        $cardGateways = [];
        $phoneGateways = [];
        foreach ($active as $pg) {
            if (strtolower($pg->currency->getCode()) !== $currency) {
                continue;
            }
            $types = $pg->detail_types ?? [];
            if (in_array(DetailType::CARD, $types, true)) {
                $cardGateways[] = (int) $pg->id;
            }
            if (in_array(DetailType::PHONE, $types, true)) {
                $phoneGateways[] = (int) $pg->id;
            }
        }

        if ($cardGateways === [] && $phoneGateways === []) {
            $this->warn('  Нет активных UAH-шлюзов — реквизиты не будут созданы.');

            return;
        }

        foreach ($users as $user) {
            $deviceId = UserDevice::query()->where('user_id', $user->id)->value('id');

            // 3 карты (2 активные, 1 выключена).
            if ($cardGateways !== []) {
                for ($i = 0; $i < 3; $i++) {
                    $this->makePaymentDetail($user->id, $deviceId, DetailType::CARD, $currency, $cardGateways, $i < 2);
                }
            }

            // 2 телефона (1 активный).
            if ($phoneGateways !== []) {
                for ($i = 0; $i < 2; $i++) {
                    $this->makePaymentDetail($user->id, $deviceId, DetailType::PHONE, $currency, $phoneGateways, $i === 0);
                }
            }
        }
    }

    /**
     * @param  array<int, int>  $gatewayIds
     */
    private function makePaymentDetail(int $userId, ?int $deviceId, DetailType $type, string $currency, array $gatewayIds, bool $isActive): void
    {
        try {
            $dto = new PaymentDetailCreateDTO(
                name: $type === DetailType::CARD ? 'Карта '.strtoupper($currency) : 'Телефон '.strtoupper($currency),
                detail: DemoDataHelper::detailValue($type, $currency),
                detail_type: $type,
                initials: DemoDataHelper::initials(),
                additional_info: null,
                is_active: $isActive,
                daily_limit: random_int(0, 1) === 0 ? null : random_int(3, 20) * 100000,
                monthly_limit: null,
                monthly_limit_reset_day: null,
                monthly_successful_orders_limit: null,
                daily_successful_orders_limit: random_int(0, 1) === 0 ? null : random_int(10, 50),
                currency: $currency,
                payment_gateway_ids: [$gatewayIds[array_rand($gatewayIds)]],
                max_pending_orders_quantity: random_int(3, 8),
                order_interval_minutes: null,
                user_device_id: $deviceId,
                user_id: $userId,
                min_order_amount: null,
                max_order_amount: null,
            );

            services()->paymentDetail()->create($dto);
        } catch (\Throwable $e) {
            $this->warn('  Реквизит пропущен: '.$e->getMessage());
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function fundTraders(Collection $users): void
    {
        foreach ($users as $user) {
            $wallet = $user->wallet;
            if (! $wallet) {
                continue;
            }

            services()->invoice()->deposit(
                walletID: $wallet->id,
                amount: Money::fromPrecision((string) random_int(5000, 30000), Currency::USDT()),
                balanceType: BalanceType::TRUST,
                transactionID: DemoDataHelper::transactionId(),
                txHash: DemoDataHelper::txHash(),
            );
        }
    }

    /**
     * @param  Collection<int, User>  $leaders
     */
    private function fundTeamLeaders(Collection $leaders): void
    {
        foreach ($leaders as $leader) {
            $wallet = $leader->wallet;
            if (! $wallet) {
                continue;
            }

            services()->invoice()->deposit(
                walletID: $wallet->id,
                amount: Money::fromPrecision((string) random_int(5000, 25000), Currency::USDT()),
                balanceType: BalanceType::RESERVE,
            );
        }
    }

    /**
     * @param  Collection<int, User>  $merchantUsers
     * @return Collection<int, Merchant>
     */
    private function createMerchants(Collection $merchantUsers): Collection
    {
        $merchants = collect();
        $names = DemoDataHelper::companyNames();

        // Мерчанты создаются и для мерчант-пользователей, и для супер-админов.
        $owners = $merchantUsers->merge(User::query()->role('Super Admin')->get())->unique('id');

        foreach ($owners as $owner) {
            for ($i = 1; $i <= 2; $i++) {
                $name = $names[array_rand($names)].' '.Str::upper(Str::random(3));

                $merchant = services()->merchant()->create(new MerchantCreateDTO(
                    user_id: $owner->id,
                    name: $name,
                    description: 'Демо-мерчант',
                    project_link: 'https://'.Str::slug($name).'.example.com',
                ));

                // Статусы: 1-й — валидирован и активен; 2-й — иногда на модерации/бан.
                if ($i === 1) {
                    $merchant->update(['validated_at' => now()->subDays(random_int(5, 60))]);
                } else {
                    $roll = random_int(1, 100);
                    if ($roll <= 60) {
                        $merchant->update(['validated_at' => now()->subDays(random_int(1, 30))]);
                    } elseif ($roll <= 80) {
                        $merchant->update([
                            'validated_at' => now()->subDays(random_int(1, 30)),
                            'banned_at' => now()->subDays(random_int(1, 10)),
                        ]);
                    }
                    // иначе остаётся на модерации (validated_at = null)
                }

                $this->configureMerchant($merchant);
                $merchants->push($merchant->fresh());
            }
        }

        $this->line("  Мерчантов: {$merchants->count()}");

        return $merchants;
    }

    private function configureMerchant(Merchant $merchant): void
    {
        // GEO-карта: только гривна.
        $merchant->setGeoMap([DemoDataHelper::DEMO_CURRENCY => 'bybit']);
        $merchant->save();

        // Клиенты мерчанта.
        $clientIds = [];
        foreach (range(1, random_int(4, 10)) as $ignored) {
            $client = MerchantClient::query()->create([
                'merchant_id' => $merchant->id,
                'client_id' => 'client-'.Str::lower(Str::random(10)),
                'blocked_until' => random_int(1, 100) <= 15 ? now()->addDays(random_int(1, 7)) : null,
            ]);
            $clientIds[] = (int) $client->id;
        }

        // Антифрод примерно для половины мерчантов — с настройками и историей проверок.
        if (random_int(0, 1) === 1) {
            try {
                services()->antiFraudSetting()->create([
                    'merchant_id' => $merchant->id,
                    'enabled' => true,
                    'primary_max_pending' => random_int(1, 3),
                    'primary_failed_limit' => random_int(3, 8),
                    'primary_block_days' => random_int(1, 7),
                    'primary_rate_limits' => [['count' => random_int(3, 6), 'minutes' => random_int(5, 30)]],
                    'secondary_enabled' => true,
                    'secondary_max_pending' => random_int(1, 2),
                    'secondary_failed_limit' => random_int(2, 5),
                    'secondary_block_days' => random_int(1, 3),
                    'secondary_rate_limits' => [['count' => random_int(2, 4), 'minutes' => random_int(10, 60)]],
                ]);

                $this->createAntiFraudHistory($merchant, $clientIds);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Генерирует историю антифрод-проверок (для графиков), распределённую по времени.
     *
     * @param  array<int, int>  $clientIds
     */
    private function createAntiFraudHistory(Merchant $merchant, array $clientIds): void
    {
        if ($clientIds === []) {
            return;
        }

        $denyReasons = [
            'Превышен лимит попыток создания заказов',
            'Слишком много незавершённых заказов',
            'Клиент временно заблокирован',
            'Превышен лимит неуспешных заказов',
        ];

        $total = random_int(40, 120);
        for ($i = 0; $i < $total; $i++) {
            $isDeny = random_int(1, 100) <= 18;
            $trafficType = random_int(1, 100) <= 70 ? 'primary' : 'secondary';
            $createdAt = now()
                ->subDays(random_int(0, $this->days))
                ->subMinutes(random_int(0, 1440));

            AntiFraudLog::query()->create([
                'merchant_id' => $merchant->id,
                'merchant_client_id' => $clientIds[array_rand($clientIds)],
                'client_id' => 'client-'.Str::lower(Str::random(10)),
                'decision' => $isDeny ? 'deny' : 'allow',
                'message' => $isDeny ? $denyReasons[array_rand($denyReasons)] : null,
                'meta' => ['traffic_type' => $trafficType],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    /**
     * @param  Collection<int, Merchant>  $merchants
     */
    private function fundMerchants(Collection $merchants): void
    {
        foreach ($merchants as $merchant) {
            $merchant->loadMissing('wallet');
            if (! $merchant->wallet || $merchant->banned_at) {
                continue;
            }

            services()->invoice()->deposit(
                walletID: $merchant->wallet->id,
                amount: Money::fromPrecision((string) random_int(3000, 12000), Currency::USDT()),
                balanceType: BalanceType::MERCHANT,
            );
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function createWithdrawals(Collection $users): void
    {
        foreach ($users->unique('id') as $user) {
            $wallet = $user->wallet;
            if (! $wallet) {
                continue;
            }

            $balanceType = BalanceType::TRUST;

            foreach (range(1, random_int(1, 3)) as $ignored) {
                $address = DemoDataHelper::tronAddress();
                $withdrawalAddress = WithdrawalAddress::query()->create([
                    'user_id' => $user->id,
                    'name' => 'Кошелёк '.Str::upper(Str::random(4)),
                    'address' => $address,
                    'address_hash' => WithdrawalAddress::hashAddress($address),
                ]);

                try {
                    $invoice = services()->invoice()->createWithdrawal(
                        walletID: $wallet->id,
                        amount: Money::fromPrecision((string) random_int(100, 3000), Currency::USDT()),
                        withdrawalAddress: $withdrawalAddress,
                        balanceType: $balanceType,
                    );

                    $roll = random_int(1, 100);
                    if ($roll <= 55) {
                        services()->invoice()->finishWithdrawal($invoice->id);
                    } elseif ($roll <= 75) {
                        services()->invoice()->cancelWithdrawal($invoice->id);
                    }
                    // иначе оставляем в статусе PENDING
                } catch (\Throwable $e) {
                    // недостаточно средств/прочее — пропускаем
                }
            }
        }
    }

    /**
     * Наполняет крипто-процессинг: пул адресов пополнения (TRC20/USDT) и invoice
     * в разных статусах (ожидание, обработка, оплачен, истёк, несовпадение суммы и т.д.).
     */
    private function createCryptoProcessing(): void
    {
        // 1. Пул адресов пополнения.
        $addresses = [];
        for ($i = 1; $i <= 10; $i++) {
            $addresses[] = WalletDepositAddress::query()->create([
                'currency' => Currency::USDT()->getCode(),
                'network' => NetworkEnum::TRX,
                'address' => DemoDataHelper::tronAddress(),
                'label' => 'Пул USDT TRC20 #'.$i,
                'is_active' => $i <= 8,
                'balance_units' => Money::fromPrecision((string) random_int(0, 50000), Currency::USDT()),
                'last_checked_at' => now()->subMinutes(random_int(1, 120)),
                'metadata' => ['source' => 'demo'],
            ]);
        }

        // 2. Кошельки-получатели: пользовательские (trust) и мерчантские (merchant).
        $walletTargets = Wallet::query()->get()
            ->map(fn (Wallet $w) => [
                'id' => (int) $w->id,
                'balance_type' => $w->merchant_id ? BalanceType::MERCHANT : BalanceType::TRUST,
            ])
            ->all();

        if ($walletTargets === []) {
            return;
        }

        $adminId = User::query()->role('Super Admin')->value('id');

        $statuses = [
            WalletDepositInvoiceStatus::PAID, WalletDepositInvoiceStatus::PAID, WalletDepositInvoiceStatus::PAID,
            WalletDepositInvoiceStatus::PENDING,
            WalletDepositInvoiceStatus::PROCESSING,
            WalletDepositInvoiceStatus::EXPIRED,
            WalletDepositInvoiceStatus::AMOUNT_MISMATCH,
            WalletDepositInvoiceStatus::CANCELLED,
            WalletDepositInvoiceStatus::FAILED,
        ];

        $count = 60;
        for ($i = 0; $i < $count; $i++) {
            $target = $walletTargets[array_rand($walletTargets)];
            $address = $addresses[array_rand($addresses)];
            $status = $statuses[array_rand($statuses)];
            $amount = Money::fromPrecision((string) random_int(50, 3000), Currency::USDT());

            $isPending = $status === WalletDepositInvoiceStatus::PENDING;
            $createdAt = $isPending
                ? now()->subMinutes(random_int(1, 45))
                : now()->subDays(random_int(0, $this->days))->subMinutes(random_int(0, 1440));
            $expiresAt = (clone $createdAt)->addMinutes(30);

            $this->makeDepositInvoice($target, $address->id, $status, $amount, $createdAt, $expiresAt, (int) $adminId);
        }

        $this->line('  Адресов пополнения: '.count($addresses).', invoice: '.$count);
    }

    /**
     * @param  array{id: int, balance_type: BalanceType}  $target
     */
    private function makeDepositInvoice(array $target, int $addressId, WalletDepositInvoiceStatus $status, Money $amount, Carbon $createdAt, Carbon $expiresAt, int $adminId): void
    {
        $data = [
            'wallet_id' => $target['id'],
            'balance_type' => $target['balance_type'],
            'deposit_address_id' => $addressId,
            'address' => DemoDataHelper::tronAddress(),
            'currency' => Currency::USDT()->getCode(),
            'network' => NetworkEnum::TRX,
            'amount' => $amount,
            'status' => $status,
            'confirmations' => 0,
            'expires_at' => $expiresAt,
            'poll_until_at' => (clone $expiresAt)->addMinutes(60),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        $matchedAt = (clone $createdAt)->addMinutes(random_int(1, 20));

        switch ($status) {
            case WalletDepositInvoiceStatus::PAID:
                $manual = random_int(1, 100) <= 25;
                $data = array_merge($data, [
                    'amount_received' => $amount,
                    'txid' => DemoDataHelper::txHash(),
                    'confirmations' => random_int(20, 60),
                    'match_type' => $manual ? WalletDepositMatchType::MANUAL : WalletDepositMatchType::AUTOMATIC,
                    'matched_at' => $matchedAt,
                    'finalized_at' => $matchedAt,
                    'last_checked_at' => $matchedAt,
                    'resolved_by_user_id' => $manual ? $adminId : null,
                    'resolution_note' => $manual ? 'Прикреплено вручную по TXID' : null,
                ]);
                break;

            case WalletDepositInvoiceStatus::PROCESSING:
                $data = array_merge($data, [
                    'amount_received' => $amount,
                    'txid' => DemoDataHelper::txHash(),
                    'confirmations' => random_int(1, 15),
                    'match_type' => WalletDepositMatchType::AUTOMATIC,
                    'matched_at' => $matchedAt,
                    'last_checked_at' => now()->subMinutes(random_int(1, 30)),
                ]);
                break;

            case WalletDepositInvoiceStatus::AMOUNT_MISMATCH:
                $received = $amount->mul((string) (random_int(40, 90) / 100));
                $data = array_merge($data, [
                    'amount_received' => $received,
                    'txid' => DemoDataHelper::txHash(),
                    'confirmations' => random_int(20, 40),
                    'match_type' => WalletDepositMatchType::AUTOMATIC,
                    'matched_at' => $matchedAt,
                    'finalized_at' => $matchedAt,
                    'error_message' => 'Полученная сумма не совпадает с суммой счёта',
                ]);
                break;

            case WalletDepositInvoiceStatus::FAILED:
                $data['error_message'] = 'Ошибка обработки транзакции';
                $data['finalized_at'] = $matchedAt;
                break;

            case WalletDepositInvoiceStatus::EXPIRED:
            case WalletDepositInvoiceStatus::CANCELLED:
                $data['finalized_at'] = (clone $expiresAt);
                break;

            case WalletDepositInvoiceStatus::PENDING:
            default:
                break;
        }

        WalletDepositInvoice::query()->create($data);
    }

    private function createNews(): void
    {
        $admin = User::query()->role('Super Admin')->first();
        if (! $admin) {
            return;
        }

        $posts = [
            ['Обновление платформы', 'Мы улучшили скорость назначения реквизитов и стабильность выплат.'],
            ['Новые платёжные шлюзы', 'Добавлена поддержка новых банков и способов оплаты.'],
            ['Плановые технические работы', 'В выходные возможны кратковременные перерывы в работе сервиса.'],
            ['Изменения в комиссиях', 'С понедельника обновляются тарифы для отдельных направлений.'],
            ['Антифрод: усиление защиты', 'Внедрены дополнительные проверки для снижения фрода.'],
        ];

        $reactors = User::query()->whereKeyNot($admin->id)->inRandomOrder()->limit(20)->get();

        foreach ($posts as [$title, $paragraph]) {
            $content = DemoDataHelper::newsContent($title, $paragraph);
            $createdAt = now()->subDays(random_int(0, $this->days));

            $post = NewsPost::query()->create([
                'author_id' => $admin->id,
                'title' => $title,
                'is_visible_for_all' => random_int(0, 1) === 1,
                'visible_role_names' => ['Trader', 'Team Leader'],
                'content_json' => $content['json'],
                'content_html' => $content['html'],
                'views_count' => random_int(20, 500),
                'likes_count' => 0,
                'dislikes_count' => 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $likes = 0;
            $dislikes = 0;
            foreach ($reactors->random(min(8, $reactors->count())) as $reactor) {
                $reaction = random_int(1, 100) <= 75 ? 'up' : 'down';
                NewsPostReaction::query()->firstOrCreate(
                    ['news_post_id' => $post->id, 'user_id' => $reactor->id],
                    ['reaction' => $reaction],
                );
                $reaction === 'up' ? $likes++ : $dislikes++;
            }

            $post->update(['likes_count' => $likes, 'dislikes_count' => $dislikes]);
        }
    }

    /**
     * @param  Collection<int, User>  $traders
     */
    private function createTelegramData(Collection $traders): void
    {
        // Личные привязки Telegram примерно для половины пользователей.
        User::query()->whereNotNull('email')->inRandomOrder()->limit(40)->get()
            ->each(function (User $user) {
                if (random_int(0, 1) === 0 || TelegramAccount::query()->where('user_id', $user->id)->exists()) {
                    return;
                }

                TelegramAccount::query()->create([
                    'user_id' => $user->id,
                    'chat_id' => (string) random_int(100000000, 999999999),
                    'username' => $user->email,
                    'first_name' => explode(' ', DemoDataHelper::fullName())[0],
                    'last_name' => explode(' ', DemoDataHelper::fullName())[1] ?? null,
                    'link_token' => Str::random(40),
                    'is_active' => true,
                    'linked_at' => now()->subDays(random_int(0, $this->days)),
                ]);
            });

        // Групповые чаты: обработка споров и командные чаты трейдеров.
        $disputeChat = TelegramChat::query()->create([
            'telegram_chat_id' => '-100'.random_int(1000000000, 9999999999),
            'type' => 'supergroup',
            'title' => 'Диспуты — обработка',
            'username' => null,
            'status' => TelegramChatStatus::ACTIVE,
            'chat_type' => TelegramChatType::DISPUTE_PROCESSING,
            'parser_type' => TelegramChatParserType::STANDARD_DISPUTE,
            'debug_enabled' => false,
            'last_message_at' => now(),
        ]);

        $teamChat = TelegramChat::query()->create([
            'telegram_chat_id' => '-100'.random_int(1000000000, 9999999999),
            'type' => 'supergroup',
            'title' => 'Команда трейдеров',
            'username' => null,
            'status' => TelegramChatStatus::ACTIVE,
            'chat_type' => TelegramChatType::TRADER_TEAM,
            'parser_type' => null,
            'debug_enabled' => false,
            'last_message_at' => now(),
        ]);

        if ($traders->isNotEmpty()) {
            $attach = $traders->random(min(5, $traders->count()));
            $teamChat->traders()->syncWithoutDetaching(
                $attach->mapWithKeys(fn (User $t) => [$t->id => ['telegram_username' => $t->email]])->all()
            );
        }

        $statuses = [
            TelegramChatMessageStatus::RECEIVED,
            TelegramChatMessageStatus::PROCESSED,
            TelegramChatMessageStatus::IGNORED,
            TelegramChatMessageStatus::FAILED,
        ];

        foreach (range(1, 25) as $n) {
            $createdAt = now()->subDays(random_int(0, $this->days))->subMinutes(random_int(0, 1440));
            $uuid = (string) Str::uuid();

            TelegramChatMessage::query()->create([
                'telegram_chat_id' => $disputeChat->id,
                'telegram_update_id' => (string) Str::uuid(),
                'telegram_message_id' => (string) random_int(1000, 999999),
                'message_type' => random_int(0, 1) === 0 ? TelegramChatMessageType::TEXT : TelegramChatMessageType::PHOTO,
                'text' => 'Спор по сделке '.$uuid,
                'caption' => null,
                'detected_uuid' => $uuid,
                'order_id' => null,
                'dispute_id' => null,
                'status' => $statuses[array_rand($statuses)],
                'failure_reason' => null,
                'is_dispute_related' => true,
                'raw_payload' => ['source' => 'demo'],
                'processed_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function createLoginHistory(): void
    {
        $locations = DemoDataHelper::locations();
        $agents = DemoDataHelper::userAgents();

        User::query()->get()->each(function (User $user) use ($locations, $agents) {
            foreach (range(1, random_int(3, 10)) as $ignored) {
                $loc = $locations[array_rand($locations)];
                $agent = $agents[array_rand($agents)];
                $createdAt = now()->subDays(random_int(0, $this->days))->subMinutes(random_int(0, 1440));

                UserLoginHistory::query()->create([
                    'user_id' => $user->id,
                    'ip_address' => DemoDataHelper::ip(),
                    'user_agent' => $agent['ua'],
                    'device_type' => $agent['device'],
                    'browser' => $agent['browser'],
                    'operating_system' => $agent['os'],
                    'location' => $loc['city'].', '.$loc['country'],
                    'country_code' => $loc['country_code'],
                    'country' => $loc['country'],
                    'region' => $loc['region'],
                    'city' => $loc['city'],
                    'is_successful' => random_int(1, 100) <= 90,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        });
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function createOnlinePeriods(Collection $users): void
    {
        $windowDays = min($this->days, 10);

        foreach ($users as $user) {
            for ($d = 0; $d <= $windowDays; $d++) {
                foreach (range(1, random_int(1, 3)) as $ignored) {
                    $start = now()->subDays($d)
                        ->setTime(random_int(7, 21), random_int(0, 59), 0);
                    $end = (clone $start)->addMinutes(random_int(20, 240));

                    UserOnlinePeriod::query()->create([
                        'user_id' => $user->id,
                        'started_at' => $start,
                        'ended_at' => $end,
                    ]);
                }
            }
        }
    }

    private function createFoundationActivityLogs(): void
    {
        $admins = User::query()->role('Super Admin')->pluck('id')->all();
        if ($admins === []) {
            return;
        }

        $agents = DemoDataHelper::userAgents();

        // Логи создания пользователей.
        User::query()->inRandomOrder()->limit(40)->get()->each(function (User $user) use ($admins, $agents) {
            $agent = $agents[array_rand($agents)];
            UserActivityLog::query()->create([
                'actor_user_id' => $admins[array_rand($admins)],
                'impersonator_user_id' => null,
                'actor_role' => 'Super Admin',
                'action' => UserActivityAction::Created,
                'subject_type' => 'user',
                'subject_id' => $user->id,
                'subject_uuid' => null,
                'route_name' => 'admin.users.store',
                'ip_address' => DemoDataHelper::ip(),
                'user_agent' => $agent['ua'],
                'changes' => [],
                'meta' => ['source' => 'demo-data'],
                'created_at' => $user->created_at,
            ]);
        });

        // Логи создания мерчантов и реквизитов.
        Merchant::query()->inRandomOrder()->limit(20)->get()->each(function (Merchant $merchant) use ($admins, $agents) {
            $agent = $agents[array_rand($agents)];
            UserActivityLog::query()->create([
                'actor_user_id' => $admins[array_rand($admins)],
                'impersonator_user_id' => null,
                'actor_role' => 'Super Admin',
                'action' => UserActivityAction::Created,
                'subject_type' => 'merchant',
                'subject_id' => $merchant->id,
                'subject_uuid' => $merchant->uuid,
                'route_name' => 'admin.merchants.store',
                'ip_address' => DemoDataHelper::ip(),
                'user_agent' => $agent['ua'],
                'changes' => [],
                'meta' => ['source' => 'demo-data'],
                'created_at' => $merchant->created_at,
            ]);
        });
    }

    private function dispatchHeavyJobs(): void
    {
        $batchSize = 25;
        $ordersPerMerchant = max(0, (int) $this->option('orders'));
        $payoutsPerMerchant = max(0, (int) $this->option('payouts'));

        $payoutTraderIds = User::query()
            ->role('Trader')
            ->where('payouts_enabled', true)
            ->pluck('id')
            ->all();

        $activeMerchants = Merchant::query()
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->pluck('id');

        foreach ($activeMerchants as $merchantId) {
            for ($created = 0; $created < $ordersPerMerchant; $created += $batchSize) {
                $count = min($batchSize, $ordersPerMerchant - $created);
                SeedMerchantOrdersJob::dispatch((int) $merchantId, $count, $this->days);
            }

            if ($payoutTraderIds !== []) {
                for ($created = 0; $created < $payoutsPerMerchant; $created += $batchSize) {
                    $count = min($batchSize, $payoutsPerMerchant - $created);
                    SeedMerchantPayoutsJob::dispatch((int) $merchantId, $count, $payoutTraderIds, $this->days);
                }
            }
        }

        $smsPerDevice = max(0, (int) $this->option('sms'));
        if ($smsPerDevice > 0) {
            UserDevice::query()->pluck('id')->each(function ($deviceId) use ($smsPerDevice) {
                SeedDeviceMessagesJob::dispatch((int) $deviceId, $smsPerDevice, $this->days);
            });
        }

        // Финализация ставится последней — на однопоточной очереди выполнится после всех.
        FinalizeDemoDataJob::dispatch($this->days);

        $this->line('  Мерчантов для заказов/выплат: '.$activeMerchants->count());
    }

    private function uniqueLogin(string $prefix): string
    {
        do {
            $login = $prefix.random_int(1000, 999999);
        } while (User::query()->where('email', strtolower($login))->exists());

        return $login;
    }
}
