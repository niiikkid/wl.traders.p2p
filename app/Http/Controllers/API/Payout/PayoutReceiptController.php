<?php

namespace App\Http\Controllers\API\Payout;

use App\Http\Controllers\Controller;
use App\Models\Payout\Payout;
use App\Models\Payout\PayoutReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PayoutReceiptController extends Controller
{
    private const RECEIPT_DISK = 'local';

    public function index(Request $request, Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        $disk = Storage::disk(self::RECEIPT_DISK);
        $receiptItems = $this->resolveReceiptItems($payout);

        if ($receiptItems === []) {
            return response()->failWithMessage('Чеки для этой выплаты отсутствуют.', 404);
        }

        $receipts = [];

        foreach ($receiptItems as $item) {
            if (! $disk->exists($item['path'])) {
                continue;
            }

            $contents = $disk->get($item['path']);
            $receipts[] = [
                'receipt_id' => $item['id'],
                'filename' => basename($item['path']),
                'mime_type' => $disk->mimeType($item['path']) ?: 'application/octet-stream',
                'size' => strlen($contents),
                'base64' => base64_encode($contents),
            ];
        }

        if ($receipts === []) {
            return response()->failWithMessage('Файлы чеков не найдены.', 404);
        }

        return response()->success([
            'payout_id' => $payout->uuid,
            'receipts' => $receipts,
        ]);
    }

    /**
     * @return array<int, array{id: int|null, path: string}>
     */
    private function resolveReceiptItems(Payout $payout): array
    {
        $payout->loadMissing('receipts');

        $items = $payout->receipts
            ->map(fn (PayoutReceipt $receipt) => [
                'id' => $receipt->id,
                'path' => $receipt->path,
            ])
            ->values()
            ->all();

        if ($items !== []) {
            return array_slice($items, 0, 5);
        }

        if (! $payout->receipt_path) {
            return [];
        }

        return [[
            'id' => null,
            'path' => $payout->receipt_path,
        ]];
    }
}
