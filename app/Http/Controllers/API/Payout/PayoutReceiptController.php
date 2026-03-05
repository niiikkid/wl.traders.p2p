<?php

namespace App\Http\Controllers\API\Payout;

use App\Http\Controllers\Controller;
use App\Models\Payout\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PayoutReceiptController extends Controller
{
    private const RECEIPT_DISK = 'local';

    public function show(Request $request, Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        if (! $payout->receipt_path) {
            return response()->failWithMessage('Чек для этой выплаты отсутствует.', 404);
        }

        $disk = Storage::disk(self::RECEIPT_DISK);

        if (! $disk->exists($payout->receipt_path)) {
            return response()->failWithMessage('Файл чека не найден.', 404);
        }

        $contents = $disk->get($payout->receipt_path);
        $mime = $disk->mimeType($payout->receipt_path) ?: 'application/octet-stream';

        return response()->success([
            'payout_id' => $payout->uuid,
            'filename' => basename($payout->receipt_path),
            'mime_type' => $mime,
            'size' => strlen($contents),
            'base64' => base64_encode($contents),
        ]);
    }
}


