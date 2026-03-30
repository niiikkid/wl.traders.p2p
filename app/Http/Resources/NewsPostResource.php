<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\NewsPost */
class NewsPostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'cover_image_url' => $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null,
            'content_html' => $this->content_html,
            'views_count' => (int) $this->views_count,
            'likes_count' => (int) $this->likes_count,
            'dislikes_count' => (int) $this->dislikes_count,
            'user_reaction' => $this->whenLoaded('reactions', fn () => $this->reactions->first()?->reaction),
            'created_at' => $this->created_at,
            'author' => [
                'id' => $this->author?->id,
                'name' => $this->author?->email,
            ],
        ];
    }
}
