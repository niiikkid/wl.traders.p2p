<?php

namespace App\Support;

class TraderCommissionTierResolver
{
    public const EPSILON = 0.00001;

    /**
     * @param array<int, array<string, mixed>> $tiers
     * @return array<int, array{from: float, to: float, rate: float}>
     */
    public static function normalize(array $tiers): array
    {
        $normalized = [];

        foreach ($tiers as $tier) {
            $normalized[] = [
                'from' => (float) ($tier['from'] ?? 0),
                'to' => (float) ($tier['to'] ?? 0),
                'rate' => (float) ($tier['rate'] ?? 0),
            ];
        }

        usort($normalized, fn (array $left, array $right) => $left['from'] <=> $right['from']);

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $tiers
     * @return array{
     *     normalized: array<int, array{from: float, to: float, rate: float}>,
     *     errors: array<int, string>
     * }
     */
    public static function normalizeAndValidate(array $tiers, float $minLimit, float $maxLimit): array
    {
        $normalized = self::normalize($tiers);
        $errors = [];

        if (empty($normalized)) {
            return [
                'normalized' => [],
                'errors' => ['Добавьте хотя бы один уровень комиссии трейдера.'],
            ];
        }

        if ($minLimit >= $maxLimit) {
            return [
                'normalized' => $normalized,
                'errors' => ['Минимальный лимит должен быть меньше максимального.'],
            ];
        }

        foreach ($normalized as $index => $tier) {
            if ($tier['from'] >= $tier['to']) {
                $errors[] = "Уровень #".($index + 1).": начало диапазона должно быть меньше конца.";
            }

            if ($tier['from'] < $minLimit - self::EPSILON || $tier['to'] > $maxLimit + self::EPSILON) {
                $errors[] = "Уровень #".($index + 1).": диапазон должен находиться в пределах лимитов метода.";
            }

            if ($tier['rate'] < 0) {
                $errors[] = "Уровень #".($index + 1).": комиссия не может быть отрицательной.";
            }

            if ($index === 0 && abs($tier['from'] - $minLimit) > self::EPSILON) {
                $errors[] = 'Первый уровень должен начинаться с минимального лимита метода.';
            }

            if ($index > 0) {
                $prevTier = $normalized[$index - 1];
                if (abs($prevTier['to'] - $tier['from']) > self::EPSILON) {
                    $errors[] = "Уровни #{$index} и #".($index + 1)." должны идти подряд без разрывов и пересечений.";
                }
            }
        }

        $lastTier = $normalized[count($normalized) - 1];
        if (abs($lastTier['to'] - $maxLimit) > self::EPSILON) {
            $errors[] = 'Последний уровень должен заканчиваться максимальным лимитом метода.';
        }

        return [
            'normalized' => $normalized,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int, array<string, mixed>>|null $tiers
     */
    public static function resolveRate(?array $tiers, float $amount, float $defaultRate): float
    {
        if (empty($tiers)) {
            return $defaultRate;
        }

        $normalized = self::normalize($tiers);
        $lastIndex = count($normalized) - 1;

        foreach ($normalized as $index => $tier) {
            $from = (float) $tier['from'];
            $to = (float) $tier['to'];
            $rate = (float) $tier['rate'];

            $withinLowerBound = $amount >= $from;
            $withinUpperBound = $index === $lastIndex ? $amount <= $to : $amount < $to;

            if ($withinLowerBound && $withinUpperBound) {
                return $rate;
            }
        }

        return $defaultRate;
    }
}
