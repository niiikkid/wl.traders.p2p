<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Enums\TransactionType;
use App\Exceptions\TraderBalanceTransferException;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Money\Money;
use App\Services\Wallet\GiveToBalanceHandler\GiveToTrust;
use App\Services\Wallet\TakeFromBalanceHandler\TakeFromTrust;
use App\Utils\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TraderBalanceTransferService
{
    /**
     * @return array{login: string, avatar_url: ?string}
     */
    public function recipientPreview(User $recipient): array
    {
        return [
            'login' => $recipient->email,
            'avatar_url' => $recipient->avatarUrl(),
        ];
    }

    public function resolveRecipient(User $sender, string $login): User
    {
        $this->assertSenderEligible($sender);

        $recipient = $this->recipientQuery($sender, $login)->first();

        if ($recipient === null) {
            throw TraderBalanceTransferException::recipientNotAvailable();
        }

        return $recipient;
    }

    public function transfer(User $sender, string $recipientLogin, Money $amount): void
    {
        $this->assertSenderEligible($sender);

        Transaction::run(function () use ($sender, $recipientLogin, $amount): void {
            $recipient = $this->recipientQuery($sender, $recipientLogin)->first();

            if ($recipient === null) {
                throw TraderBalanceTransferException::recipientNotAvailable();
            }

            $sender->refresh();
            $recipient->refresh();

            $this->assertSenderEligible($sender);
            $this->assertRecipientEligible($recipient, $sender);

            [$senderWallet, $recipientWallet] = $this->lockWallets(
                $this->resolveWallet($sender),
                $this->resolveWallet($recipient),
            );

            if ($senderWallet->trust_balance->lessThan($amount)) {
                throw TraderBalanceTransferException::insufficientTrustBalance(
                    $senderWallet->trust_balance->toBeauty(),
                );
            }

            (new TakeFromTrust)->handle(
                $senderWallet,
                $amount,
                TransactionType::TRANSFER_TO_TRADER,
            );

            (new GiveToTrust)->handle(
                $recipientWallet,
                $amount,
                TransactionType::TRANSFER_FROM_TRADER,
            );
        });
    }

    /**
     * @return Builder<User>
     */
    private function recipientQuery(User $sender, string $login): Builder
    {
        return User::query()
            ->role('Trader')
            ->where('team_leader_id', $sender->team_leader_id)
            ->where('email', $login)
            ->where('id', '!=', $sender->id)
            ->whereNull('banned_at')
            ->whereNull('archived_at');
    }

    private function assertSenderEligible(User $sender): void
    {
        if (
            ! $sender->hasRole('Trader')
            || $sender->team_leader_id === null
            || $sender->banned_at !== null
            || $sender->archived_at !== null
        ) {
            throw TraderBalanceTransferException::transferUnavailable();
        }
    }

    private function assertRecipientEligible(User $recipient, User $sender): void
    {
        if (! $recipient->hasRole('Trader')) {
            throw TraderBalanceTransferException::recipientNotAvailable();
        }

        if ($recipient->team_leader_id !== $sender->team_leader_id) {
            throw TraderBalanceTransferException::recipientNotAvailable();
        }

        if ($recipient->id === $sender->id) {
            throw TraderBalanceTransferException::recipientNotAvailable();
        }

        if ($recipient->banned_at !== null || $recipient->archived_at !== null) {
            throw TraderBalanceTransferException::recipientNotAvailable();
        }
    }

    private function resolveWallet(User $user): Wallet
    {
        $user->loadMissing('wallet');

        if ($user->wallet instanceof Wallet) {
            return $user->wallet;
        }

        return services()->wallet()->create($user);
    }

    /**
     * @return array{0: Wallet, 1: Wallet}
     */
    private function lockWallets(Wallet $first, Wallet $second): array
    {
        $walletIds = [$first->id, $second->id];
        sort($walletIds);

        $locked = Wallet::query()
            ->whereIn('id', $walletIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        return [
            $locked[$first->id],
            $locked[$second->id],
        ];
    }
}
