<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V2\PayoutReceiptResource;
use App\Models\Payout\Payout;
use App\Models\Payout\PayoutReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PayoutReceiptController extends Controller
{
    private const RECEIPT_DISK = 'local';

    public function index(Payout $payout): JsonResponse
    {
        Gate::authorize('api-v2-access-to-merchant', $payout->merchant);

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
                'id' => $item['id'],
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
            'receipts' => PayoutReceiptResource::collection($receipts)->resolve(),
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
            return $items;
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
