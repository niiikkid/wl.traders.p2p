<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum CascadeDealStatus: string
{
    use Enumable;

    case PROVISIONING = 'provisioning';
    case PROVISIONING_FAILED = 'provisioning_failed';
    case SUCCESS = 'success';
    case FAIL = 'fail';
    case PENDING = 'pending';

    public function isFinal(): bool
    {
        return match ($this) {
            self::SUCCESS, self::FAIL => true,
            default => false,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function merchantApiVisibleCases(): array
    {
        return [
            self::PENDING,
            self::SUCCESS,
            self::FAIL,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function merchantApiVisibleValues(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            self::merchantApiVisibleCases(),
        );
    }
}
