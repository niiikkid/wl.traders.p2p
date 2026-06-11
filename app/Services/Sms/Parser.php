<?php

namespace App\Services\Sms;

use App\Contracts\OpenAiServiceContract;
use App\Enums\SmsType;
use App\Models\PaymentGateway;
use App\Models\SmsStopWord;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Sms\Profiles\Contracts\SmsAmountParsingProfileContract;
use App\Services\Sms\Profiles\SmsAmountParsingProfileResolver;
use App\Services\Sms\Utils\NormalizeMessage;
use App\Services\Sms\ValueObjects\ParserResultValue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use JsonException;
use RuntimeException;

class Parser
{
    private const OPERATION_CLASSIFICATION_PROMPT = <<<'PROMPT'
Ты классифицируешь текст SMS или push-уведомления о финансовой операции.

Определи тип операции:
- "in" — поступление средств;
- "out" — списание, оплата, перевод или снятие средств;
- "none" — операция не является платёжной или тип нельзя определить с высокой уверенностью.

Возвращай только валидный JSON без пояснений:

{
  "value": "in" | "out" | "none"
}

Правила:
1. Используй "in" или "out" только при высокой уверенности.
2. Если нет явного поступления или списания средств, используй "none".
3. Баланс сам по себе не является операцией.
4. Не добавляй дополнительных полей.
PROMPT;

    private const PAYMENT_DETAILS_EXTRACTION_PROMPT = <<<'PROMPT'
Ты извлекаешь данные из push-уведомления или SMS о финансовой операции.

Операция уже заранее определена как поступление или списание средств.

Формат входного текста:
- в начале сообщения всегда указан отправитель;
- отправитель отделён от текста уведомления символом "|";
- отправитель может быть названием приложения, названием банка, номером или адресом отправителя.

Возвращай только валидный JSON без пояснений:

{
  "amount": string | null,
  "card": string | null,
  "balance": string | null,
  "bank": string | null
}

Правила:
1. bank — значение до первого символа "|", очищенное от лишних пробелов.
2. Если bank состоит только из цифр, верни его как есть.
3. Если bank — текстовое название, приведи его к аккуратному виду:
   - убери лишние пробелы;
   - сохрани дефисы и точки, если они есть;
   - каждое отдельное слово начинай с заглавной буквы.
4. Примеры bank:
   - "freebank" → "Freebank"
   - "mono bank" → "Mono Bank"
   - "privat24" → "Privat24"
   - "3700" → "3700"
5. amount — сумма самой операции, а не баланс и не комиссия.
6. balance — баланс после операции, если он явно указан. Иначе null.
7. amount и balance возвращай строкой без валюты, пробелов и разделителей тысяч.
8. Если есть копейки/центы, используй точку как десятичный разделитель.
9. Примеры нормализации сумм:
   - "1 000.00 uah" → "1000.00"
   - "+1 000.00 ₴" → "1000.00"
   - "1 000,50 грн" → "1000.50"
   - "1000" → "1000"
10. card — только последние 4 цифры карты, счёта или реквизитов, если они явно указаны.
11. Если указано несколько сумм, не используй баланс или комиссию как amount.
12. Если сумму, карту, баланс или банк определить нельзя, верни null в соответствующем поле.
13. Не добавляй дополнительных полей.
PROMPT;

    protected ?PaymentGateway $paymentGateway = null;

    public function __construct(
        protected SmsAmountParsingProfileResolver $profileResolver = new SmsAmountParsingProfileResolver,
    ) {}

    /**
     * @param  SmsType  $messageType  Канал: SMS или push (передаётся в OpenAI user-промпт).
     * @return array{operation_type: string, amount: string, card: ?string, balance: ?string, bank: ?string}|null
     */
    public function parse(string $sender, string $message, SmsType $messageType): ?array
    {
        if ($this->hasStopWord($message)) {
            return null;
        }

        if (! $this->containsCurrencyMarker($message)) {
            return null;
        }

        $operationType = $this->classifyOperation($sender, $message, $messageType);

        if (! in_array($operationType, ['in', 'out'], true)) {
            return null;
        }

        $details = $this->extractPaymentDetails($sender, $message, $messageType);
        $amount = $details['amount'] ?? null;

        if (! is_string($amount) || $amount === '') {
            return null;
        }

        return [
            'operation_type' => $operationType,
            'amount' => $amount,
            'card' => $this->nullableString($details['card'] ?? null),
            'balance' => $this->nullableString($details['balance'] ?? null),
            'bank' => $this->nullableString($details['bank'] ?? null),
        ];
    }

