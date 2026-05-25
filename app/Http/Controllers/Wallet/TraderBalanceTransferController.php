<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wallet;

use App\Exceptions\TraderBalanceTransferException;
use App\Exceptions\WalletException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\TraderTransfer\CheckRecipientRequest;
use App\Http\Requests\Wallet\TraderTransfer\StoreTransferRequest;
use App\Services\Wallet\TraderBalanceTransferService;
use Illuminate\Http\JsonResponse;

class TraderBalanceTransferController extends Controller
{
    public function __construct(
        private readonly TraderBalanceTransferService $traderBalanceTransferService,
    ) {}

    public function recipient(CheckRecipientRequest $request): JsonResponse
    {
        try {
            $recipient = $this->traderBalanceTransferService->resolveRecipient(
                $request->user(),
                $request->recipientLogin(),
            );

            return response()->json(
                $this->traderBalanceTransferService->recipientPreview($recipient),
            );
        } catch (TraderBalanceTransferException $exception) {
            return $this->failTransfer($exception);
        } catch (WalletException $exception) {
            return $this->failTransferAmount($exception);
        }
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        try {
            $this->traderBalanceTransferService->transfer(
                $request->user(),
                $request->recipientLogin(),
                $request->amountMoney(),
            );

            return response()->json([
                'message' => 'Средства переведены.',
            ]);
        } catch (TraderBalanceTransferException $exception) {
            return $this->failTransfer($exception);
        } catch (WalletException $exception) {
            return $this->failTransferAmount($exception);
        }
    }

    private function failTransfer(TraderBalanceTransferException $exception): JsonResponse
    {
        $message = $exception->getMessage();

        return response()->json([
            'message' => $message,
            'errors' => $this->errorsForTransferException($exception, $message),
        ], 422);
    }

    private function failTransferAmount(WalletException $exception): JsonResponse
    {
        $message = $exception->getMessage();

        return response()->json([
            'message' => $message,
            'errors' => [
                'amount' => [$message],
            ],
        ], 422);
    }

    /**
     * @return array<string, list<string>>
     */
    private function errorsForTransferException(TraderBalanceTransferException $exception, string $message): array
    {
        if (str_contains($message, 'рабочем балансе')) {
            return [
                'amount' => [$message],
            ];
        }

        if (str_contains($message, 'логин') || str_contains($message, 'Трейдер не найден')) {
            return [
                'login' => [$message],
            ];
        }

        return [];
    }
}
