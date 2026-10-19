<?php

namespace Tests\Unit;

use App\Enums\MarketEnum;
use App\Http\Requests\MerchantApiLog\AmountDistributionRequest;
use App\Models\Setting;
use App\Models\ValueObjects\Settings\ManualPriceParserSettings;
use App\Services\Market\Utils\MarketStore;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Settings\SettingsService;
use App\Support\TestData\DemoDataHelper;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\TestCase;

class CurrencyPriorityTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('env', 'testing');
        $app->instance('config', new Repository([
            'money' => require dirname(__DIR__, 2).'/config/money.php',
        ]));
        $app->instance('cache', new CacheRepository(new ArrayStore));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    public function test_usd_is_first_without_removing_other_fiat_or_changing_settlement(): void
    {
        $codes = Currency::getAllCodes();

        $this->assertSame('usd', $codes[0]);
        $this->assertSame($codes, Currency::getAll()->map->getCode()->all());
        $this->assertContains('uah', $codes);
        $this->assertContains('rub', $codes);
        $this->assertNotContains('usdt', $codes);
        $this->assertSame(['usd', 'rub', 'kzt', 'eur', 'tjs', 'kgs', 'uah', 'azn', 'try', 'idr', 'pln'], $codes);
        $this->assertFalse(config('money.currencies.USD.base'));
        $this->assertTrue(config('money.currencies.USDT.base'));
    }

    public function test_amount_distribution_defaults_to_usd_but_respects_explicit_currency(): void
    {
        $this->assertSame('usd', AmountDistributionRequest::create('/')->currency());
        $this->assertSame('usd', AmountDistributionRequest::create('/', 'GET', ['currency' => ''])->currency());
        $this->assertSame('uah', AmountDistributionRequest::create('/', 'GET', ['currency' => 'UAH'])->currency());
    }

    public function test_demo_amounts_rates_cards_and_messages_use_their_actual_currency(): void
    {
        $this->assertSame('usd', DemoDataHelper::DEMO_CURRENCY);
        $this->assertSame('usd', array_key_first(DemoDataHelper::SELL_RATES));

        foreach (DemoDataHelper::AMOUNT_RANGES as $currency => [$min, $max]) {
            $amount = DemoDataHelper::realisticFiatAmount($currency);
            $this->assertGreaterThanOrEqual($min, $amount);
            $this->assertLessThanOrEqual($max, $amount);
            $this->assertStringContainsString(strtoupper($currency), DemoDataHelper::bankMessage($amount, '1234', $currency));
        }

        $this->assertStringContainsString('USD', DemoDataHelper::bankMessage(100, '1234'));
        $card = DemoDataHelper::generateCard();
        $this->assertMatchesRegularExpression('/^4[0-9]{15}$/', $card);
        $this->assertSame(DemoDataHelper::luhnChecksumDigit(substr($card, 0, -1)), substr($card, -1));
        $this->assertMatchesRegularExpression('/^120255501[0-9]{2}$/', DemoDataHelper::phoneForCurrency('USD'));
        $this->assertStringStartsWith('380', DemoDataHelper::phoneForCurrency('uah'));

        foreach (DemoDataHelper::fictionalBanks() as $bank) {
            $this->assertStringStartsWith('demo-', $bank['code']);
            $this->assertStringStartsWith('example.', $bank['senders'][1]);
        }
    }

    public function test_demo_market_prices_cover_usd_and_preserve_other_supported_demo_fiat(): void
    {
        DemoDataHelper::seedMarketPrices();
        $this->assertSame(1.08, DemoDataHelper::SELL_RATES['usd']);
        $this->assertSame(config('money.currencies.USD.manual_rate'), DemoDataHelper::SELL_RATES['usd']);

        foreach (DemoDataHelper::SELL_RATES as $code => $rate) {
            foreach ([MarketEnum::BYBIT, MarketEnum::BINANCE, MarketEnum::MANUAL] as $market) {
                $this->assertSame(
                    Money::fromPrecision((string) $rate, $code)->toUnits(),
                    MarketStore::getSellPrice(Currency::make($code), $market),
                );
                $this->assertNotNull(MarketStore::getBuyPrice(Currency::make($code), $market));
                if ($code === 'usd') {
                    $this->assertSame(
                        Money::fromPrecision('1.08', 'usd')->toUnits(),
                        MarketStore::getBuyPrice(Currency::USD(), $market),
                    );
                }
            }
        }
    }

    public function test_manual_usd_defaults_do_not_replace_explicit_rates_or_other_currencies(): void
    {
        $defaults = ManualPriceParserSettings::fromArray([], 'usd');
        $this->assertSame(1.08, $defaults->buy->rate);
        $this->assertSame(1.08, $defaults->sell->rate);
        $explicit = ManualPriceParserSettings::fromArray(['rate' => 1.13], 'usd');
        $this->assertSame(1.13, $explicit->buy->rate);
        $this->assertSame(1.13, $explicit->sell->rate);
        $this->assertSame(0.0, ManualPriceParserSettings::fromArray(['sell' => ['rate' => 0]], 'usd')->sell->rate);
        $this->assertSame(0.0, ManualPriceParserSettings::defaults('eur')->sell->rate);
        $this->assertSame(0.0, ManualPriceParserSettings::defaults()->sell->rate);
    }

    public function test_settings_service_supplies_manual_usd_default_without_changing_automatic_settings(): void
    {
        cache()->put('app-settings', collect([new Setting([
            'key' => SettingsService::CURRENCY_PRICE_PARSER_SETTINGS,
            'value' => json_encode(['usd' => ['bybit' => ['buy' => ['amount' => 1500], 'sell' => ['amount' => 2000]]]]),
        ])]));
        $service = new SettingsService;

        $this->assertSame(1.08, $service->getMarketPriceParser(Currency::USD(), MarketEnum::MANUAL)->sell->rate);
        $automatic = $service->getMarketPriceParser(Currency::USD(), MarketEnum::BYBIT);
        $this->assertSame(1500, $automatic->buy->amount);
        $this->assertSame(2000, $automatic->sell->amount);
    }

    public function test_fiat_default_migration_preserves_existing_preferences_and_rolls_back(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:');
        Schema::swap($connection->getSchemaBuilder());
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('fiat_currency', 10)->default('rub');
        });
        $connection->table('users')->insert(['id' => 1, 'fiat_currency' => 'uah']);
        $connection->table('users')->insert(['id' => 2, 'fiat_currency' => 'rub']);
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_19_215834_set_usd_as_default_user_fiat_currency.php';

        $migration->up();
        $connection->table('users')->insert(['id' => 3]);
        $this->assertSame(['uah', 'rub', 'usd'], $connection->table('users')->orderBy('id')->pluck('fiat_currency')->all());

        $migration->down();
        $connection->table('users')->insert(['id' => 4]);
        $this->assertSame(['uah', 'rub', 'usd', 'rub'], $connection->table('users')->orderBy('id')->pluck('fiat_currency')->all());
    }
}
