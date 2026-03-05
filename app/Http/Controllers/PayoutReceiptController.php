<?php

namespace App\Http\Controllers;

use App\Models\Payout\Payout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayoutReceiptController extends Controller
{
    private const RECEIPT_DISK = 'local';

    public function show(Request $request, Payout $payout): BinaryFileResponse
    {
        $payout->loadMissing('merchant');

        if (! $this->canViewReceipt($request, $payout)) {
            abort(403);
        }

        if (! $payout->receipt_path) {
            abort(404, 'Чек для этой выплаты отсутствует.');
        }

        $disk = Storage::disk(self::RECEIPT_DISK);

        if (! $disk->exists($payout->receipt_path)) {
            abort(404, 'Файл чека не найден.');
        }

        $path = $disk->path($payout->receipt_path);
        $mime = $disk->mimeType($payout->receipt_path) ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    private function canViewReceipt(Request $request, Payout $payout): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('Super Admin') || $user->hasRole('Support')) {
            return true;
        }

        if ($payout->trader_id && $payout->trader_id === $user->id) {
            return true;
        }

        if ($payout->merchant && $payout->merchant->user_id === $user->id) {
            return true;
        }

        return false;
    }
}


