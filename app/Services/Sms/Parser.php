<?php

namespace App\Services\Sms;

use App\Models\PaymentGateway;
use App\Models\SmsStopWord;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Sms\Utils\NormalizeMessage;
use App\Services\Sms\ValueObjects\ParserResultValue;
use Illuminate\Support\Facades\Cache;

class Parser
{
    public function parse(string $sender, string $message): ?ParserResultValue
    {
        $gateway = $this->getGatewayBySender($sender);

        if (empty($gateway)) {
            return null;
        }

        $amount = $this->parseAmountFromMessage($message);

        if (empty($amount)) {
            return null;
        }

        $card = $this->parseCardLastDigitsFromMessage($message);

        return new ParserResultValue(
            amount: Money::fromPrecision($amount, $gateway->currency),
            paymentGateway: $gateway,
            card_last_digits: $card
        );
    }

    public function normalizeAmount(string $raw): string
    {
        $raw = trim($raw);
        $raw = str_replace(["\xC2\xA0", ' '], '', $raw); // убираем пробелы и неразрывные пробелы

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

    public function parseAmountFromMessage($message): ?string
    {
        $triggerPatterns = [
            'перевод\s(?<amount>\d+(.\d+){0,3})р\sот\s.+\sбаланс',
            'перевод\sна\sсумму\s.+\sиз\s.+\sот\s',
            'perevod\s.+\sot\s.+\siz\s.+\sna\sschet\s',
            'зачислен перевод по',
            'поступление',
            'пополнение',
            'перевод по сбп',
            'зачисление',
            'зачислено',
            '[а-я]+\sпополнена',
            'popolnenie scheta',
            'postuplenie sredstv na schet',
            'postuplenie',
            'получен перевод',
            'popolnenie',
            'приход на карту',
            'перевод из',
            'vneseno',
            'перевел\(а\) вам',
            'postupil perevod',
            'перевод денежных средств',
            'перевод на карту',
            'zachislenie',
            '^перевод\sот\s',
            'приход',
            'пополнили карту',
            '\sперевод\s.+\sна\sкарту',
            'home\scredit\skazakhstan\sкарточка\s.+\sпополнена\sна\s',
            '\sвы\sполучили\sперевод:\s',
        ];

        $stopWords = Cache::remember('sms_stop_words', 60, function () {
            return SmsStopWord::all()->pluck('word')->toArray();
        });

        $exceptions = [
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\.\sтеперь\sна\sкарте\s.+₽$',
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\s-\sбаланс\:\s.+$',
            '^\d{2}\.\d{2}\.\d{2}\s\d{2}\:\d{2}\sзачисление\s\*(?<card_last_digits>\d{4})\srur\s(?<amount>\d+(.\d+){0,3})\;\sостаток\s.+$',
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\sот\s.+теперь\sна\sсчете\s.+₽$',
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\s—\sтеперь\sу\sвас\:\s.+$',
            '^\d{2}\:\d{2}\sперевод\s(?<amount>\d+(.\d+){0,3})р\sна\sкарту\s.+\sбаланс\s.+$',
            '^\+\s(?<amount>\d+(.\d+){0,3})\s₽\s—\sбаланс\:\s.+$',
            '^совкомбанк\s\+\s(?<amount>\d+(.\d+){0,3})\s₽\s—\sбаланс\:\s.+(?<card_last_digits>\d{4})$'
        ];

        $message = NormalizeMessage::normalize($message);

        $amount = null;

        foreach ($stopWords as $stopWord) {
            $regex = '/(|^|\s|;)' . $stopWord . '(\s|\.|:)/mi';
            preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

            if (! empty($matches[0])) {
                return null;
            }
        }

        foreach ($exceptions as $exception) {
            $regex = '/' . $exception . '/mi';
            preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

            if (! empty($matches[0]['amount'])) {
                $amount = $matches[0]['amount'];
                break;
            }
        }

        if (empty($amount)) {
            foreach ($triggerPatterns as $triggerWord) {
                $triggerWord = mb_strtolower($triggerWord);

                $regex = '/' . $triggerWord . '/mi';
                preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

                if (! empty($matches[0])) {
                    $amount = $this->findAmount($message);
                    break;
                }
            }
        }

        if ($amount) {
            $amount = $this->normalizeAmount($amount);
        }

        return $amount;
    }

    public function parseCardLastDigitsFromMessage(string $message): ?string
    {
        $regex = '(\*|^mir|\smir|счёт|mir-|ecmc|\s••\s|\s\d{6}\.\.|карта\s\*\*\*\s|^карта\s|\s··|\sмир)(?<card_last_digits>\d{4})(\D|$)';

        $regex = '/' . $regex . '/mi';
        preg_match_all($regex, $message, $matches, PREG_SET_ORDER);

        $digits = null;
        if (! empty($matches[0]['card_last_digits'])) {
            $digits = $matches[0]['card_last_digits'];
        }

        return $digits;
    }

    public function parseRaw(string $message): ?array
    {
        $amount = $this->parseAmountFromMessage($message);

        return !empty($amount) ? [
            'amount' => $amount,
            'card' => $this->parseCardLastDigitsFromMessage($message),
        ] : null;
    }

    protected function findAmount(string $message): ?string
    {
        $amountRegex = '(\s|\+)(?<amount>\d+(.\d+){0,3})\s{0,1}(RUB|rub|р|p|₽|RUR|rur|rurcard2card|руб|₸|kzt)(\s|\.|\,|\;|$)';

        $regex = '/' . $amountRegex . '/mi';
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
}
