<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wallet;

use App\Exceptions\TraderBalanceTransferException;
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
        }
    }

    private function failTransfer(TraderBalanceTransferException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], 422);
    }
}
