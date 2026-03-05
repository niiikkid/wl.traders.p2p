<?php

namespace App\Contracts;

use App\Enums\BalanceType;
use App\Exceptions\FundsHolderException;
use App\Models\FundsOnHold;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use App\Services\Money\Money;
use Carbon\Carbon;

interface FundsHolderServiceContract
{
    /**
     * @throws FundsHolderException
     */
    public function holdFundsFor(Money $amount, Wallet $sourceWallet, ?Wallet $destinationWallet, BalanceType $sourceWalletBalanceType, ?BalanceType $destinationWalletBalanceType, Model $forAction, ?Carbon $until = null): FundsOnHold;

    /**
     * @throws FundsHolderException
     */
    public function setTimer(FundsOnHold $fundsOnHold, Carbon $timer): void;

    /**
     * @throws FundsHolderException
     */
    public function changeDestination(FundsOnHold $fundsOnHold, ?Wallet $destinationWallet, ?BalanceType $destinationWalletBalanceType): FundsOnHold;

    /**
     * @throws FundsHolderException
     */
    public function execute(FundsOnHold $fundsOnHold): FundsOnHold;

    /**
     * @throws FundsHolderException
     */
    public function cancel(FundsOnHold $fundsOnHold): FundsOnHold;
}
