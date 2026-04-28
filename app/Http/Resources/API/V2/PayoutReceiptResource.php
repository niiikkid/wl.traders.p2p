<?php

declare(strict_types=1);

namespace App\Http\Resources\API\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'receipt_id' => $this->resource['id'],
            'filename' => $this->resource['filename'],
            'mime_type' => $this->resource['mime_type'],
            'size' => $this->resource['size'],
            'base64' => $this->resource['base64'],
        ];
    }
}