    public function parseLegacy(string $sender, string $message): ?ParserResultValue
    {
        $this->paymentGateway = $this->getGatewayBySender($sender);

        if (empty($this->paymentGateway)) {
            return null;
        }

        $amount = $this->parseAmountFromMessage($message, $this->paymentGateway->currency);

        if (empty($amount)) {
            return null;
        }

        $card = $this->parseCardLastDigitsFromMessage($message, $this->paymentGateway->currency);

        return new ParserResultValue(
            amount: Money::fromPrecision($amount, $this->paymentGateway->currency),
            paymentGateway: $this->paymentGateway,
            card_last_digits: $card
        );
    }

    public function normalizeAmount(string $raw): string
    {
        $raw = trim($raw);
        // NBSP, узкий пробел U+202F между тысячами и прочие Unicode-пробелы (\p{Zs})
        $raw = preg_replace('/\p{Zs}+/u', '', $raw);

        // Если число содержит и точку и запятую
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                // Европейский стиль: 1.234,56 → 1234.56
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                // Американский стиль: 1,234.56 → 1234.56
                $raw = str_replace(',', '', $raw);
            }
        } elseif (str_contains($raw, ',')) {
            $parts = explode(',', $raw);

            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                // Если после запятой 1–2 цифры → дробная часть
                $raw = str_replace(',', '.', $raw);
            } else {
                // Иначе это разделитель тысяч — удаляем
                $raw = str_replace(',', '', $raw);
            }
        } elseif (str_contains($raw, '.')) {
            $parts = explode('.', $raw);

            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                // Если после точки 1–2 цифры → дробная часть
                // ничего не меняем
            } else {
                // Иначе это разделитель тысяч — удаляем
                $raw = str_replace('.', '', $raw);
            }
        }

        $value = (float) $raw;

        return (fmod($value, 1.0) === 0.0) ? (int) $value : $value;
    }

    public function parseAmountFromMessage(string $message, ?Currency $currency = null): ?string
    {
        $currency ??= Currency::RUB();
        $profile = $this->profileResolver->resolve($currency);

        $triggerPatterns = $profile->triggerPatterns();
        $exceptions = $profile->exceptionPatterns();

        $message = NormalizeMessage::normalize($message);

        $amount = null;

        if ($this->hasStopWord($message)) {
            return null;
        }

        foreach ($exceptions as $exception) {
            $regex = '/'.$exception.'/miu';
            preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

            if (! empty($matches[0]['amount'])) {
                $amount = $matches[0]['amount'];
                break;
            }
        }

        if (empty($amount)) {
            foreach ($triggerPatterns as $triggerWord) {
                // Сообщение уже в нижнем регистре (NormalizeMessage). mb_strtolower() по шаблону ломает escapes (\S → \s).
                $regex = '/'.$triggerWord.'/miu';
                preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

                if (! empty($matches[0])) {
                    $amount = $this->findAmount($message, $profile);
                    break;
                }
            }
        }

        if ($amount) {
            $amount = $this->normalizeAmount($amount);
        }

        return $amount;
    }

    public function parseCardLastDigitsFromMessage(string $message, ?Currency $currency = null): ?string
    {
        $currency ??= Currency::RUB();
        $profile = $this->profileResolver->resolve($currency);

        $body = $profile->cardLastDigitsPattern();
        $regex = '/'.$body.'/miu';
        preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

        $digits = null;
        if (! empty($matches[0]['card_last_digits'])) {
            $digits = $matches[0]['card_last_digits'];
        }

        return $digits;
    }

    /**
     * @param  string|null  $sender  Нормализованный или сырой отправитель; нужен для валюты шлюза при разборе только текста SMS.
     */
    public function parseRaw(string $message, ?string $sender = null): ?array
    {
        $currency = $this->currencyForRawParse($sender);
        $amount = $this->parseAmountFromMessage($message, $currency);

        return ! empty($amount) ? [
            'amount' => $amount,
            'card' => $this->parseCardLastDigitsFromMessage($message, $currency),
        ] : null;
    }

    protected function findAmount(string $message, SmsAmountParsingProfileContract $profile): ?string
    {
        $markers = $profile->amountCurrencyMarkers();
        $amountRegex = '(\s|\+)(?<amount>\d+(.\d+){0,3})\s{0,1}('.$markers.')(\s|\.|\,|\;|$)';

        $regex = '/'.$amountRegex.'/miu';
        preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

        $amount = null;
        if (! empty($matches[0]['amount'])) {
            $amount = $matches[0]['amount'];
        }

        return $amount;
    }

    public function hasStopWord(string $message): bool
    {
        return $this->findMatchedStopWord($message) !== null;
    }

    public function findMatchedStopWord(string $message): ?string
    {
        $stopWords = Cache::remember('sms_stop_words', 60, function () {
            return SmsStopWord::all()->pluck('word')->toArray();
        });

        foreach ($stopWords as $stopWord) {
            $stopWord = trim((string) $stopWord);
            if ($stopWord === '') {
                continue;
            }

            if ($this->matchesStopWord($message, $stopWord)) {
                return $stopWord;
            }
        }

        return null;
    }

    public function matchesStopWord(string $message, string $normalizedStopWord): bool
    {
        $normalizedStopWord = trim($normalizedStopWord);
        if ($normalizedStopWord === '') {
            return false;
        }

        $message = NormalizeMessage::normalize($message);
        $quoted = preg_quote($normalizedStopWord, '/');
        // Whole token in any script: not surrounded by Unicode letters (works at line/string ends).
        $regex = '/(?<!\p{L})'.$quoted.'(?!\p{L})/iu';

        return preg_match($regex, $message) === 1;
    }

    protected function containsCurrencyMarker(string $message): bool
    {
        $markers = implode('|', array_map(
            fn (Currency $currency): string => $this->profileResolver->resolve($currency)->amountCurrencyMarkers(),
            [Currency::RUB(), Currency::KZT(), Currency::UAH()]
        ));

        $regex = '/(^|[\s+\-.,:;|]|\d)('.$markers.')($|[\s.,:;|]|\d)/iu';

        return preg_match($regex, NormalizeMessage::normalize($message)) === 1;
    }

    protected function classifyOperation(string $sender, string $message, SmsType $messageType): ?string
    {
        $response = $this->askOpenAi(self::OPERATION_CLASSIFICATION_PROMPT, $sender, $message, $messageType);
        $value = $response['value'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return array{amount?: mixed, card?: mixed, balance?: mixed, bank?: mixed}
     */
    protected function extractPaymentDetails(string $sender, string $message, SmsType $messageType): array
    {
        return $this->askOpenAi(self::PAYMENT_DETAILS_EXTRACTION_PROMPT, $sender, $message, $messageType) ?? [];
    }

    protected function askOpenAi(string $systemPrompt, string $sender, string $message, SmsType $messageType): ?array
    {
        try {
            $openAi = app(OpenAiServiceContract::class);
            $settings = $openAi->getSettings();
            $model = $settings->selected_model;

            if (! is_string($model) || $model === '') {
                return null;
            }

            $typeLine = $this->formatMessageTypeForPrompt($messageType);
            $userPrompt = "Тип сообщения: {$typeLine}\nsender: {$sender}\nmessage: {$message}";

            $response = $openAi->prompt(
                prompt: $userPrompt,
                systemPrompt: $systemPrompt,
                model: $model,
            );

            $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (ConnectionException|JsonException|RuntimeException $exception) {
            report($exception);

            return null;
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function formatMessageTypeForPrompt(SmsType $messageType): string
    {
        return match ($messageType) {
            SmsType::SMS => 'SMS (текстовое сообщение с телефона)',
            SmsType::PUSH => 'PUSH (push-уведомление от приложения)',
        };
    }

    public function getGatewayBySender(string $sender): ?PaymentGateway
    {
        /**
         * @var PaymentGateway $paymentGateway
         */
        $paymentGateways = PaymentGateway::get(['id', 'code', 'name', 'currency', 'sms_senders']);
        $paymentGateway = null;

        $sender = NormalizeMessage::normalize($sender);

        foreach ($paymentGateways as $gateway) {
            if (empty($gateway->sms_senders)) {
                continue;
            }

            $smsSenders = $gateway->sms_senders;

            $smsSenders = array_map(function ($sender) {
                return NormalizeMessage::normalize($sender);
            }, $smsSenders);

            if (in_array($sender, $smsSenders)) {
                $paymentGateway = $gateway;
            }
        }

        if (! $paymentGateway) {
            return null;
        }

        return $paymentGateway;
    }

    protected function currencyForRawParse(?string $sender): Currency
    {
        if ($sender === null || $sender === '') {
            return Currency::RUB();
        }

        $gateway = $this->getGatewayBySender($sender);

        return $gateway?->currency ?? Currency::RUB();
    }
}
