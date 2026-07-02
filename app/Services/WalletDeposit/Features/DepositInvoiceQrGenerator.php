<?php

namespace App\Services\WalletDeposit\Features;

use App\Models\WalletDepositInvoice;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

/**
 * Generates and privately stores a QR image whose payload is the deposit address.
 * Rendered as SVG so no image extension (Imagick/GD) is required.
 */
class DepositInvoiceQrGenerator
{
    public function generate(WalletDepositInvoice $invoice): void
    {
        $disk = (string) config('services.wallet_deposit.qr_disk', 'local');
        $path = "wallet-deposit-invoices/{$invoice->uuid}/qr.svg";

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($invoice->address);

        Storage::disk($disk)->put($path, $svg);

        $invoice->update([
            'qr_disk' => $disk,
            'qr_path' => $path,
        ]);
    }
}
