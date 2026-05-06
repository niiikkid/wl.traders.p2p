<?php

namespace App\Services\Sms;

use App\Models\PaymentGateway;
use App\Models\SmsStopWord;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Sms\Profiles\Contracts\SmsAmountParsingProfileContract;
use App\Services\Sms\Profiles\SmsAmountParsingProfileResolver;
use App\Services\Sms\Utils\NormalizeMessage;
use App\Services\Sms\ValueObjects\ParserResultValue;
use Illuminate\Support\Facades\Cache;

class Parser
{
    protected ?PaymentGateway $paymentGateway = null;

    public function __construct(
        protected SmsAmountParsingProfileResolver $profileResolver = new SmsAmountParsingProfileResolver,
    ) {}

    public function parse(string $sender, string $message): ?ParserResultValue
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

        $stopWords = Cache::remember('sms_stop_words', 60, function () {
            return SmsStopWord::all()->pluck('word')->toArray();
        });

        $message = NormalizeMessage::normalize($message);

        $amount = null;

        foreach ($stopWords as $stopWord) {
            $regex = '/(|^|\s|;)'.$stopWord.'(\s|\.|:)/mi';
            preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

            if (! empty($matches[0])) {
                return null;
            }
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
