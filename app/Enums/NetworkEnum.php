<?php

namespace App\Enums;

use App\Traits\Enumable;

enum NetworkEnum: string
{
    use Enumable;

    case ETH = 'eth';      // Ethereum, Ether, ETH
    case BSC = 'bsc';      // BNB Chain (BSC), Binance Coin, BNB
    case ARB = 'arb';      // Arbitrum, Arbitrum, ARB
    case AVAX = 'avax';    // Avalanche C-Chain, Avalanche, AVAX
    case MATIC = 'matic';  // Polygon, Polygon (ранее Matic), MATIC
    case TRX = 'trx';      // Tron, Tronix, TRX

    /**
     * Возвращает информацию о валюте: "Название валюты (Сеть)"
     */
    public function getCurrencyInfo(): string
    {
        return match ($this) {
            self::ETH => 'Ether (Ethereum)',
            self::BSC => 'Binance Coin (BNB Chain / BSC)',
            self::ARB => 'Arbitrum (Arbitrum)',
            self::AVAX => 'Avalanche (Avalanche C-Chain)',
            self::MATIC => 'Polygon (Polygon, ранее Matic)',
            self::TRX => 'Tronix (Tron)',
        };
    }

    /**
     * Возвращает код валюты для данной сети
     */
    public function getCurrencyCode(): string
    {
        return match ($this) {
            self::ETH => 'ETH',
            self::BSC => 'BNB',
            self::ARB => 'ARB',
            self::AVAX => 'AVAX',
            self::MATIC => 'MATIC',
            self::TRX => 'TRX',
        };
    }
}
