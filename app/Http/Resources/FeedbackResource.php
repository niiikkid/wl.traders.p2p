<?php

namespace App\Http\Resources;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Feedback $this
         */
        return [
            'id' => $this->id,
            'content' => $this->content,
            'created_at' => $this->created_at?->toISOString(),
            'is_own' => (int) $this->user_id === (int) $request->user()?->id,
            'is_starred' => (bool) ($this->is_starred ?? false),
            'is_hidden' => (bool) ($this->is_hidden ?? false),
            'author' => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
                'login' => $this->author?->email,
            ],
        ];
    }
}
